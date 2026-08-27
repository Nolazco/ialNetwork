<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A quien le toca enterarse de lo que pasa con un expediente: los clientes
 * afiliados a la empresa (aprobados) y todos los ejecutivos, porque los
 * expedientes rotan entre ellos.
 *
 * Compartido entre los distintos mailers (ModuladoMailer, PrevioReportMailer)
 * para no repetir la misma resolucion en cada uno.
 */
final class RecipientResolver
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<string>
     */
    public function clientEmails(ImportRequest $import): array
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
    public function executiveEmails(): array
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
