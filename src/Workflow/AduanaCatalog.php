<?php

namespace App\Workflow;

/**
 * Aduanas por las que la agencia despacha operaciones. Antes solo se
 * manejaba Manzanillo (quemado en SoiaClient y ConsolidatorInstructionSheetGenerator);
 * ahora cada solicitud (ImportRequest::$aduana) elige la suya, así que
 * ambos puntos consultan este catálogo en vez de asumir una sola.
 *
 * Las claves de self::LABELS son la clave real de 2 dígitos que lleva el
 * pedimento — la misma que ya usaba ConsolidatorInstructionSheetGenerator.
 * El portal SOIA usa una numeración propia para su combo (self::SOIA_CODES),
 * sin relación con la clave oficial.
 */
final class AduanaCatalog
{
    public const MANZANILLO = '16';
    public const LAZARO_CARDENAS = '51';
    public const VERACRUZ = '43';
    public const AICM = '47';

    /** @var array<string, string> */
    public const LABELS = [
        self::MANZANILLO => 'Manzanillo, Colima',
        self::LAZARO_CARDENAS => 'Lázaro Cárdenas, Michoacán',
        self::VERACRUZ => 'Veracruz',
        self::AICM => 'Aeropuerto Internacional de la CDMX',
    ];

    /** @var array<string, string> */
    private const SOIA_CODES = [
        self::MANZANILLO => '160',
        self::LAZARO_CARDENAS => '510',
        self::VERACRUZ => '430',
        self::AICM => '470',
    ];

    public function isValid(?string $aduana): bool
    {
        return $aduana !== null && array_key_exists($aduana, self::LABELS);
    }

    public function soiaCode(string $aduana): string
    {
        return self::SOIA_CODES[$aduana] ?? throw new \InvalidArgumentException("Aduana desconocida: {$aduana}");
    }
}
