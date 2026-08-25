<?php

namespace App\Controller;

use App\Entity\Container;
use App\Entity\ContainerYard;
use App\Entity\Delivery;
use App\Entity\EmptyReturn;
use App\Entity\FreightHauler;
use App\Entity\User;
use App\Workflow\EmptyReturnCatalog;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\TransportCoordinator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;
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
        private readonly EmptyReturnCatalog $returnCatalog,
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

        // Cuantos vacios le quedan por devolver a cada despacho, para saber si
        // hay que ofrecer el boton.
        $pendingReturns = [];

        foreach ($deliveries as $delivery) {
            $pendingReturns[$delivery->getId()] = count($this->coordinator->containersPendingReturnFor($delivery));
        }

        return $this->render('/dashboard/deliveries.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'deliveries' => $deliveries,
            'hauler' => $hauler,
            'isHauler' => !$this->isGranted('ROLE_EXECUTIVE'),
            'directions' => ImportRequestWorkflow::DIRECTIONS,
            'pendingReturns' => $pendingReturns,
        ]);
    }

    /**
     * Formulario de devolucion de vacios de un despacho.
     */
    #[Route('/dashboard/despachos/{id}/vacios', name: 'delivery_empty_returns', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function emptyReturns(#[MapEntity(id: 'id')] Delivery $delivery): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->ownsDelivery($delivery)) {
            throw $this->createAccessDeniedException('Ese despacho no está asignado a tu cuenta.');
        }

        return $this->render('/dashboard/emptyReturns.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'delivery' => $delivery,
            'pending' => $this->coordinator->containersPendingReturnFor($delivery),
            'returned' => $this->returnsFor($delivery),
            'yards' => $this->entityManager->getRepository(ContainerYard::class)->findBy([], ['name' => 'ASC']),
            'returnTypes' => EmptyReturnCatalog::TYPES,
        ]);
    }

    /**
     * Registra la devolucion de un contenedor al patio.
     */
    #[Route('/dashboard/despachos/{id}/vacios/{container}', name: 'delivery_empty_return', requirements: ['id' => '\d+', 'container' => '\d+'], methods: ['POST'])]
    public function registerEmptyReturn(
        #[MapEntity(id: 'id')] Delivery $delivery,
        #[MapEntity(id: 'container')] Container $container,
        Request $r,
        SluggerInterface $slugger,
    ): Response {
        if (!$this->isCsrfTokenValid('delivery_empty_return', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
        }

        if (!$this->ownsDelivery($delivery)) {
            $this->addFlash('error', 'Ese despacho no está asignado a tu cuenta.');

            return $this->redirectToRoute('deliveries');
        }

        // El contenedor tiene que ser uno de los que este camion todavia debe.
        if (!in_array($container, $this->coordinator->containersPendingReturnFor($delivery), true)) {
            $this->addFlash('error', 'Ese contenedor no está pendiente de devolución en este despacho.');

            return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
        }

        $type = $r->request->get('type');

        if (!$this->returnCatalog->isValid($type)) {
            $this->addFlash('error', 'Selecciona el tipo de devolución.');

            return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
        }

        $yard = $this->entityManager->getRepository(ContainerYard::class)->find($r->request->get('yard'));
        $eir = trim((string) $r->request->get('eir'));
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('date'));

        if (!$yard || $eir === '' || !$date) {
            $this->addFlash('error', 'Patio, folio del EIR y fecha son obligatorios.');

            return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
        }

        $return = new EmptyReturn();
        // addEmptyReturn en vez de setReference: hay que meterlo tambien en la
        // coleccion del expediente, porque confirmEmptyReturn la recorre para
        // saber si ya volvieron todos los contenedores y todavia no hubo flush.
        $delivery->getReference()->addEmptyReturn($return);
        $return->setTransport($delivery->getTransport());
        $return->setContainer($container);
        $return->setYard($yard);
        $return->setType($type);
        $return->setEir($eir);
        $return->setDate($date->setTime(0, 0));

        if ($route = $this->storeEir($r, $delivery, $container, $slugger)) {
            $return->setEirRoute($route);
        }

        $this->entityManager->persist($return);

        $newStatus = $this->coordinator->confirmEmptyReturn($return);
        $this->entityManager->flush();

        if ($newStatus) {
            $this->addFlash('success', sprintf('Vacío %s devuelto. El expediente pasó a "%s".', $container->getNum(), $newStatus));

            return $this->redirectToRoute('deliveries');
        }

        $this->addFlash('success', sprintf('Vacío %s devuelto.', $container->getNum()));

        return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
    }

    /**
     * Guarda el EIR escaneado y devuelve su ruta, o null si no venia archivo.
     */
    private function storeEir(Request $r, Delivery $delivery, Container $container, SluggerInterface $slugger): ?string
    {
        $file = $r->files->get('eirFile');

        if (!$file || !$file->isValid()) {
            return null;
        }

        $folder = 'uploads/eir/'.$delivery->getReference()->getId();

        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            return null;
        }

        $name = $slugger->slug($container->getNum()).'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($folder, $name);
        } catch (FileException) {
            return null;
        }

        return $folder.'/'.$name;
    }

    /**
     * @return list<EmptyReturn>
     */
    private function returnsFor(Delivery $delivery): array
    {
        $returns = [];

        foreach ($delivery->getReference()->getEmptyReturns() as $return) {
            if ($delivery->getContainers()->contains($return->getContainer())) {
                $returns[] = $return;
            }
        }

        return $returns;
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
