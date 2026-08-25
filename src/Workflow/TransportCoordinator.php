<?php

namespace App\Workflow;

use App\Entity\Delivery;
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
     * El transportista confirma que salio. Devuelve el nuevo estado del
     * expediente, o null si el expediente no se movio.
     */
    public function confirmDeparture(Delivery $delivery, \DateTimeImmutable $at): ?string
    {
        $delivery->setDepartedAt($at);

        $request = $delivery->getReference();
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
     * El transportista confirma la entrega. El expediente solo avanza cuando ya
     * no queda ningun despacho pendiente.
     */
    public function confirmArrival(Delivery $delivery, \DateTimeImmutable $at): ?string
    {
        $delivery->setDeliveredAt($at);

        $request = $delivery->getReference();

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
            if (!$delivery->isDelivered()) {
                ++$pending;
            }
        }

        return $pending;
    }

    /**
     * Contenedores del expediente que todavia no van en ningun camion.
     *
     * @return list<\App\Entity\Container>
     */
    public function unassignedContainers(ImportRequest $request): array
    {
        $assigned = [];

        foreach ($request->getDeliveries() as $delivery) {
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
