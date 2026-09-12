<?php

namespace App\Entity;

use App\Repository\RectificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rectificación de un pedimento ya pagado ante el SAT: trae su propio numero
 * de pedimento y su propia referencia (ej. RZ2608244), distintos de los del
 * pedimento original. Puede pasar en cualquier momento despues del primer
 * pago —no en un punto fijo del roadmap— y un mismo expediente puede tener
 * varias rectificaciones sucesivas (RZ..., RRZ..., RRRZ..., segun cuantas
 * veces se haya corregido).
 */
#[ORM\Entity(repositoryClass: RectificationRepository::class)]
class Rectification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rectifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    #[ORM\Column(length: 255)]
    private ?string $agencyReference = null;

    #[ORM\Column(length: 255)]
    private ?string $importNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $fullPedimentoRoute = null;

    #[ORM\Column(length: 255)]
    private ?string $simplifiedPedimentoRoute = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?ImportRequest
    {
        return $this->reference;
    }

    public function setReference(?ImportRequest $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getAgencyReference(): ?string
    {
        return $this->agencyReference;
    }

    public function setAgencyReference(string $agencyReference): static
    {
        $this->agencyReference = $agencyReference;

        return $this;
    }

    public function getImportNumber(): ?string
    {
        return $this->importNumber;
    }

    public function setImportNumber(string $importNumber): static
    {
        $this->importNumber = $importNumber;

        return $this;
    }

    public function getFullPedimentoRoute(): ?string
    {
        return $this->fullPedimentoRoute;
    }

    public function setFullPedimentoRoute(string $fullPedimentoRoute): static
    {
        $this->fullPedimentoRoute = $fullPedimentoRoute;

        return $this;
    }

    public function getSimplifiedPedimentoRoute(): ?string
    {
        return $this->simplifiedPedimentoRoute;
    }

    public function setSimplifiedPedimentoRoute(string $simplifiedPedimentoRoute): static
    {
        $this->simplifiedPedimentoRoute = $simplifiedPedimentoRoute;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
