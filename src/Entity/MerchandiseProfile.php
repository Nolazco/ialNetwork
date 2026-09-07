<?php

namespace App\Entity;

use App\Repository\MerchandiseProfileRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mercancia que un cliente suele mandar, para no volver a capturar clave SAT,
 * unidad o si es estibable en cada instrucción al consolidador de carga (ver
 * ConsolidatorInstruction) — cantidad y peso si cambian por envío, y no viven
 * aqui. Mismo patron que DeliveryPoint: catalogo acotado a una sola Company.
 */
#[ORM\Entity(repositoryClass: MerchandiseProfileRepository::class)]
class MerchandiseProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 255)]
    private ?string $descripcion = null;

    /** Clave de bienes transportados (SAT). */
    #[ORM\Column(length: 255)]
    private ?string $claveSat = null;

    #[ORM\Column(length: 255)]
    private ?string $claveUnidad = null;

    #[ORM\Column(length: 255)]
    private ?string $unidad = null;

    #[ORM\Column]
    private bool $estibable = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getClaveSat(): ?string
    {
        return $this->claveSat;
    }

    public function setClaveSat(string $claveSat): static
    {
        $this->claveSat = $claveSat;

        return $this;
    }

    public function getClaveUnidad(): ?string
    {
        return $this->claveUnidad;
    }

    public function setClaveUnidad(string $claveUnidad): static
    {
        $this->claveUnidad = $claveUnidad;

        return $this;
    }

    public function getUnidad(): ?string
    {
        return $this->unidad;
    }

    public function setUnidad(string $unidad): static
    {
        $this->unidad = $unidad;

        return $this;
    }

    public function isEstibable(): bool
    {
        return $this->estibable;
    }

    public function setEstibable(bool $estibable): static
    {
        $this->estibable = $estibable;

        return $this;
    }
}
