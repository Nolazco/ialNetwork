<?php

namespace App\Controller;

use App\Entity\Delivery;
use App\Entity\Container;
use App\Entity\FreightHauler;
use App\Entity\ImportRequest;
use App\Entity\Operation;
use App\Entity\User;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\OperationCatalog;
use App\Workflow\TransportCoordinator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * El expediente visto por el ejecutivo: alta del pedimento, avance de estados y
 * registro de maniobras.
 *
 * Es el paso 2 del flujo. El cliente solo puede dar el aviso; a partir de ahi
 * todo lo mueve la agencia, por eso la clase entera exige ROLE_EXECUTIVE
 * (ROLE_ADMIN lo hereda, ver role_hierarchy en security.yaml).
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardCaseFiles extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportRequestWorkflow $workflow,
        private readonly OperationCatalog $catalog,
        private readonly TransportCoordinator $transport,
    ) {
    }

    #[Route('/dashboard/pedimentos/expediente/{id}', name: 'case_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] ImportRequest $import): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/caseFile.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'import' => $import,
            'sequence' => $this->workflow->sequenceFor($import),
            'completed' => $this->workflow->completedStatuses($import),
            'nextStatuses' => $this->workflow->nextStatuses($import),
            'progress' => $this->workflow->progress($import),
            'directions' => ImportRequestWorkflow::DIRECTIONS,
            'types' => ImportRequestWorkflow::TYPES,
            'maniobras' => OperationCatalog::COMMON,
            'maniobraOther' => OperationCatalog::OTHER,
            'operations' => $this->entityManager->getRepository(Operation::class)
                ->findBy(['reference' => $import], ['date' => 'ASC', 'id' => 'ASC']),
            'canAssignTransport' => $this->workflow->canAssignTransport($import),
            'awaitsTransport' => $this->workflow->awaitsTransport($import),
            'haulers' => $this->entityManager->getRepository(FreightHauler::class)->findBy([], ['companyName' => 'ASC']),
            'unassignedContainers' => $this->transport->unassignedContainers($import),
            'maxContainers' => Delivery::MAX_CONTAINERS,
            'requiresEmptyReturn' => $this->workflow->requiresEmptyReturn($import),
            'containersPendingReturn' => $this->transport->containersPendingReturn($import),
        ]);
    }

    /**
     * Aviso al transporte: asigna un camion al expediente.
     *
     * En importacion el camion recoge en el recinto y lleva la mercancia al
     * cliente; en exportacion es al reves. En ambos casos un camion carga como
     * mucho dos contenedores, asi que un expediente contenerizado necesita
     * varios avisos.
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/transporte', name: 'case_file_transport', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assignTransport(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_transport', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$this->workflow->canAssignTransport($import)) {
            $this->addFlash('error', sprintf('El expediente no admite aviso al transporte estando en "%s".', $import->getStatus()));

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $hauler = $this->entityManager->getRepository(FreightHauler::class)->find($r->request->get('transport'));

        if (!$hauler) {
            $this->addFlash('error', 'Selecciona un transportista.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('date'));
        $hour = \DateTimeImmutable::createFromFormat('H:i', (string) $r->request->get('hour'));

        if (!$date || !$hour) {
            $this->addFlash('error', 'La fecha y la hora del despacho son obligatorias.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $delivery = new Delivery();
        $delivery->setReference($import);
        $delivery->setTransport($hauler);
        $delivery->setDate($date->setTime(0, 0));
        $delivery->setHour($hour);

        if ($import->getType() === ImportRequestWorkflow::TYPE_CONTAINER) {
            $available = [];

            foreach ($this->transport->unassignedContainers($import) as $container) {
                $available[$container->getId()] = $container;
            }

            $chosen = $r->request->all('containers');

            if (count($chosen) === 0) {
                $this->addFlash('error', 'Selecciona al menos un contenedor para este camión.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            if (count($chosen) > Delivery::MAX_CONTAINERS) {
                $this->addFlash('error', sprintf('Un camión no puede cargar más de %d contenedores.', Delivery::MAX_CONTAINERS));

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            foreach ($chosen as $containerId) {
                // Solo contenedores de este expediente que no viajen ya en otro
                // camion: la lista llega del formulario y no es de fiar.
                if (!isset($available[(int) $containerId])) {
                    $this->addFlash('error', 'Alguno de los contenedores ya está asignado o no pertenece al expediente.');

                    return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
                }

                $delivery->addContainer($available[(int) $containerId]);
            }
        }

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Se avisó a %s para el %s.', $hauler->getCompanyName(), $date->format('d/m/Y')));

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/transporte/{delivery}/cancelar', name: 'case_file_transport_cancel', requirements: ['id' => '\d+', 'delivery' => '\d+'], methods: ['POST'])]
    public function cancelTransport(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'delivery')] Delivery $delivery, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_transport_cancel', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($delivery->getReference() !== $import) {
            $this->addFlash('error', 'Ese despacho no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($delivery->isDeparted()) {
            $this->addFlash('error', 'No se puede cancelar un despacho que ya salió.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $this->entityManager->remove($delivery);
        $this->entityManager->flush();

        $this->addFlash('success', 'Aviso de transporte cancelado.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Alta del pedimento: captura la referencia interna y el numero de pedimento
     * que el cliente no puede conocer.
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/captura', name: 'case_file_capture', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function capture(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_capture', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $agencyReference = trim((string) $r->request->get('agencyReference'));
        $importNumber = trim((string) $r->request->get('importNumber'));

        if ($agencyReference === '' || $importNumber === '') {
            $this->addFlash('error', 'La referencia de la agencia y el número de pedimento son obligatorios.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $import->setAgencyReference($agencyReference);
        $import->setImportNumber($importNumber);

        // Dar de alta el pedimento es lo que saca al expediente de "Pendiente".
        // Si ya avanzo mas alla, esto es una correccion y no debe retroceder.
        if ($import->getStatus() === ImportRequestWorkflow::PENDING) {
            $import->setStatus(ImportRequestWorkflow::CAPTURED);
            $this->addFlash('success', 'Pedimento dado de alta. El expediente pasó a Capturado.');
        } else {
            $this->addFlash('success', 'Datos del pedimento actualizados.');
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Avanza el expediente al siguiente estado de su secuencia.
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/estatus', name: 'case_file_advance', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function advance(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_advance', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $status = (string) $r->request->get('status');

        // Solo se acepta un salto contemplado por la secuencia: nada de mandar
        // el expediente a un estado arbitrario desde el formulario.
        if (!$this->workflow->canTransitionTo($import, $status)) {
            $this->addFlash('error', sprintf('No se puede pasar de "%s" a "%s".', $import->getStatus(), $status));

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // "Vacío devuelto" lo alcanza el expediente cuando el transportista
        // registra el EIR de cada contenedor, no marcandolo a mano: de otro modo
        // el expediente cerraria sin el respaldo de los patios.
        if ($status === ImportRequestWorkflow::EMPTY_RETURNED) {
            $pending = $this->transport->containersPendingReturn($import);

            if ($pending !== []) {
                $this->addFlash('error', sprintf(
                    'Faltan %d contenedor(es) por devolver. El transportista debe registrar su EIR.',
                    count($pending)
                ));

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        $import->setStatus($status);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('El expediente pasó a "%s".', $status));

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Registra una maniobra sobre el expediente.
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/maniobra', name: 'case_file_operation', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addOperation(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_operation', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $type = $this->catalog->resolve($r->request->get('type'), $r->request->get('customType'));

        if ($type === null) {
            $this->addFlash('error', 'Selecciona una maniobra del catálogo o escribe una.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $date = $r->request->get('date');

        if (!$date || !($parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date))) {
            $this->addFlash('error', 'La fecha de la maniobra no es válida.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $operation = new Operation();
        $operation->setReference($import);
        $operation->setType($type);
        $operation->setDate($parsed->setTime(0, 0));

        $this->entityManager->persist($operation);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Maniobra "%s" registrada.', $type));

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/maniobra/{operation}/eliminar', name: 'case_file_operation_delete', requirements: ['id' => '\d+', 'operation' => '\d+'], methods: ['POST'])]
    public function deleteOperation(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'operation')] Operation $operation, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_operation_delete', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Evita borrar una maniobra de otro expediente manipulando la URL.
        if ($operation->getReference() !== $import) {
            $this->addFlash('error', 'Esa maniobra no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $this->entityManager->remove($operation);
        $this->entityManager->flush();

        $this->addFlash('success', 'Maniobra eliminada.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }
}
