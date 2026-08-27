<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notify(ImportRequest $import): void
    {
        $to = $this->clientEmails($import);
        $cc = $this->executiveEmails();

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
     * @return list<string>
     */
    private function clientEmails(ImportRequest $import): array
    {
        $emails = [];

        foreach ($import->getIdCompany()->getAssociateds() as $associated) {
            if ($associated->isApproved() && $associated->getIdClient()?->getEmail()) {
                $emails[$associated->getIdClient()->getEmail()] = true;
            }
        }

        return array_keys($emails);
    }

    /**
     * @return list<string>
     */
    private function executiveEmails(): array
    {
        $emails = [];

        foreach ($this->entityManager->getRepository(User::class)->findAll() as $user) {
            // ROLE_ADMIN hereda ROLE_EXECUTIVE (role_hierarchy en
            // security.yaml), pero getRoles() del entity no resuelve la
            // jerarquia, asi que se comprueban ambos explicitamente.
            $roles = $user->getRoles();

            if ((in_array('ROLE_EXECUTIVE', $roles, true) || in_array('ROLE_ADMIN', $roles, true)) && $user->getEmail()) {
                $emails[$user->getEmail()] = true;
            }
        }

        return array_keys($emails);
    }
}
