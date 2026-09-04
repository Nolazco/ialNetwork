<?php

namespace App\Notification;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa al usuario cuando el administrador resuelve su cuenta nueva (ver
 * DashboardUsers::verifyUser() / denyUser()) — antes de esto solo se
 * enteraba si intentaba iniciar sesión y le fallaba o le funcionaba, sin
 * ninguna notificación de por medio.
 */
final class UserStatusMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notifyApproved(User $user): void
    {
        $this->send($user, true);
    }

    public function notifyRejected(User $user): void
    {
        $this->send($user, false);
    }

    private function send(User $user, bool $approved): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to((string) $user->getEmail())
            ->subject($approved ? 'Tu cuenta fue autorizada — IAL Network' : 'Tu cuenta fue rechazada — IAL Network')
            ->htmlTemplate('emails/user_status.html.twig')
            ->context(['user' => $user, 'approved' => $approved]);

        $this->mailer->send($email);
    }
}
