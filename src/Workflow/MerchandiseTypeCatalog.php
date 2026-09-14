<?php

namespace App\Workflow;

/**
 * Tipo de mercancia que pide XCF para cotizar (catalogo propio de ellos, no
 * del SAT) — ver DashboardConsolidatorQuotes.
 */
final class MerchandiseTypeCatalog
{
    public const RAW_MATERIAL = '01';
    public const PROCESSED_MATERIAL = '02';
    public const FINISHED_MATERIAL = '03';
    public const MANUFACTURING_INDUSTRY_MATERIAL = '04';
    public const OTHER = '05';

    /** @var array<string, string> */
    public const LABELS = [
        self::RAW_MATERIAL => 'Materia prima',
        self::PROCESSED_MATERIAL => 'Materia procesada',
        self::FINISHED_MATERIAL => 'Materia terminada (producto terminado)',
        self::MANUFACTURING_INDUSTRY_MATERIAL => 'Materia para la industria manufacturera',
        self::OTHER => 'Otra',
    ];

    public function isValid(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::LABELS);
    }
}
