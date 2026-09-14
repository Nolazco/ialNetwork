<?php

namespace App\Notification;

use App\Entity\ConsolidatorQuote;
use App\Repository\NotificationRecipientsRepository;
use App\Workflow\MerchandiseTypeCatalog;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Manda la solicitud de cotización al consolidador de carga (XCF) — mismos
 * destinatarios que las instrucciones de entrega (ver ConsolidatorMailer):
 * comparten el mismo contacto en XCF, asi que se reusan las mismas claves de
 * NotificationRecipients en vez de duplicar la configuracion en /admin.
 *
 * A diferencia de las instrucciones, no adjunta el pedimento: la cotización
 * se pide antes de que el pedimento se pague, asi que normalmente todavia no
 * hay "Pedimento completo" que anexar.
 */
final class ConsolidatorQuoteMailer
{
    /**
     * Botón de pruebas de DashboardConsolidatorQuotes::create() — mismo
     * espíritu que ConsolidatorMailer::TEST_RECIPIENT.
     */
    public const TEST_RECIPIENT = 'carlosceptile@gmail.com';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        private readonly NotificationRecipientsRepository $notificationRecipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    /**
     * $test manda solo a TEST_RECIPIENT, ignorando NotificationRecipients y
     * los destinatarios dinámicos — para probar sin arriesgar mandarle nada a
     * XCF ni exponerle a un correo externo la cc interna (ejecutivo/cliente).
     */
    public function notify(ConsolidatorQuote $quote, bool $test = false): void
    {
        $import = $quote->getReference();
        $company = $import->getIdCompany();

        $to = $test ? [self::TEST_RECIPIENT] : $this->notificationRecipients->emailsFor(ConsolidatorMailer::TO_KEY);
        $cc = $test ? [] : $this->dedupe(array_merge(
            $this->notificationRecipients->emailsFor(ConsolidatorMailer::CC_KEY),
            array_filter([$quote->getCreatedBy()?->getEmail()]),
            $this->recipients->clientEmails($import),
        ));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf(
                '%sCOTIZACION // %s // Mercancía: %s // Ref: %s // Ped.: %s',
                $test ? '[PRUEBA] ' : '',
                $company->getName(),
                $quote->getDescripcion(),
                $import->getAgencyReference(),
                $import->getImportNumber(),
            ))
            ->htmlTemplate('emails/consolidator_quote.html.twig')
            ->context(['quote' => $quote, 'import' => $import, 'merchandiseTypes' => MerchandiseTypeCatalog::LABELS])
            ->to(...$to);

        if ($cc !== []) {
            $email->cc(...$cc);
        }

        $this->mailer->send($email);
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
