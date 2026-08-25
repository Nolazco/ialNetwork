<?php

namespace App\Workflow;

use App\Entity\ImportRequest;

/**
 * Define por que estados pasa un expediente y en que orden.
 *
 * El recorrido depende de dos ejes: si la operacion es de importacion o de
 * exportacion, y si la mercancia viene contenerizada o en carga suelta. Son
 * cuatro secuencias distintas y no comparten ni los mismos pasos ni el mismo
 * orden (por ejemplo, en exportacion la liberacion en terminal va antes del
 * pago cuando es contenedor, y despues de la modulacion cuando es carga suelta).
 *
 * La inspeccion fuera de puerto es un paso opcional: solo aplica a cierta
 * mercancia de importacion, asi que el ejecutivo puede registrarla o saltarsela.
 */
final class ImportRequestWorkflow
{
    public const DIRECTION_IMPORT = 'import';
    public const DIRECTION_EXPORT = 'export';

    public const TYPE_CONTAINER = 'container';
    public const TYPE_LCL = 'lcl';

    /** Etiquetas para los selectores y los listados. */
    public const DIRECTIONS = [
        self::DIRECTION_IMPORT => 'Importación',
        self::DIRECTION_EXPORT => 'Exportación',
    ];

    public const TYPES = [
        self::TYPE_CONTAINER => 'Contenedor',
        self::TYPE_LCL => 'Carga suelta',
    ];

    public const PENDING = 'Pendiente';
    public const CAPTURED = 'Capturado';
    public const DECONSOLIDATED = 'Desconsolidado';
    public const REVALIDATED = 'Revalidado';
    public const ENTERED = 'Ingresado';
    public const RELEASED_AT_TERMINAL = 'Liberado en terminal';
    public const PAID = 'Pagado';
    public const SCHEDULED = 'Programado';
    public const MODULATED = 'Modulado';
    public const OFFSITE_INSPECTION = 'Inspección fuera de puerto';
    public const IN_TRANSIT = 'En tránsito';
    public const DELIVERED = 'Entregado';
    public const EMPTY_RETURNED = 'Vacío devuelto';
    public const FINISHED = 'Finalizado';

    /**
     * Pasos que se pueden omitir: no aplican al 100% de la mercancia.
     */
    private const OPTIONAL = [self::OFFSITE_INSPECTION];

    /**
     * @var array<string, list<string>>
     */
    private const SEQUENCES = [
        self::DIRECTION_IMPORT.'.'.self::TYPE_CONTAINER => [
            self::PENDING,
            self::CAPTURED,
            self::REVALIDATED,
            self::PAID,
            self::SCHEDULED,
            self::MODULATED,
            self::OFFSITE_INSPECTION,
            self::IN_TRANSIT,
            self::DELIVERED,
            self::EMPTY_RETURNED,
            self::FINISHED,
        ],
        self::DIRECTION_IMPORT.'.'.self::TYPE_LCL => [
            self::PENDING,
            self::CAPTURED,
            self::DECONSOLIDATED,
            self::REVALIDATED,
            self::PAID,
            self::SCHEDULED,
            self::MODULATED,
            self::OFFSITE_INSPECTION,
            self::IN_TRANSIT,
            self::DELIVERED,
            // Sin devolucion de vacio: la carga suelta se desconsolida en el
            // recinto, asi que la agencia nunca toma posesion del contenedor.
            self::FINISHED,
        ],
        self::DIRECTION_EXPORT.'.'.self::TYPE_CONTAINER => [
            self::PENDING,
            self::CAPTURED,
            self::ENTERED,
            self::RELEASED_AT_TERMINAL,
            self::PAID,
            self::MODULATED,
            self::FINISHED,
        ],
        self::DIRECTION_EXPORT.'.'.self::TYPE_LCL => [
            self::PENDING,
            self::CAPTURED,
            self::ENTERED,
            self::PAID,
            self::MODULATED,
            self::RELEASED_AT_TERMINAL,
            self::FINISHED,
        ],
    ];

    /**
     * La secuencia completa que le corresponde a este expediente.
     *
     * @return list<string>
     */
    public function sequenceFor(ImportRequest $request): array
    {
        return self::SEQUENCES[$request->getDirection().'.'.$request->getType()]
            ?? self::SEQUENCES[self::DIRECTION_IMPORT.'.'.self::TYPE_CONTAINER];
    }

    /**
     * Estados a los que se puede avanzar desde el actual.
     *
     * Normalmente es uno solo. Son dos cuando el siguiente paso es opcional: el
     * ejecutivo elige entre registrarlo o saltarselo.
     *
     * @return list<string>
     */
    public function nextStatuses(ImportRequest $request): array
    {
        $sequence = $this->sequenceFor($request);
        $position = array_search($request->getStatus(), $sequence, true);

        if ($position === false || $position === count($sequence) - 1) {
            return [];
        }

        $next = [$sequence[$position + 1]];

        if (in_array($sequence[$position + 1], self::OPTIONAL, true) && isset($sequence[$position + 2])) {
            $next[] = $sequence[$position + 2];
        }

        return $next;
    }

    /**
     * ¿Puede el expediente pasar del estado actual al indicado?
     */
    public function canTransitionTo(ImportRequest $request, string $status): bool
    {
        return in_array($status, $this->nextStatuses($request), true);
    }

    public function isFinished(ImportRequest $request): bool
    {
        return $request->getStatus() === self::FINISHED;
    }

    public function isOptional(string $status): bool
    {
        return in_array($status, self::OPTIONAL, true);
    }

    /**
     * Avance del expediente en porcentaje, para la barra de progreso.
     */
    public function progress(ImportRequest $request): int
    {
        $sequence = $this->sequenceFor($request);
        $position = array_search($request->getStatus(), $sequence, true);

        if ($position === false) {
            return 0;
        }

        return (int) round($position / (count($sequence) - 1) * 100);
    }

    /**
     * Los estados ya recorridos, para pintarlos como completados en la linea de
     * tiempo. Un paso opcional que se salto no cuenta como recorrido.
     *
     * @return list<string>
     */
    public function completedStatuses(ImportRequest $request): array
    {
        $sequence = $this->sequenceFor($request);
        $position = array_search($request->getStatus(), $sequence, true);

        if ($position === false) {
            return [];
        }

        return array_slice($sequence, 0, $position + 1);
    }
}
