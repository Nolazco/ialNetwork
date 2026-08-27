<?php

namespace App\Notification;

use App\Entity\LegacyClassificationRequest;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Mismo destinatario fijo que ClassificationMailer, pero sin Company ni User
 * reales detrás: la solicitud llegó por el puente público /legacy/clasificacion.
 */
final class LegacyClassificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notify(LegacyClassificationRequest $request): void
    {
        $cc = $this->dedupe(array_merge(
            [$request->getRequesterEmail()],
            $this->recipients->executiveEmails(),
        ));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Solicitud de Clasificación de Mercancía // %s // %s', $request->getMerchandiseName(), $request->getCompanyName()))
            ->htmlTemplate('emails/legacyClassification.html.twig')
            ->context(['request' => $request])
            ->to(...ClassificationMailer::CLASSIFIERS)
            ->cc(...$cc);

        foreach ($request->getAttachments() as $attachment) {
            if (is_file($attachment['ruta'])) {
                $email->attachFromPath($attachment['ruta'], $attachment['nombre']);
            }
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
