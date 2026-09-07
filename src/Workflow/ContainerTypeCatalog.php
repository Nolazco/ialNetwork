<?php

namespace App\Workflow;

/**
 * Tipos de contenedor que maneja la agencia hoy. Antes solo vivian como
 * opciones sueltas en el formulario de alta, sin ninguna lista con la que
 * validar del lado del servidor — hace falta ahora que el tipo se puede
 * corregir despues (ver DashboardCaseFiles::editContainer()), no solo
 * capturar una vez al dar de alta la solicitud.
 */
final class ContainerTypeCatalog
{
    public const STANDARD_20 = '20DC';
    public const HIGH_CUBE_20 = '20HC';
    public const STANDARD_40 = '40DC';
    public const HIGH_CUBE_40 = '40HC';
    public const REEFER_40 = '40RH';

    /** @var array<string, string> */
    public const LABELS = [
        self::STANDARD_20 => '20 pies estandar',
        self::HIGH_CUBE_20 => '20 pies cubo alto',
        self::STANDARD_40 => '40 pies estandar',
        self::HIGH_CUBE_40 => '40 pies cubo alto',
        self::REEFER_40 => '40 pies refrigerado',
    ];

    public function isValid(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::LABELS);
    }
}
