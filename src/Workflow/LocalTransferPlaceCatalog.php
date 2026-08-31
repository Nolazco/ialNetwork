<?php

namespace App\Workflow;

/**
 * Donde puede ocurrir un traspaso local entre transportistas.
 *
 * Lista cerrada, igual que EmptyReturnCatalog: son las tres formas en que
 * ocurre el cambio de camion, no un catalogo abierto.
 */
final class LocalTransferPlaceCatalog
{
    /** El transportista que traspasa lo deja en su propio patio. */
    public const CARRIER_YARD = 'Patio del transporte';

    /** Un lugar cualquiera, descrito en texto libre. */
    public const FREE_PLACE = 'Lugar libre';

    /** Un punto de inspeccion del catalogo (ver InspectionPoint). */
    public const INSPECTION_POINT = 'Punto de inspección';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::CARRIER_YARD,
        self::FREE_PLACE,
        self::INSPECTION_POINT,
    ];

    public function isValid(?string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }
}
