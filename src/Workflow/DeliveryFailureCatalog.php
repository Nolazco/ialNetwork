<?php

namespace App\Workflow;

/**
 * Motivos por los que un despacho no se pudo realizar.
 *
 * El catalogo cubre lo habitual, pero no es una lista cerrada: siempre puede
 * aparecer un motivo fuera de lo comun, asi que el formulario deja capturar
 * uno propio. Delivery::$failureReason es un varchar justamente por eso.
 */
final class DeliveryFailureCatalog
{
    /**
     * @var list<string>
     */
    public const COMMON = [
        'Autoridad rechazó la carga',
        'Unidad no cumplió los requisitos de la autoridad',
        'Documentación incompleta o rechazada en el recinto',
        'Mercancía no coincide con lo declarado',
        'Cita cancelada por el cliente',
    ];

    /** Valor que el selector envia cuando se captura un motivo propio. */
    public const OTHER = '__otra__';

    /**
     * Normaliza lo que llega del formulario al motivo que se va a guardar.
     *
     * Devuelve null cuando no hay nada que registrar, para que el controlador
     * pueda rechazar la peticion.
     */
    public function resolve(?string $selected, ?string $custom): ?string
    {
        if ($selected === self::OTHER) {
            $custom = trim((string) $custom);

            return $custom === '' ? null : $custom;
        }

        return in_array($selected, self::COMMON, true) ? $selected : null;
    }
}
