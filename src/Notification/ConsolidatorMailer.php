<?php

namespace App\Notification;

use App\Entity\ConsolidatorInstruction;
use App\Entity\RequiredDocument;
use App\Repository\NotificationRecipientsRepository;
use App\Service\UploadPath;
use App\Workflow\RequiredDocumentType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Manda las instrucciones de entrega al consolidador de carga (XCF, el unico
 * hasta ahora) — mismo patron que ClassificationMailer/PrevioReportMailer:
 * direccion(es) fija(s) editables desde /admin (ver NotificationRecipients),
 * mas copia al ejecutivo que mando la instruccion y al cliente dueño del
 * expediente.
 */
final class ConsolidatorMailer
{
    public const TO_KEY = 'consolidator_to';
    public const CC_KEY = 'consolidator_cc';

    /**
     * Botón de pruebas de DashboardConsolidatorInstructions::create() — se
     * quita junto con ese botón antes de producción (ver bypassSoia() en
     * DashboardCaseFiles, mismo espíritu).
     */
    public const TEST_RECIPIENT = 'carlosceptile@gmail.com';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        private readonly NotificationRecipientsRepository $notificationRecipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        private readonly UploadPath $uploadPath,
    ) {
    }

    /**
     * $test manda solo a TEST_RECIPIENT, ignorando NotificationRecipients y
     * los destinatarios dinámicos — para probar sin arriesgar mandarle nada
     * a XCF ni exponerle a un correo externo la cc interna (ejecutivo/cliente).
     */
    public function notify(ConsolidatorInstruction $instruction, bool $test = false): void
    {
        $import = $instruction->getReference();
        $company = $import->getIdCompany();

        $to = $test ? [self::TEST_RECIPIENT] : $this->notificationRecipients->emailsFor(self::TO_KEY);
        $cc = $test ? [] : $this->dedupe(array_merge(
            $this->notificationRecipients->emailsFor(self::CC_KEY),
            array_filter([$instruction->getCreatedBy()?->getEmail()]),
            $this->recipients->clientEmails($import),
        ));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf(
                '%sINSTRUCCIONES // %s // %s // %s // %s',
                $test ? '[PRUEBA] ' : '',
                $company->getName(),
                $instruction->getDescripcion(),
                $import->getAgencyReference(),
                $import->getImportNumber(),
            ))
            ->htmlTemplate('emails/consolidator_instruction.html.twig')
            ->context(['instruction' => $instruction, 'import' => $import])
            ->to(...$to);

        if ($cc !== []) {
            $email->cc(...$cc);
        }

        if ($instruction->getFileRoute()) {
            $xlsxPath = $this->uploadPath->resolve($instruction->getFileRoute());

            if (is_file($xlsxPath)) {
                $email->attachFromPath($xlsxPath, $instruction->suggestedFileName());
            }
        }

        $pedimento = $this->fullPedimento($import->getRequiredDocuments()->toArray());

        if ($pedimento && $pedimento->getRoute()) {
            $pdfPath = $this->uploadPath->resolve($pedimento->getRoute());

            if (is_file($pdfPath)) {
                $email->attachFromPath($pdfPath, sprintf('pedimento-%s.pdf', $import->getClientReference()));
            }
        }

        $this->mailer->send($email);
    }

    /**
     * @param list<RequiredDocument> $documents
     */
    private function fullPedimento(array $documents): ?RequiredDocument
    {
        foreach ($documents as $document) {
            if ($document->getType() === RequiredDocumentType::FULL_PEDIMENTO) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @param list<string> $emails
     *
     * @return list<string>
     */
    private function dedupe(array $emails): array
    {
        return array_keys(array_flip($emails));
    }
}
