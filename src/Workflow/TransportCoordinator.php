<?php

namespace App\Workflow;

use App\Entity\Container;
use App\Entity\Delivery;
use App\Entity\EmptyReturn;
use App\Entity\ImportRequest;

/**
 * Traduce lo que confirma el transportista en avance del expediente.
 *
 * La regla no es uno a uno, porque un expediente contenerizado puede llevar
 * varios camiones: basta que uno salga para que el expediente vaya en transito,
 * pero solo queda entregado cuando han llegado todos.
 */
final class TransportCoordinator
{
    public function __construct(private readonly ImportRequestWorkflow $workflow)
    {
    }

    /**
     * El transportista confirma que salio. Un despacho compartido entre
     * varios expedientes (mismo cliente, misma unidad) los mueve a todos de
     * forma independiente: cada uno avanza solo si a el le toca.
     *
     * @return list<array{request: ImportRequest, status: string}>
     */
    public function confirmDeparture(Delivery $delivery, \DateTimeImmutable $at): array
    {
        $delivery->setDepartedAt($at);

        $moved = [];

        foreach ($delivery->getReferences() as $request) {
            $status = $this->confirmDepartureFor($request);

            if ($status !== null) {
                $moved[] = ['request' => $request, 'status' => $status];
            }
        }

        return $moved;
    }

    private function confirmDepartureFor(ImportRequest $request): ?string
    {
        $departure = $this->workflow->departureStatus($request);

        // En exportacion no hay trayecto que registrar: el expediente no avanza
        // hasta que el camion llega al recinto.
        if ($departure === null || !$this->workflow->canTransitionTo($request, $departure)) {
            return null;
        }

        $request->setStatus($departure);

        return $departure;
    }

    /**
     * El transportista confirma la entrega. Cada expediente del despacho solo
     * avanza cuando ya no le queda ningun despacho propio pendiente — el
     * hecho de compartir camion con otro expediente no acelera ni retrasa el
     * suyo.
     *
     * @return list<array{request: ImportRequest, status: string}>
     */
    public function confirmArrival(Delivery $delivery, \DateTimeImmutable $at): array
    {
        $delivery->setDeliveredAt($at);

        $moved = [];

        foreach ($delivery->getReferences() as $request) {
            $status = $this->confirmArrivalFor($request);

            if ($status !== null) {
                $moved[] = ['request' => $request, 'status' => $status];
            }
        }

        return $moved;
    }

    private function confirmArrivalFor(ImportRequest $request): ?string
    {
        if ($this->pendingDeliveries($request) > 0) {
            return null;
        }

        $arrival = $this->workflow->arrivalStatus($request);

        if (!$this->workflow->canTransitionTo($request, $arrival)) {
            return null;
        }

        $request->setStatus($arrival);

        return $arrival;
    }

    /**
     * Despachos del expediente que todavia no llegan a su destino.
     */
    public function pendingDeliveries(ImportRequest $request): int
    {
        $pending = 0;

        foreach ($request->getDeliveries() as $delivery) {
            // Un despacho fallido nunca se va a entregar: es un callejon sin
            // salida que una nueva cita reemplaza, no algo que siga pendiente.
            if (!$delivery->isDelivered() && !$delivery->isFailed()) {
                ++$pending;
            }
        }

        return $pending;
    }

    /**
     * El transportista registra la devolucion de un vacio. El expediente solo
     * avanza cuando ya volvieron todos los contenedores.
     */
    public function confirmEmptyReturn(EmptyReturn $return): ?string
    {
        $request = $return->getReference();

        if ($this->containersPendingReturn($request) !== []) {
            return null;
        }

        $status = ImportRequestWorkflow::EMPTY_RETURNED;

        if (!$this->workflow->canTransitionTo($request, $status)) {
            return null;
        }

        $request->setStatus($status);

        return $status;
    }

    /**
     * Contenedores del expediente que aun no se devuelven al patio.
     *
     * @return list<Container>
     */
    public function containersPendingReturn(ImportRequest $request): array
    {
        if (!$this->workflow->requiresEmptyReturn($request)) {
            return [];
        }

        $returned = [];

        foreach ($request->getEmptyReturns() as $return) {
            $returned[$return->getContainer()->getId()] = true;
        }

        $pending = [];

        foreach ($request->getContainers() as $container) {
            if (!isset($returned[$container->getId()])) {
                $pending[] = $container;
            }
        }

        return $pending;
    }

    /**
     * Contenedores de este despacho que el transportista todavia debe devolver.
     *
     * @return list<Container>
     */
    public function containersPendingReturnFor(Delivery $delivery): array
    {
        $pendingByRequest = [];

        foreach ($delivery->getReferences() as $request) {
            foreach ($this->containersPendingReturn($request) as $container) {
                $pendingByRequest[$container->getId()] = $container;
            }
        }

        $pending = [];

        foreach ($delivery->getContainers() as $container) {
            if (isset($pendingByRequest[$container->getId()])) {
                $pending[] = $container;
            }
        }

        return $pending;
    }

    /**
     * Contenedores del expediente que todavia no van en ningun camion.
     *
     * @return list<Container>
     */
    public function unassignedContainers(ImportRequest $request): array
    {
        $assigned = [];

        foreach ($request->getDeliveries() as $delivery) {
            // Un despacho fallido no se queda con el contenedor: vuelve a
            // estar disponible para una nueva cita.
            if ($delivery->isFailed()) {
                continue;
            }

            foreach ($delivery->getContainers() as $container) {
                $assigned[$container->getId()] = true;
            }
        }

        $pending = [];

        foreach ($request->getContainers() as $container) {
            if (!isset($assigned[$container->getId()])) {
                $pending[] = $container;
            }
        }

        return $pending;
    }
}
