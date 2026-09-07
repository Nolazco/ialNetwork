<?php

namespace App\Notification;

use App\Entity\User;
use App\Repository\NotificationRecipientsRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa a los administradores en cuanto alguien se registra (ver
 * UserManagement::create()), para que se enteren de la cuenta nueva sin
 * tener que entrar a /dashboard/usuarios a revisar.
 */
final class NewUserMailer
{
    public const TO_KEY = 'new_user_to';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly NotificationRecipientsRepository $notificationRecipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notify(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Nuevo usuario registrado // %s', $user->getEmail()))
            ->htmlTemplate('emails/new_user_registered.html.twig')
            ->context(['user' => $user])
            ->to(...$this->notificationRecipients->emailsFor(self::TO_KEY));

        $this->mailer->send($email);
    }
}
