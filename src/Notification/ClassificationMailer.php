<?php

namespace App\Notification;

use App\Entity\ClassificationRequest;
use App\Repository\NotificationRecipientsRepository;
use App\Service\UploadPath;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Manda la solicitud al equipo de clasificadores: contestan desde su propio
 * correo, la app no hace seguimiento de la respuesta.
 */
final class ClassificationMailer
{
    /**
     * Claves de NotificationRecipients (editable desde /admin). Públicas:
     * LegacyClassificationMailer las reutiliza para no duplicar el string.
     */
    public const TO_KEY = 'classification_to';
    public const CC_KEY = 'classification_cc';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        private readonly NotificationRecipientsRepository $notificationRecipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        private readonly UploadPath $uploadPath,
    ) {
    }

    public function notify(ClassificationRequest $request): void
    {
        $cc = $this->dedupe(array_merge(
            [$request->getRequestedBy()->getEmail()],
            $this->recipients->executiveEmails(),
            array_filter([$request->getCompany()->getClassificationContactEmail()]),
            $this->notificationRecipients->emailsFor(self::CC_KEY),
        ));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Solicitud de Clasificación de Mercancía // %s // %s', $request->getMerchandiseName(), $request->getCompany()->getName()))
            ->htmlTemplate('emails/classification.html.twig')
            ->context(['request' => $request])
            ->to(...$this->notificationRecipients->emailsFor(self::TO_KEY))
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
