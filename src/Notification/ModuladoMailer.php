<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Repository\NotificationRecipientsRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa que un expediente llegó a Modulado: a los clientes afiliados a la
 * empresa (destinatarios) y a todos los ejecutivos en copia, porque los
 * expedientes rotan entre ellos — más lo que se agregue desde /admin
 * (ver NotificationRecipients), sin fijos heredados: antes de esto, esta
 * alerta era 100% dinámica.
 */
final class ModuladoMailer
{
    public const TO_KEY = 'modulado_to';
    public const CC_KEY = 'modulado_cc';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        private readonly NotificationRecipientsRepository $notificationRecipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notify(ImportRequest $import): void
    {
        $to = $this->dedupe(array_merge(
            $this->recipients->clientEmails($import),
            $this->notificationRecipients->emailsFor(self::TO_KEY),
        ));
        $cc = $this->dedupe(array_merge(
            $this->recipients->executiveEmails(),
            $this->notificationRecipients->emailsFor(self::CC_KEY),
        ));

        if ($to === [] && $cc === []) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Expediente %s modulado', $import->getClientReference()))
            ->htmlTemplate('emails/modulado.html.twig')
            ->context(['import' => $import]);

        if ($to !== []) {
            $email->to(...$to);
        }

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
