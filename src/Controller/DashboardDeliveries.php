<?php

namespace App\Controller;

use App\Entity\Delivery;
use App\Entity\FreightHauler;
use App\Entity\User;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\TransportCoordinator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Los despachos: el listado del transportista y sus confirmaciones.
 *
 * Es el paso 3 del flujo. Quien sabe cuando salio el camion y cuando entrego es
 * el transportista, asi que es el quien mueve el expediente a "En tránsito" y a
 * "Entregado" (o a "Ingresado" en exportacion), no el ejecutivo.
 *
 * La pantalla es cosa de la agencia y de los transportistas: un cliente no tiene
 * nada que ver aqui.
 */
#[IsGranted(new Expression('is_granted("ROLE_EXECUTIVE") or is_granted("ROLE_FH")'))]
class DashboardDeliveries extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TransportCoordinator $coordinator,
        private readonly ImportRequestWorkflow $workflow,
    ) {
    }

    #[Route('/dashboard/despachos', name: 'deliveries', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $repository = $this->entityManager->getRepository(Delivery::class);

        // El transportista solo ve lo suyo; la agencia ve todo.
        if ($this->isGranted('ROLE_EXECUTIVE')) {
            $deliveries = $repository->findBy([], ['date' => 'ASC', 'hour' => 'ASC']);
            $hauler = null;
        } else {
            $hauler = $this->haulerFor($user);
            $deliveries = $hauler
                ? $repository->findBy(['transport' => $hauler], ['date' => 'ASC', 'hour' => 'ASC'])
                : [];
        }

        return $this->render('/dashboard/deliveries.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'deliveries' => $deliveries,
            'hauler' => $hauler,
            'isHauler' => !$this->isGranted('ROLE_EXECUTIVE'),
            'directions' => ImportRequestWorkflow::DIRECTIONS,
        ]);
    }

    #[Route('/dashboard/despachos/{id}/salida', name: 'delivery_departure', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirmDeparture(#[MapEntity(id: 'id')] Delivery $delivery, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('delivery_departure', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('deliveries');
        }

        if (!$this->ownsDelivery($delivery)) {
            $this->addFlash('error', 'Ese despacho no está asignado a tu cuenta.');

            return $this->redirectToRoute('deliveries');
        }

        if ($delivery->isDeparted()) {
            $this->addFlash('warning', 'Ese despacho ya tenía la salida confirmada.');

            return $this->redirectToRoute('deliveries');
        }

        $newStatus = $this->coordinator->confirmDeparture($delivery, new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', $newStatus
            ? sprintf('Salida confirmada. El expediente pasó a "%s".', $newStatus)
            : 'Salida confirmada.');

        return $this->redirectToRoute('deliveries');
    }

    #[Route('/dashboard/despachos/{id}/entrega', name: 'delivery_arrival', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirmArrival(#[MapEntity(id: 'id')] Delivery $delivery, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('delivery_arrival', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('deliveries');
        }

        if (!$this->ownsDelivery($delivery)) {
            $this->addFlash('error', 'Ese despacho no está asignado a tu cuenta.');

            return $this->redirectToRoute('deliveries');
        }

        if ($delivery->isDelivered()) {
            $this->addFlash('warning', 'Ese despacho ya tenía la entrega confirmada.');

            return $this->redirectToRoute('deliveries');
        }

        // En importacion la entrega presupone que el camion salio; si el
        // transportista se salto ese paso, se registra ahora.
        if (!$delivery->isDeparted() && $this->workflow->departureStatus($delivery->getReference()) !== null) {
            $this->coordinator->confirmDeparture($delivery, new \DateTimeImmutable());
        }

        $newStatus = $this->coordinator->confirmArrival($delivery, new \DateTimeImmutable());
        $this->entityManager->flush();

        if ($newStatus) {
            $this->addFlash('success', sprintf('Entrega confirmada. El expediente pasó a "%s".', $newStatus));
        } else {
            $pending = $this->coordinator->pendingDeliveries($delivery->getReference());
            $this->addFlash('success', sprintf(
                'Entrega confirmada. Faltan %d despacho(s) por llegar para cerrar el expediente.',
                $pending
            ));
        }

        return $this->redirectToRoute('deliveries');
    }

    /**
     * Salida y entrega las confirma quien las hizo: el transportista asignado.
     * La agencia ve el listado completo, pero en modo lectura.
     */
    private function ownsDelivery(Delivery $delivery): bool
    {
        /** @var User $user */
        $user = $this->getUser();
        $hauler = $this->haulerFor($user);

        return $hauler !== null && $delivery->getTransport() === $hauler;
    }

    private function haulerFor(User $user): ?FreightHauler
    {
        return $this->entityManager->getRepository(FreightHauler::class)->findOneBy(['id_user' => $user]);
    }
}
