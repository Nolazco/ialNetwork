<?php

namespace App\Workflow;

/**
 * Lo que el cliente anticipa sobre la inspección de su mercancía al dar de
 * alta la solicitud: si espera que aplique, y de que autoridad.
 *
 * Es solo lo que el cliente cree en ese momento, no lo que decide el
 * ejecutivo mas adelante (eso lo sigue marcando el certificado real en
 * "Documentos del ejecutivo"). Por eso incluye "Por confirmar": el cliente
 * no siempre lo sabe al momento de crear la solicitud.
 */
final class InspectionAuthorityCatalog
{
    public const NONE = 'No requiere';
    public const UNSURE = 'Por confirmar';

    /**
     * @var list<string>
     */
    public const COMMON = ['SADER', 'COFEPRIS', 'SEMARNAT'];

    /** Valor que el selector envia cuando el cliente captura una autoridad propia. */
    public const OTHER = '__otra__';

    /**
     * Normaliza lo que llega del formulario. Devuelve null cuando no hay
     * nada valido que guardar, para que el controlador pueda rechazar la
     * peticion.
     */
    public function resolve(?string $selected, ?string $custom): ?string
    {
        if ($selected === self::OTHER) {
            $custom = trim((string) $custom);

            return $custom === '' ? null : $custom;
        }

        if ($selected === self::NONE || $selected === self::UNSURE || in_array($selected, self::COMMON, true)) {
            return $selected;
        }

        return null;
    }
}
