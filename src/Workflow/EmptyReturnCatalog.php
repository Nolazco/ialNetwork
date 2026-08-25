<?php

namespace App\Workflow;

/**
 * Formas en que se devuelve un contenedor vacio.
 *
 * A diferencia de OperationCatalog, esta si es una lista cerrada: son las dos
 * maneras en que puede ocurrir la maniobra, no un catalogo abierto. Si aparece
 * otra, se agrega aqui y queda disponible en el formulario.
 */
final class EmptyReturnCatalog
{
    /** El mismo camion que entrego la mercancia lleva el vacio al patio. */
    public const DIRECT = 'Directa';

    /** Otro camion pasa despues por el vacio. */
    public const PICKUP = 'Recolección';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::DIRECT,
        self::PICKUP,
    ];

    public function isValid(?string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }
}
