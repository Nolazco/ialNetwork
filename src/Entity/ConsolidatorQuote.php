<?php

namespace App\Entity;

use App\Repository\ConsolidatorQuoteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Solicitud de cotización a la empresa de fletes consolidados (XCF, hasta
 * ahora la unica) por la mercancia de un expediente — se manda antes de
 * comprometerse con un transportista, a diferencia de ConsolidatorInstruction
 * (que ya trae transporte asignado y genera el papeleo de entrega). No
 * cambia el estatus del expediente ni depende de el.
 */
#[ORM\Entity(repositoryClass: ConsolidatorQuoteRepository::class)]
class ConsolidatorQuote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'consolidatorQuotes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    /**
     * El destinatario final al que XCF entregaria. Nullable: null significa
     * domicilio fiscal de la empresa del expediente (mismo patron que
     * ConsolidatorInstruction::$deliveryPoint).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?DeliveryPoint $deliveryPoint = null;

    #[ORM\Column(length: 255)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 255)]
    private ?string $claveSat = null;

    #[ORM\Column(length: 255)]
    private ?string $unidad = null;

    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column]
    private float $weightKg = 0;

    #[ORM\Column]
    private float $cubicaje = 0;

    /**
     * Codigo del catalogo de XCF (ver MerchandiseTypeCatalog): 01-05.
     */
    #[ORM\Column(length: 2)]
    private ?string $merchandiseType = null;

    #[ORM\Column(type: 'datetime_immutable')]
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

    public function getDeliveryPoint(): ?DeliveryPoint
    {
        return $this->deliveryPoint;
    }

    public function setDeliveryPoint(?DeliveryPoint $deliveryPoint): static
    {
        $this->deliveryPoint = $deliveryPoint;

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

    public function getUnidad(): ?string
    {
        return $this->unidad;
    }

    public function setUnidad(string $unidad): static
    {
        $this->unidad = $unidad;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getWeightKg(): float
    {
        return $this->weightKg;
    }

    public function setWeightKg(float $weightKg): static
    {
        $this->weightKg = $weightKg;

        return $this;
    }

    public function getCubicaje(): float
    {
        return $this->cubicaje;
    }

    public function setCubicaje(float $cubicaje): static
    {
        $this->cubicaje = $cubicaje;

        return $this;
    }

    public function getMerchandiseType(): ?string
    {
        return $this->merchandiseType;
    }

    public function setMerchandiseType(string $merchandiseType): static
    {
        $this->merchandiseType = $merchandiseType;

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
