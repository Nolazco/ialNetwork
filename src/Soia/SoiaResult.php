<?php

namespace App\Soia;

/**
 * Lo que devuelve una consulta al SOIA para un pedimento especifico.
 */
final class SoiaResult
{
    private const RESOLVED_STATES = ['CUMPLIDO', 'DESADUANADO'];

    /** El semáforo fiscal seleccionó el pedimento para revisión. */
    private const INSPECTION_STATES = ['RECONOCIMIENTO ADUANERO'];

    public function __construct(
        public readonly bool $found,
        public readonly ?string $estado = null,
        public readonly ?\DateTimeImmutable $fecha = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function notFound(?string $error = null): self
    {
        return new self(false, error: $error);
    }

    public static function of(string $estado, ?\DateTimeImmutable $fecha): self
    {
        return new self(true, $estado, $fecha);
    }

    public function isResolved(): bool
    {
        return $this->found && $this->estado !== null && in_array(strtoupper($this->estado), self::RESOLVED_STATES, true);
    }

    public function isUnderInspection(): bool
    {
        return $this->found && $this->estado !== null && in_array(strtoupper($this->estado), self::INSPECTION_STATES, true);
    }
}
