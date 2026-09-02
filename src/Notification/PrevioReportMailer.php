<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Entity\PrevioReport;
use App\Repository\NotificationRecipientsRepository;
use App\Service\UploadPath;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Manda el reporte de previo generado: al cliente real afiliado a la empresa
 * y a los ejecutivos (igual que ModuladoMailer), más un puñado de
 * direcciones fijas heredadas del sistema anterior (previos.html/prev.php)
 * que se decidió conservar explícitamente en vez de reemplazar por completo
 * con la resolución dinámica.
 */
final class PrevioReportMailer
{
    /**
     * Claves de NotificationRecipients (editable desde /admin). Públicas:
     * LegacyPrevioMailer las reutiliza para no duplicar el string.
     */
    public const TO_KEY = 'previo_to';
    public const CC_KEY = 'previo_cc';

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
     * @return list<string>
     */
    public function resolveTo(ImportRequest $import): array
    {
        return $this->dedupe(array_merge(
            $this->recipients->clientEmails($import),
            $this->notificationRecipients->emailsFor(self::TO_KEY),
        ));
    }

    /**
     * Vista previa del cc para el formulario, sin saber todavía quién va a
     * quedar como autor del reporte (eso se agrega en notify()).
     *
     * @return list<string>
     */
    public function resolveCc(): array
    {
        return $this->dedupe(array_merge(
            $this->recipients->executiveEmails(),
            $this->notificationRecipients->emailsFor(self::CC_KEY),
        ));
    }

    public function notify(PrevioReport $previo): void
    {
        $import = $previo->getReference();
        $to = $this->resolveTo($import);
        $cc = $this->dedupe(array_merge(
            $this->resolveCc(),
            array_filter([$previo->getCreatedBy()?->getEmail()]),
        ));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Reporte de previo %s', $import->getClientReference()))
            ->htmlTemplate('emails/previo.html.twig')
            ->context(['import' => $import, 'previo' => $previo])
            ->to(...$to);

        if ($cc !== []) {
            $email->cc(...$cc);
        }

        $pdfPath = $previo->getPdfRoute() ? $this->uploadPath->resolve($previo->getPdfRoute()) : null;

        if ($pdfPath && is_file($pdfPath)) {
            $email->attachFromPath(
                $pdfPath,
                sprintf('reporte-previo-%s.pdf', $import->getClientReference())
            );
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
