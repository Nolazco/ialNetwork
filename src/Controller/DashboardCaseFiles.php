<?php

namespace App\Controller;

use App\Entity\ImportRequest;
use App\Entity\Operation;
use App\Entity\User;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\OperationCatalog;
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
        ]);
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
