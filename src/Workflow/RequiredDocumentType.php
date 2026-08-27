<?php

namespace App\Workflow;

/**
 * Documentos que sube el ejecutivo (no el cliente) para respaldar el avance
 * del expediente por sus etapas.
 *
 * Salvo ADVANCE_REQUEST, cada tipo es un solo slot por expediente: subir uno
 * nuevo reemplaza al anterior. ADVANCE_REQUEST admite varios, porque el
 * anticipo inicial suele llevar complementos.
 */
final class RequiredDocumentType
{
    public const PROFORMA = 'Proforma del pedimento';
    public const ADVANCE_REQUEST = 'Solicitud de anticipo';
    public const REVALIDATED_BL = 'BL revalidado';
    public const FULL_PEDIMENTO = 'Pedimento completo';
    public const SIMPLIFIED_PEDIMENTO = 'Pedimento simplificado';
    public const SCHEDULE_PROOF = 'Comprobante de cita';
    public const INSPECTION_CERTIFICATE = 'Certificado de inspección';

    /**
     * Tipos de slot único: subir uno nuevo reemplaza al que ya existiera.
     *
     * @var list<string>
     */
    public const SINGLE_SLOT = [
        self::PROFORMA,
        self::REVALIDATED_BL,
        self::FULL_PEDIMENTO,
        self::SIMPLIFIED_PEDIMENTO,
        self::SCHEDULE_PROOF,
        self::INSPECTION_CERTIFICATE,
    ];

    public function isSingleSlot(string $type): bool
    {
        return in_array($type, self::SINGLE_SLOT, true);
    }
}
