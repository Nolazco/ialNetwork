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
     * Documentos del ejecutivo que hacen falta para llegar a cada estatus.
     * Es una capa aparte de la secuencia (SEQUENCES/nextStatuses): esta decide
     * *que* estatus sigue, DOCUMENT_GATES decide si ya se puede *entrar*.
     *
     * SCHEDULED y MODULATED no llevan entrada aqui porque su regla no es "un
     * documento fijo" (ver missingRequirements): SCHEDULED se satisface con el
     * comprobante O con tener ya un despacho asignado, y MODULATED ya no se
     * satisface con un documento sino con una consulta SOIA exitosa.
     *
     * @var array<string, list<string>>
     */
    private const DOCUMENT_GATES = [
        self::CAPTURED => [RequiredDocumentType::PROFORMA],
        self::REVALIDATED => [RequiredDocumentType::REVALIDATED_BL],
        self::PAID => [RequiredDocumentType::FULL_PEDIMENTO, RequiredDocumentType::SIMPLIFIED_PEDIMENTO],
        self::OFFSITE_INSPECTION => [RequiredDocumentType::INSPECTION_CERTIFICATE],
    ];

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

    /**
     * Que le falta al expediente para poder entrar al estatus indicado, en
     * frases listas para mostrar. Vacio significa que ya se puede avanzar (en
     * cuanto a documentos: sigue haciendo falta que $status este en
     * nextStatuses()).
     *
     * @return list<string>
     */
    public function missingRequirements(ImportRequest $request, string $status): array
    {
        if ($status === self::SCHEDULED) {
            $satisfied = !$request->getDeliveries()->isEmpty()
                || $this->hasRequiredDocument($request, RequiredDocumentType::SCHEDULE_PROOF);

            return $satisfied ? [] : [RequiredDocumentType::SCHEDULE_PROOF.' (o asignar transporte con fecha/hora)'];
        }

        // Modulado ya no se satisface subiendo un documento: solo lo dispara
        // una consulta SOIA exitosa (ver ModuladoConfirmer), automatica o
        // manual, nunca el boton generico de avance.
        if ($status === self::MODULATED) {
            return ['Usa "Consultar SOIA" para confirmar la modulación'];
        }

        $missing = [];

        foreach (self::DOCUMENT_GATES[$status] ?? [] as $type) {
            if (!$this->hasRequiredDocument($request, $type)) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    private function hasRequiredDocument(ImportRequest $request, string $type): bool
    {
        foreach ($request->getRequiredDocuments() as $document) {
            if ($document->getType() === $type && $document->getRoute() !== null) {
                return true;
            }
        }

        return false;
    }

    public function isFinished(ImportRequest $request): bool
    {
        return $request->getStatus() === self::FINISHED;
    }

    /**
     * Estatus que solo dependen de documentos o de un formulario propio (no
     * de un evento externo como la modulacion via SOIA), asi que pueden
     * avanzar solos en cuanto se cumplen. "Capturado" se excluye a proposito:
     * aunque tiene entrada en DOCUMENT_GATES, tambien depende de los datos
     * del alta del pedimento (ver DashboardCaseFiles::capture()), no solo del
     * documento — avanzarlo aqui dejaria agencyReference/importNumber en
     * "Pendiente". "Modulado" no aplica nunca: missingRequirements() nunca
     * regresa vacio para el, solo lo dispara una consulta SOIA exitosa.
     */
    private const AUTO_ADVANCE_ON_DOCUMENT = [self::SCHEDULED, self::REVALIDATED, self::PAID, self::OFFSITE_INSPECTION];

    /**
     * Si el expediente ya cumple lo necesario para el siguiente estatus (de
     * los que dependen nada mas de documentos/formularios propios), lo
     * avanza solo. El boton manual "Avanzar" sigue disponible aparte para
     * casos extraordinarios.
     *
     * @return string|null el nuevo estatus si avanzo, null si no habia nada que hacer
     */
    public function tryAutoAdvance(ImportRequest $request): ?string
    {
        foreach ($this->nextStatuses($request) as $candidate) {
            if (!in_array($candidate, self::AUTO_ADVANCE_ON_DOCUMENT, true)) {
                continue;
            }

            if ($this->missingRequirements($request, $candidate) === []) {
                $request->setStatus($candidate);

                return $candidate;
            }
        }

        return null;
    }

    public function isOptional(string $status): bool
    {
        return in_array($status, self::OPTIONAL, true);
    }

    /**
     * Estado que alcanza el expediente cuando el transportista confirma la
     * salida del recinto.
     *
     * Solo la importacion tiene trayecto propio: en exportacion el camion
     * recoge en planta y lo unico que se registra es su llegada al recinto.
     */
    public function departureStatus(ImportRequest $request): ?string
    {
        return $request->getDirection() === self::DIRECTION_IMPORT ? self::IN_TRANSIT : null;
    }

    /**
     * Estado que alcanza el expediente cuando el transportista confirma que
     * dejo la mercancia donde tocaba.
     */
    public function arrivalStatus(ImportRequest $request): string
    {
        return $request->getDirection() === self::DIRECTION_IMPORT ? self::DELIVERED : self::ENTERED;
    }

    /**
     * ¿El expediente esta justo en el punto de avisar al transporte?
     */
    public function awaitsTransport(ImportRequest $request): bool
    {
        $target = $this->departureStatus($request) ?? $this->arrivalStatus($request);

        return in_array($target, $this->nextStatuses($request), true);
    }

    /**
     * ¿Se le puede asignar transporte ahora?
     *
     * Tambien cuando ya va en transito: con varios contenedores el primer
     * camion puede haber salido mientras faltan otros por asignar. Y tambien
     * un paso antes de Programado: agendar la cita (con transportista real o
     * "pendiente") es una de las dos formas de satisfacer ese requisito, asi
     * que hace falta poder hacerlo desde Pagado, no solo cuando el despacho ya
     * es el siguiente paso inmediato.
     *
     * Y tambien ya estando en Programado sin despacho todavia: a ese estatus
     * se puede llegar solo con el comprobante de cita (ver missingRequirements),
     * sin haber agendado nada de verdad todavia, asi que hace falta seguir
     * pudiendo asignarlo despues.
     *
     * Si la mercancia viaja con el consolidador de carga (XCF), no se puede
     * avisar al transporte hasta mandarle antes las instrucciones: XCF genera
     * un folio que el transportista debe presentar, asi que el aviso sin ese
     * folio dejaria al camion sin poder entregar.
     */
    public function canAssignTransport(ImportRequest $request): bool
    {
        if ($request->travelsWithConsolidator() && $request->getConsolidatorInstructions()->isEmpty()) {
            return false;
        }

        if ($this->awaitsTransport($request) || in_array(self::SCHEDULED, $this->nextStatuses($request), true)) {
            return true;
        }

        if ($request->getStatus() === self::SCHEDULED && $request->getDeliveries()->isEmpty()) {
            return true;
        }

        $departure = $this->departureStatus($request);

        return $departure !== null && $request->getStatus() === $departure;
    }

    /**
     * ¿Este expediente termina devolviendo contenedores vacios?
     *
     * Solo la importacion contenerizada: en carga suelta la mercancia se
     * desconsolida en el recinto y la agencia nunca toma el contenedor, y en
     * exportacion el vacio no le corresponde.
     */
    public function requiresEmptyReturn(ImportRequest $request): bool
    {
        return in_array(self::EMPTY_RETURNED, $this->sequenceFor($request), true);
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

        $taken = $request->getOptionalStepsTaken();

        // Un paso opcional que se salto queda atras en la secuencia pero no se
        // recorrio, asi que no puede aparecer como completado.
        return array_values(array_filter(
            array_slice($sequence, 0, $position + 1),
            fn (string $step): bool => !$this->isOptional($step) || in_array($step, $taken, true),
        ));
    }

    /**
     * Pasos opcionales que el expediente ya dejo atras sin realizarlos.
     *
     * @return list<string>
     */
    public function skippedStatuses(ImportRequest $request): array
    {
        $sequence = $this->sequenceFor($request);
        $position = array_search($request->getStatus(), $sequence, true);

        if ($position === false) {
            return [];
        }

        $taken = $request->getOptionalStepsTaken();

        return array_values(array_filter(
            array_slice($sequence, 0, $position + 1),
            fn (string $step): bool => $this->isOptional($step) && !in_array($step, $taken, true),
        ));
    }

    /**
     * La secuencia a mostrar en el roadmap del expediente: igual que
     * sequenceFor(), pero sin "Inspección fuera de puerto" cuando el cliente
     * ya avisó desde el alta que no la espera y nunca se termino registrando
     * una. Es solo para no confundir al cliente con un paso que en la
     * practica no va a pasar — el aviso del cliente no decide nada por si
     * solo (ver InspectionAuthorityCatalog), asi que el paso real (avanzarlo
     * via advance() si el certificado si llega) no se toca aqui, solo su
     * aparicion en esta lista.
     *
     * @return list<string>
     */
    public function roadmapSequence(ImportRequest $request): array
    {
        if ($this->offsiteInspectionExpected($request)) {
            return $this->sequenceFor($request);
        }

        return array_values(array_filter(
            $this->sequenceFor($request),
            fn (string $step): bool => $step !== self::OFFSITE_INSPECTION,
        ));
    }

    /**
     * ¿Sigue siendo relevante mostrar/pedir algo de "Inspección fuera de
     * puerto" para este expediente? No, cuando el cliente ya avisó desde el
     * alta que no la espera y nunca se termino registrando una — en ese caso
     * no solo se oculta del roadmap (roadmapSequence()), tampoco tiene caso
     * seguir avisando "falta el certificado" en el panel de siguiente paso
     * ni marcarlo como pendiente en la tabla de documentos. El aviso del
     * cliente no decide nada por si solo (ver InspectionAuthorityCatalog):
     * si el certificado real llega de todos modos, sigue pudiendo subirse
     * (la tabla de documentos no oculta el control, solo el aviso), y eso
     * ya alcanza para que optionalStepsTaken lo registre y esto vuelva a
     * dar true.
     */
    public function offsiteInspectionExpected(ImportRequest $request): bool
    {
        return $request->getExpectedInspectionAuthority() !== InspectionAuthorityCatalog::NONE
            || in_array(self::OFFSITE_INSPECTION, $request->getOptionalStepsTaken(), true);
    }
}
