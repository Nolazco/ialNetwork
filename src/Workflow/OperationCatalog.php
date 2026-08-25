<?php

namespace App\Workflow;

/**
 * Maniobras que el ejecutivo puede registrar sobre un expediente.
 *
 * El catalogo cubre lo habitual, pero no es una lista cerrada: siempre puede
 * aparecer una maniobra fuera de lo comun, asi que el formulario deja capturar
 * uno propio. Operation::$type es un varchar justamente por eso.
 */
final class OperationCatalog
{
    /**
     * @var list<string>
     */
    public const COMMON = [
        'Previo Ocular',
        'Previo DesyCon',
        'Previo Ocular con autoridad',
        'Previo DesyCon con autoridad',
        'Etiquetado',
        'Separación de mercancía',
        'Desconsolidación parcial',
        'Desconsolidación total',
        'Transferencia de recinto',
    ];

    /** Valor que el selector envia cuando el ejecutivo captura una maniobra propia. */
    public const OTHER = '__otra__';

    /**
     * Normaliza lo que llega del formulario a la maniobra que se va a guardar.
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
