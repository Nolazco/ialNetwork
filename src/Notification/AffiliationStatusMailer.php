<?php

namespace App\Notification;

use App\Entity\Associated;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa al cliente cuando el ejecutivo resuelve su solicitud de afiliación a
 * una empresa (ver DashboardAffiliations::decide()) — de otro modo no se
 * entera de que ya puede ver los expedientes de esa empresa (o de que se la
 * rechazaron) hasta que vuelve a entrar a revisar.
 */
final class AffiliationStatusMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notify(Associated $association): void
    {
        $approved = $association->isApproved();

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to((string) $association->getIdClient()->getEmail())
            ->subject($approved
                ? sprintf('Tu afiliación a %s fue autorizada — IAL Network', $association->getIdCompany()->getName())
                : sprintf('Tu afiliación a %s fue rechazada — IAL Network', $association->getIdCompany()->getName()))
            ->htmlTemplate('emails/affiliation_status.html.twig')
            ->context(['association' => $association, 'approved' => $approved]);

        $this->mailer->send($email);
    }
}
