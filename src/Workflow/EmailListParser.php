<?php

namespace App\Workflow;

/**
 * Convierte lo que alguien captura en un solo campo de texto ("correo1;
 * correo2, correo3") en una lista limpia de correos validos, sin duplicados.
 * Acepta punto y coma o coma como separador (lo usual en clientes de correo),
 * para no depender de que el usuario recuerde uno en particular.
 *
 * Compartido entre DashboardForwarders (catalogo interno) y DashboardImports
 * (alta de forwarder en linea desde la solicitud del cliente).
 */
final class EmailListParser
{
    /**
     * @return list<string>
     */
    public static function parse(string $raw): array
    {
        $emails = [];

        foreach (preg_split('/[;,]+/', $raw) ?: [] as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $emails[$candidate] = true;
            }
        }

        return array_keys($emails);
    }
}
