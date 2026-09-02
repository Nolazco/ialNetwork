<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Unidad (camion/tracto) de la flota de un transportista. La captura el
 * transportista mismo desde su panel de despachos: es quien conoce su propia
 * flota, no la agencia.
 */
#[ORM\Entity(repositoryClass: VehicleRepository::class)]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FreightHauler $hauler = null;

    #[ORM\Column(length: 32)]
    private ?string $plates = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $economicNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHauler(): ?FreightHauler
    {
        return $this->hauler;
    }

    public function setHauler(?FreightHauler $hauler): static
    {
        $this->hauler = $hauler;

        return $this;
    }

    public function getPlates(): ?string
    {
        return $this->plates;
    }

    public function setPlates(string $plates): static
    {
        $this->plates = $plates;

        return $this;
    }

    public function getEconomicNumber(): ?string
    {
        return $this->economicNumber;
    }

    public function setEconomicNumber(?string $economicNumber): static
    {
        $this->economicNumber = $economicNumber;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }
}
