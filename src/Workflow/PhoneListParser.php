<?php

namespace App\Workflow;

/**
 * Convierte lo que alguien captura en un solo campo de texto ("numero1;
 * numero2, numero3") en una lista limpia de numeros de WhatsApp, sin
 * duplicados. Igual que EmailListParser pero sin validar formato: el numero
 * puede traer codigo de pais, y WhatsAppSender es quien decide como
 * formatearlo para la API (ver formatJid en scripts/whatsapp-bridge-server.js).
 */
final class PhoneListParser
{
    /**
     * @return list<string>
     */
    public static function parse(string $raw): array
    {
        $numeros = [];

        foreach (preg_split('/[;,]+/', $raw) ?: [] as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '') {
                $numeros[$candidate] = true;
            }
        }

        return array_keys($numeros);
    }
}
