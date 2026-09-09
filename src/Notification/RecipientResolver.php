<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A quien le toca enterarse de lo que pasa con un expediente: los clientes
 * afiliados a la empresa (aprobados), los correos de contacto que la empresa
 * haya agregado sin necesidad de cuenta (ver Company::$alertContactEmails) y
 * todos los ejecutivos, porque los expedientes rotan entre ellos.
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

        foreach ($import->getIdCompany()->getAlertContactEmails() as $contactEmail) {
            $emails[$contactEmail] = true;
        }

        return array_keys($emails);
    }

    /**
     * Los correos de contacto del forwarder al que viene consignado el
     * expediente, o una lista vacia si viene consignado al cliente directo.
     *
     * @return list<string>
     */
    public function forwarderEmails(ImportRequest $import): array
    {
        return array_keys(array_flip($import->getForwarder()?->getContactEmails() ?? []));
    }

    /**
     * Si se manda $aduana, un ejecutivo con aduanas asignadas solo entra
     * cuando esa aduana esta entre las suyas — el admin ve todo sin importar
     * la aduana, y un ejecutivo sin ninguna aduana asignada tambien ve todo
     * (para no dejar de avisarle a alguien que todavia no se configuro). Sin
     * $aduana (llamadas que no tienen un expediente detras, como las
     * solicitudes de clasificacion) se regresan todos, igual que antes.
     *
     * @return list<string>
     */
    public function executiveEmails(?string $aduana = null): array
    {
        $emails = [];

        foreach ($this->entityManager->getRepository(User::class)->findAll() as $user) {
            // ROLE_ADMIN hereda ROLE_EXECUTIVE (role_hierarchy en
            // security.yaml), pero getRoles() del entity no resuelve la
            // jerarquia, asi que se comprueban ambos explicitamente.
            $roles = $user->getRoles();
            $isAdmin = in_array('ROLE_ADMIN', $roles, true);
            $isExecutive = in_array('ROLE_EXECUTIVE', $roles, true);

            if ((!$isAdmin && !$isExecutive) || !$user->getEmail()) {
                continue;
            }

            if (!$isAdmin && $aduana !== null) {
                $assigned = $user->getAduanas();

                if ($assigned !== [] && !in_array($aduana, $assigned, true)) {
                    continue;
                }
            }

            $emails[$user->getEmail()] = true;
        }

        return array_keys($emails);
    }
}
