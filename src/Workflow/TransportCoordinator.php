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
     * Motivos por los que este despacho todavia no puede salir: alguno de
     * sus expedientes no ha llegado al punto donde le toca salir (ej. sigue
     * sin modular). Es una sola unidad fisica: no puede salir del recinto
     * con carga mixta (una parte liberada, otra no), asi que basta con que
     * uno no este listo para bloquear todo el despacho. Vacio significa que
     * ya se puede confirmar.
     *
     * @return list<string>
     */
    public function departureBlockers(Delivery $delivery): array
    {
        $blockers = [];

        foreach ($delivery->getReferences() as $request) {
            $target = $this->workflow->departureStatus($request);

            // "!==" y no solo canTransitionTo: un segundo camion del mismo
            // expediente puede confirmar su salida cuando el expediente ya
            // va "En transito" por el primero — eso no es un bloqueo.
            if ($target !== null && $request->getStatus() !== $target && !$this->workflow->canTransitionTo($request, $target)) {
                $blockers[] = sprintf('%s sigue en "%s"; todavía no puede salir del recinto.', $request->getClientReference(), $request->getStatus());
            }
        }

        return $blockers;
    }

    /**
     * Motivos por los que este despacho todavia no puede entregarse. Mismo
     * criterio de "todo o nada" que departureBlockers(). Vacio significa que
     * ya se puede confirmar.
     *
     * @return list<string>
     */
    public function arrivalBlockers(Delivery $delivery): array
    {
        $blockers = [];

        foreach ($delivery->getReferences() as $request) {
            // Otros despachos del mismo expediente que sigan pendientes (sin
            // contar este) no son un bloqueo: es legitimo que este camion
            // llegue aunque el expediente en conjunto no cierre todavia.
            $otherPending = 0;

            foreach ($request->getDeliveries() as $other) {
                if ($other === $delivery) {
                    continue;
                }

                if ($other->stillOwes($request)) {
                    ++$otherPending;
                }
            }

            if ($otherPending > 0) {
                continue;
            }

            $departure = $this->workflow->departureStatus($request);
            $arrival = $this->workflow->arrivalStatus($request);

            if ($departure === null) {
                // Exportacion: no hay salida que confirmar, la llegada es el
                // unico paso.
                if (!$this->workflow->canTransitionTo($request, $arrival)) {
                    $blockers[] = sprintf('%s sigue en "%s"; todavía no puede pasar a "%s".', $request->getClientReference(), $request->getStatus(), $arrival);
                }

                continue;
            }

            // Importacion: confirmar entrega puede confirmar la salida de
            // forma implicita si todavia no se hizo (esto ya lo hace el
            // controlador), asi que basta con que la salida sea valida ahora
            // o ya haya pasado.
            $readyForDeparture = $request->getStatus() === $departure || $this->workflow->canTransitionTo($request, $departure);

            if (!$readyForDeparture) {
                $blockers[] = sprintf('%s sigue en "%s"; todavía no puede salir del recinto.', $request->getClientReference(), $request->getStatus());
            }
        }

        return $blockers;
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
        // Se calcula ANTES de marcar la entrega: una vez marcada,
        // stillOwes() ya no puede distinguir a quien de verdad le tocaba
        // esta entrega. Un despacho compartido de carga suelta que ya le
        // traspaso su parte a un expediente no debe "entregarsela" de nuevo
        // solo porque el despacho en general se acaba de marcar entregado.
        $owed = [];

        foreach ($delivery->getReferences() as $request) {
            if ($delivery->stillOwes($request)) {
                $owed[] = $request;
            }
        }

        $delivery->setDeliveredAt($at);

        $moved = [];

        foreach ($owed as $request) {
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

        // Un contenedor traspasado que todavia no tiene una continuacion
        // asignada no cuenta como "despacho pendiente" (ver
        // Delivery::stillOwes()), pero tampoco esta entregado: sigue
        // flotando en el punto de traspaso, sin camion. No aplica a carga
        // suelta (nunca tiene contenedores que revisar aqui).
        if ($this->unassignedContainers($request) !== []) {
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
     *
     * No es "cuantos no estan entregados/fallidos": un despacho compartido
     * de carga suelta puede haberle traspasado su parte a ESTE expediente y
     * seguir debiendole la suya al otro — por eso se pregunta puntualmente
     * si a este expediente en concreto le sigue debiendo algo (ver
     * Delivery::stillOwes()), no solo por su propio estado general.
     */
    public function pendingDeliveries(ImportRequest $request): int
    {
        $pending = 0;

        foreach ($request->getDeliveries() as $delivery) {
            if ($delivery->stillOwes($request)) {
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
     * Contenedores del expediente que aun no se devuelven al patio de
     * verdad, este o no ya programada su cita (ver EmptyReturn::isExecuted()).
     *
     * @return list<Container>
     */
    public function containersPendingReturn(ImportRequest $request): array
    {
        if (!$this->workflow->requiresEmptyReturn($request)) {
            return [];
        }

        $executed = [];

        foreach ($request->getEmptyReturns() as $return) {
            if ($return->isExecuted()) {
                $executed[$return->getContainer()->getId()] = true;
            }
        }

        $pending = [];

        foreach ($request->getContainers() as $container) {
            if (!isset($executed[$container->getId()])) {
                $pending[] = $container;
            }
        }

        return $pending;
    }

    /**
     * Contenedores del expediente a los que el ejecutivo todavia no les
     * programa cita de devolucion (ni siquiera existe el registro). Antes de
     * esto, el transportista no tiene nada que hacer: no elige el patio, lo
     * asigna el ejecutivo segun instrucciones de la naviera.
     *
     * @return list<Container>
     */
    public function containersPendingSchedule(ImportRequest $request): array
    {
        if (!$this->workflow->requiresEmptyReturn($request)) {
            return [];
        }

        $scheduled = [];

        foreach ($request->getEmptyReturns() as $return) {
            $scheduled[$return->getContainer()->getId()] = true;
        }

        $pending = [];

        foreach ($request->getContainers() as $container) {
            if (!isset($scheduled[$container->getId()])) {
                $pending[] = $container;
            }
        }

        return $pending;
    }

    /**
     * El registro de devolucion (programada por el ejecutivo) de un
     * contenedor, si ya existe.
     */
    public function emptyReturnFor(Container $container): ?EmptyReturn
    {
        foreach ($container->getReference() as $request) {
            foreach ($request->getEmptyReturns() as $return) {
                if ($return->getContainer() === $container) {
                    return $return;
                }
            }
        }

        return null;
    }

    /**
     * El despacho vigente de un contenedor (el que no quedo descartado por
     * un despacho fallido, ver unassignedContainers()). Sirve para saber
     * quien es "el transporte ya asignado" antes de programar su devolucion
     * de vacio.
     */
    public function deliveryFor(Container $container): ?Delivery
    {
        foreach ($container->getDeliveries() as $delivery) {
            if (!$delivery->isFailed()) {
                return $delivery;
            }
        }

        return null;
    }

    /**
     * Contenedores de este despacho cuya devolucion ya esta programada (el
     * ejecutivo ya le asigno patio y cita) pero el transportista todavia no
     * la ejecuta. Antes de programarse, el contenedor no aparece aqui: nada
     * que hacer todavia.
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
            if (isset($pendingByRequest[$container->getId()]) && $this->emptyReturnFor($container) !== null) {
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
