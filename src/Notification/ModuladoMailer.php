<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa que un expediente llegó a Modulado: a los clientes afiliados a la
 * empresa (destinatarios) y a todos los ejecutivos en copia, porque los
 * expedientes rotan entre ellos.
 */
final class ModuladoMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notify(ImportRequest $import): void
    {
        $to = $this->recipients->clientEmails($import);
        $cc = $this->recipients->executiveEmails();

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
}
