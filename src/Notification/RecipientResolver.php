<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A quien le toca enterarse de lo que pasa con un expediente: los correos de
 * trafico que la propia empresa configuro (ver Company::$trafico) y todos
 * los ejecutivos, porque los expedientes rotan entre ellos.
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
     * Correos de trafico que la propia empresa configuro (ver
     * Company::$trafico) — gente que necesita estar al tanto de la operacion
     * sin necesariamente intervenir en ella (contadores, representantes),
     * asi que no siempre tienen ni necesitan cuenta en el portal. Van
     * SIEMPRE en copia de los avisos del expediente, ademas de quien lo dio
     * de alta (ver clientEmails()) — no son alternativos entre si.
     *
     * @return list<string>
     */
    public function traficoEmails(ImportRequest $import): array
    {
        return $import->getIdCompany()->getTrafico();
    }

    /**
     * A quien de la empresa le llega el aviso de este expediente en
     * particular: el cliente que lo dio de alta (ver
     * ImportRequest::$createdBy) -- es su propia solicitud, no la de sus
     * compañeros -- MAS la lista de trafico de la empresa (ver
     * traficoEmails()), que son avisos de la empresa en general, no de una
     * persona. Los expedientes sin creador registrado (los de antes de ese
     * campo) solo llevan trafico, igual que antes de este cambio.
     *
     * @return list<string>
     */
    public function clientEmails(ImportRequest $import): array
    {
        $emails = $this->traficoEmails($import);
        $creatorEmail = $import->getCreatedBy()?->getEmail();

        if ($creatorEmail) {
            $emails[] = $creatorEmail;
        }

        return array_values(array_unique($emails));
    }

    /**
     * Mismo criterio que clientEmails() pero para WhatsApp: el numero propio
     * del cliente que dio de alta el expediente (ver User::$whatsapp,
     * capturado en "Mi perfil"), o el numero de la empresa (Company::$whatsapp)
     * como respaldo si no se conoce quien lo dio de alta o no ha capturado el
     * suyo.
     *
     * @return list<string>
     */
    public function clientWhatsapp(ImportRequest $import): array
    {
        $whatsapp = $import->getCreatedBy()?->getWhatsapp();

        return $whatsapp ? [$whatsapp] : $import->getIdCompany()->getWhatsapp();
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
