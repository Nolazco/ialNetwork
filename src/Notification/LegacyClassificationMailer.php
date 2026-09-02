<?php

namespace App\Notification;

use App\Entity\LegacyClassificationRequest;
use App\Repository\NotificationRecipientsRepository;
use App\Service\UploadPath;
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
        private readonly NotificationRecipientsRepository $notificationRecipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        private readonly UploadPath $uploadPath,
    ) {
    }

    public function notify(LegacyClassificationRequest $request): void
    {
        $cc = $this->dedupe(array_merge(
            [$request->getRequesterEmail()],
            $this->recipients->executiveEmails(),
            $this->notificationRecipients->emailsFor(ClassificationMailer::CC_KEY),
        ));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Solicitud de Clasificación de Mercancía // %s // %s', $request->getMerchandiseName(), $request->getCompanyName()))
            ->htmlTemplate('emails/legacyClassification.html.twig')
            ->context(['request' => $request])
            ->to(...$this->notificationRecipients->emailsFor(ClassificationMailer::TO_KEY))
            ->cc(...$cc);

        foreach ($request->getAttachments() as $attachment) {
            $path = $this->uploadPath->resolve($attachment['ruta']);

            if (is_file($path)) {
                $email->attachFromPath($path, $attachment['nombre']);
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
