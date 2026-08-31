<?php

namespace App\Entity;

use App\Repository\DeliveryPointRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Almacen/punto de entrega del catalogo propio de una empresa. Cada cliente
 * tiene su propia red de bodegas, asi que este catalogo esta acotado a una
 * sola Company (a diferencia de Provider/Forwarder, que son de toda la
 * agencia) — primer catalogo de este tipo en el proyecto.
 */
#[ORM\Entity(repositoryClass: DeliveryPointRepository::class)]
class DeliveryPoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }
}
