<?php

namespace App\Controller;

use App\Entity\Container;
use App\Entity\Delivery;
use App\Entity\EmptyReturn;
use App\Entity\FreightHauler;
use App\Entity\ImportRequest;
use App\Entity\InspectionPoint;
use App\Entity\LocalTransfer;
use App\Entity\User;
use App\Notification\ForwarderMailer;
use App\Service\UploadPath;
use App\Workflow\DeliveryFailureCatalog;
use App\Workflow\EmptyReturnCatalog;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\LocalTransferPlaceCatalog;
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
        private readonly DeliveryFailureCatalog $failureCatalog,
        private readonly ForwarderMailer $forwarderMailer,
        private readonly LocalTransferPlaceCatalog $placeCatalog,
        private readonly UploadPath $uploadPath,
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

            if ($hauler) {
                // Ademas de lo que le toca entregar, un transportista puede
                // tener asignada solo la devolucion de vacios de un despacho
                // que entrego alguien mas (ver Delivery::$returnTransport).
                $delivered = $repository->findBy(['transport' => $hauler]);
                $returning = $repository->findBy(['returnTransport' => $hauler]);

                $byId = [];

                foreach (array_merge($delivered, $returning) as $delivery) {
                    $byId[$delivery->getId()] = $delivery;
                }

                $deliveries = array_values($byId);
                usort($deliveries, static fn (Delivery $a, Delivery $b): int => [$a->getDate(), $a->getHour()] <=> [$b->getDate(), $b->getHour()]);
            } else {
                $deliveries = [];
            }
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
            'failureReasons' => DeliveryFailureCatalog::COMMON,
            'placeTypes' => LocalTransferPlaceCatalog::TYPES,
            'placeFree' => LocalTransferPlaceCatalog::FREE_PLACE,
            'placeInspection' => LocalTransferPlaceCatalog::INSPECTION_POINT,
            'inspectionPoints' => $this->entityManager->getRepository(InspectionPoint::class)->findBy([], ['name' => 'ASC']),
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

        if (!$this->ownsReturn($delivery)) {
            throw $this->createAccessDeniedException('Ese despacho no está asignado a tu cuenta.');
        }

        $pending = $this->coordinator->containersPendingReturnFor($delivery);
        $containerOwners = [];
        $pendingReturns = [];

        foreach ($pending as $container) {
            $containerOwners[$container->getId()] = $this->ownerFor($delivery, $container);
            $pendingReturns[$container->getId()] = $this->coordinator->emptyReturnFor($container);
        }

        return $this->render('/dashboard/emptyReturns.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'delivery' => $delivery,
            'pending' => $pending,
            'containerOwners' => $containerOwners,
            'pendingReturns' => $pendingReturns,
            'returned' => $this->returnsFor($delivery),
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

        if (!$this->ownsReturn($delivery)) {
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

        $eir = trim((string) $r->request->get('eir'));
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('date'));

        if ($eir === '' || !$date) {
            $this->addFlash('error', 'Folio del EIR y fecha son obligatorios.');

            return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
        }

        $owner = $this->ownerFor($delivery, $container);

        if ($owner === null) {
            $this->addFlash('error', 'Ese contenedor no tiene un expediente único en este despacho; corrígelo antes de registrar el EIR.');

            return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
        }

        // Ojo: el candado es sobre ESTE despacho, no sobre el estatus
        // agregado del expediente. Un contenedor puede llegar en un camion
        // distinto al de otro del mismo expediente (despacho dividido o
        // traspaso) — el vacio de uno ya entregado se puede devolver aunque
        // el expediente en conjunto siga "En tránsito" esperando al otro.
        // confirmEmptyReturn() ya se encarga de no avanzar el estatus del
        // expediente hasta que todos sus contenedores esten devueltos.
        if (!$delivery->isDelivered()) {
            $this->addFlash('error', 'Este despacho todavía no tiene la entrega confirmada; no se puede registrar la devolución de vacíos.');

            return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
        }

        // El patio y la papeleta ya los fijo el ejecutivo al programar la
        // cita (ver DashboardCaseFiles::scheduleEmptyReturn()); el candado de
        // arriba (containersPendingReturnFor) ya garantiza que este registro
        // existe y sigue sin ejecutarse.
        $return = $this->coordinator->emptyReturnFor($container);

        $return->setTransport($delivery->getReturnTransport() ?? $delivery->getTransport());
        $return->setType($type);
        $return->setEir($eir);
        $return->setDate($date->setTime(0, 0));

        if ($route = $this->storeEir($r, $delivery, $container, $slugger)) {
            // Puede reemplazar uno que el ejecutivo ya hubiera adjuntado (ver
            // DashboardCaseFiles::uploadEmptyReturnEir()).
            $oldPath = $return->getEirRoute() ? $this->uploadPath->resolve($return->getEirRoute()) : null;

            if ($oldPath && is_file($oldPath)) {
                unlink($oldPath);
            }

            $return->setEirRoute($route);
        }

        $newStatus = $this->coordinator->confirmEmptyReturn($return);
        $this->entityManager->flush();

        if ($owner->getForwarder() !== null) {
            $this->forwarderMailer->notifyEmptyReturn($return, $owner);
        }

        if ($newStatus) {
            $this->addFlash('success', sprintf('Vacío %s devuelto. El expediente pasó a "%s".', $container->getNum(), $newStatus));

            return $this->redirectToRoute('deliveries');
        }

        $this->addFlash('success', sprintf('Vacío %s devuelto.', $container->getNum()));

        return $this->redirectToRoute('delivery_empty_returns', ['id' => $delivery->getId()]);
    }

    /**
     * Un despacho compartido puede cargar contenedores de mas de un
     * expediente: el dueño de un contenedor concreto es la interseccion
     * entre los expedientes del despacho y los del propio contenedor, no
     * "el" expediente del despacho a secas. Devuelve null si esa
     * interseccion no da exactamente uno (dato inconsistente, no se adivina).
     */
    private function ownerFor(Delivery $delivery, Container $container): ?ImportRequest
    {
        $owners = [];

        foreach ($delivery->getReferences() as $candidate) {
            if ($container->getReference()->contains($candidate)) {
                $owners[] = $candidate;
            }
        }

        return count($owners) === 1 ? $owners[0] : null;
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

        $route = 'uploads/eir/'.$delivery->getId();
        $folder = $this->uploadPath->resolve($route);

        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            return null;
        }

        $name = $slugger->slug($container->getNum()).'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($folder, $name);
        } catch (FileException) {
            return null;
        }

        return $route.'/'.$name;
    }

    /**
     * Guarda la prueba de entrega, o null si no venia archivo. Es opcional a
     * proposito: preferible tenerla, pero no imprescindible para cerrar el
     * despacho.
     */
    private function storeProof(Request $r, Delivery $delivery, SluggerInterface $slugger): ?string
    {
        $file = $r->files->get('proof');

        if (!$file || !$file->isValid()) {
            return null;
        }

        $route = 'uploads/entregas/'.$delivery->getId();
        $folder = $this->uploadPath->resolve($route);

        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            return null;
        }

        $name = $slugger->slug('entrega-'.$delivery->getId()).'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($folder, $name);
        } catch (FileException) {
            return null;
        }

        return $route.'/'.$name;
    }

    /**
     * @return list<EmptyReturn>
     */
    private function returnsFor(Delivery $delivery): array
    {
        $returns = [];

        foreach ($delivery->getReferences() as $request) {
            foreach ($request->getEmptyReturns() as $return) {
                if ($return->isExecuted() && $delivery->getContainers()->contains($return->getContainer())) {
                    $returns[] = $return;
                }
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

        if ($delivery->isFailed()) {
            $this->addFlash('error', 'Ese despacho está marcado como fallido, no se puede confirmar la salida.');

            return $this->redirectToRoute('deliveries');
        }

        $blockers = $this->coordinator->departureBlockers($delivery);

        if ($blockers !== []) {
            $this->addFlash('error', sprintf('No se puede confirmar la salida: %s', implode(' ', $blockers)));

            return $this->redirectToRoute('deliveries');
        }

        $moved = $this->coordinator->confirmDeparture($delivery, new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', $moved !== []
            ? sprintf('Salida confirmada. %s', $this->describeMoves($moved))
            : 'Salida confirmada.');

        return $this->redirectToRoute('deliveries');
    }

    #[Route('/dashboard/despachos/{id}/entrega', name: 'delivery_arrival', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirmArrival(#[MapEntity(id: 'id')] Delivery $delivery, Request $r, SluggerInterface $slugger): Response
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

        if ($delivery->isFailed()) {
            $this->addFlash('error', 'Ese despacho está marcado como fallido, no se puede confirmar la entrega.');

            return $this->redirectToRoute('deliveries');
        }

        $blockers = $this->coordinator->arrivalBlockers($delivery);

        if ($blockers !== []) {
            $this->addFlash('error', sprintf('No se puede confirmar la entrega: %s', implode(' ', $blockers)));

            return $this->redirectToRoute('deliveries');
        }

        // En importacion la entrega presupone que el camion salio; si el
        // transportista se salto ese paso, se registra ahora. Los expedientes
        // de un mismo despacho siempre comparten direccion (se valida al
        // agendar la cita), asi que basta con mirar el primero.
        $firstReference = $delivery->getReferences()->first() ?: null;

        if (!$delivery->isDeparted() && $firstReference && $this->workflow->departureStatus($firstReference) !== null) {
            $this->coordinator->confirmDeparture($delivery, new \DateTimeImmutable());
        }

        if ($route = $this->storeProof($r, $delivery, $slugger)) {
            $delivery->setProofRoute($route);
            $delivery->setProofUploadedAt(new \DateTimeImmutable());
        }

        $moved = $this->coordinator->confirmArrival($delivery, new \DateTimeImmutable());
        $this->entityManager->flush();

        if ($moved !== []) {
            $this->addFlash('success', sprintf('Entrega confirmada. %s', $this->describeMoves($moved)));
        } else {
            $parts = [];

            foreach ($delivery->getReferences() as $request) {
                $pending = $this->coordinator->pendingDeliveries($request);

                if ($pending > 0) {
                    $parts[] = sprintf('%s: faltan %d despacho(s) por llegar', $request->getClientReference(), $pending);
                }
            }

            $this->addFlash('success', $parts !== []
                ? sprintf('Entrega confirmada. %s.', implode('; ', $parts))
                : 'Entrega confirmada.');
        }

        return $this->redirectToRoute('deliveries');
    }

    /**
     * El despacho no se pudo realizar (la autoridad rechazó la carga, la
     * unidad no cumplió requisitos...). A diferencia de salida/entrega, aquí
     * sí puede reportar el ejecutivo además del transportista: el aviso a
     * veces llega por teléfono, no por la app.
     */
    #[Route('/dashboard/despachos/{id}/fallo', name: 'delivery_failure', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reportFailure(#[MapEntity(id: 'id')] Delivery $delivery, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('delivery_failure', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('deliveries');
        }

        if (!$this->ownsDelivery($delivery) && !$this->isGranted('ROLE_EXECUTIVE')) {
            $this->addFlash('error', 'Ese despacho no está asignado a tu cuenta.');

            return $this->redirectToRoute('deliveries');
        }

        if ($delivery->isDeparted()) {
            $this->addFlash('error', 'Ese despacho ya salió, no se puede marcar como fallido.');

            return $this->redirectToRoute('deliveries');
        }

        if ($delivery->isFailed()) {
            $this->addFlash('warning', 'Ese despacho ya estaba marcado como fallido.');

            return $this->redirectToRoute('deliveries');
        }

        $reason = $this->failureCatalog->resolve($r->request->get('reason'), $r->request->get('customReason'));

        if ($reason === null) {
            $this->addFlash('error', 'Selecciona el motivo por el que no se pudo realizar la carga.');

            return $this->redirectToRoute('deliveries');
        }

        /** @var User $user */
        $user = $this->getUser();

        $delivery->setFailedAt(new \DateTimeImmutable());
        $delivery->setFailureReason($reason);
        $delivery->setFailureReportedBy($user);

        $this->entityManager->flush();

        $this->addFlash('success', 'Despacho marcado como fallido. El ejecutivo puede reprogramar desde el expediente.');

        return $this->redirectToRoute('deliveries');
    }

    /**
     * Traslado local: el transportista que ya salio deja parte (o toda) su
     * carga para que otro la continue. El expediente NO avanza de estatus
     * aqui — sigue "En tránsito" hasta la entrega real, la haga quien la
     * haga — y esto nunca lo ve el cliente (ver LocalTransfer).
     */
    #[Route('/dashboard/despachos/{id}/traspaso', name: 'delivery_handoff', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirmHandoff(#[MapEntity(id: 'id')] Delivery $delivery, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('delivery_handoff', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('deliveries');
        }

        if (!$this->ownsDelivery($delivery) && !$this->isGranted('ROLE_EXECUTIVE')) {
            $this->addFlash('error', 'Ese despacho no está asignado a tu cuenta.');

            return $this->redirectToRoute('deliveries');
        }

        if (!$delivery->isDeparted()) {
            $this->addFlash('error', 'Ese despacho todavía no ha salido, no se puede traspasar.');

            return $this->redirectToRoute('deliveries');
        }

        if ($delivery->isDelivered()) {
            $this->addFlash('error', 'Ese despacho ya tenía la entrega confirmada, no se puede traspasar.');

            return $this->redirectToRoute('deliveries');
        }

        if ($delivery->isFailed()) {
            $this->addFlash('error', 'Ese despacho está marcado como fallido, no se puede traspasar.');

            return $this->redirectToRoute('deliveries');
        }

        $placeType = (string) $r->request->get('placeType');

        if (!$this->placeCatalog->isValid($placeType)) {
            $this->addFlash('error', 'Selecciona dónde ocurrió el traspaso.');

            return $this->redirectToRoute('deliveries');
        }

        $place = null;
        $inspectionPoint = null;

        if ($placeType === LocalTransferPlaceCatalog::FREE_PLACE) {
            $place = trim((string) $r->request->get('place'));

            if ($place === '') {
                $this->addFlash('error', 'Captura el lugar del traspaso.');

                return $this->redirectToRoute('deliveries');
            }
        } elseif ($placeType === LocalTransferPlaceCatalog::INSPECTION_POINT) {
            $inspectionPoint = $this->entityManager->getRepository(InspectionPoint::class)->find($r->request->get('inspectionPointId'));

            if (!$inspectionPoint) {
                $this->addFlash('error', 'Selecciona el punto de inspección.');

                return $this->redirectToRoute('deliveries');
            }
        }

        $notes = trim((string) $r->request->get('notes'));
        $notes = $notes !== '' ? $notes : null;

        /** @var User $user */
        $user = $this->getUser();
        $at = new \DateTimeImmutable();

        if ($delivery->getContainers()->isEmpty()) {
            // Carga suelta: se traspasa todo el despacho de un jalon. Si es
            // compartido entre expedientes, hace falta saber de cual es.
            $reference = $delivery->getReferences()->count() > 1
                ? $this->entityManager->getRepository(ImportRequest::class)->find($r->request->get('reference'))
                : ($delivery->getReferences()->first() ?: null);

            if (!$reference || !$delivery->getReferences()->contains($reference)) {
                $this->addFlash('error', 'Selecciona a cuál expediente corresponde el traspaso.');

                return $this->redirectToRoute('deliveries');
            }

            $transfer = new LocalTransfer();
            $transfer->setFromDelivery($delivery);
            $transfer->setReference($reference);
            $transfer->setAt($at);
            $transfer->setPlaceType($placeType);
            $transfer->setPlace($place);
            $transfer->setInspectionPoint($inspectionPoint);
            $transfer->setNotes($notes);
            $transfer->setReportedBy($user);

            $this->entityManager->persist($transfer);
        } else {
            $chosenIds = array_map('intval', $r->request->all('containers'));

            if ($chosenIds === []) {
                $this->addFlash('error', 'Selecciona al menos un contenedor a traspasar.');

                return $this->redirectToRoute('deliveries');
            }

            $chosen = [];

            foreach ($delivery->getContainers() as $container) {
                if (in_array($container->getId(), $chosenIds, true)) {
                    $chosen[] = $container;
                }
            }

            if (count($chosen) !== count($chosenIds)) {
                $this->addFlash('error', 'Alguno de los contenedores elegidos no pertenece a este despacho.');

                return $this->redirectToRoute('deliveries');
            }

            // Agrupa por expediente dueño: un traspaso puede incluir
            // contenedores de mas de un expediente si el despacho es
            // compartido.
            $byReference = [];

            foreach ($chosen as $container) {
                $owner = $this->ownerFor($delivery, $container);

                if ($owner === null) {
                    $this->addFlash('error', sprintf('El contenedor %s no tiene un expediente único en este despacho; corrígelo antes de traspasarlo.', $container->getNum()));

                    return $this->redirectToRoute('deliveries');
                }

                $byReference[$owner->getId()]['reference'] = $owner;
                $byReference[$owner->getId()]['containers'][] = $container;
            }

            foreach ($byReference as $group) {
                $transfer = new LocalTransfer();
                $transfer->setFromDelivery($delivery);
                $transfer->setReference($group['reference']);
                $transfer->setAt($at);
                $transfer->setPlaceType($placeType);
                $transfer->setPlace($place);
                $transfer->setInspectionPoint($inspectionPoint);
                $transfer->setNotes($notes);
                $transfer->setReportedBy($user);

                foreach ($group['containers'] as $container) {
                    $transfer->addContainer($container);
                    $delivery->removeContainer($container);
                }

                $this->entityManager->persist($transfer);
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Traspaso registrado. La carga traspasada queda disponible para agendarle un nuevo despacho.');

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

    /**
     * La devolucion de vacios la hace normalmente el mismo transportista que
     * entrego, pero el ejecutivo puede asignarsela a otro (ver
     * Delivery::$returnTransport) — ese es quien debe poder verla y
     * registrarla, no necesariamente el que entrego.
     */
    private function ownsReturn(Delivery $delivery): bool
    {
        /** @var User $user */
        $user = $this->getUser();
        $hauler = $this->haulerFor($user);

        return $hauler !== null && ($delivery->getReturnTransport() ?? $delivery->getTransport()) === $hauler;
    }

    /**
     * Arma el mensaje de qué expediente(s) avanzaron tras confirmar salida o
     * entrega de un despacho — puede ser más de uno si la unidad se comparte.
     *
     * @param list<array{request: ImportRequest, status: string}> $moved
     */
    private function describeMoves(array $moved): string
    {
        $referencesByStatus = [];

        foreach ($moved as $entry) {
            $referencesByStatus[$entry['status']][] = $entry['request']->getClientReference();
        }

        $parts = [];

        foreach ($referencesByStatus as $status => $references) {
            $parts[] = sprintf(
                '%s %s a "%s".',
                implode(', ', $references),
                count($references) > 1 ? 'pasaron' : 'pasó',
                $status
            );
        }

        return implode(' ', $parts);
    }

    private function haulerFor(User $user): ?FreightHauler
    {
        return $this->entityManager->getRepository(FreightHauler::class)->findOneBy(['id_user' => $user]);
    }
}
