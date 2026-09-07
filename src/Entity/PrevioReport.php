<?php

namespace App\Entity;

use App\Repository\PrevioReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Reporte de un previo/inspección hecho sobre la mercancía de un expediente.
 *
 * Vive aparte de Operation (las "maniobras"): un previo trae mucho mas detalle
 * (lugar, horarios, sellos, mercancia, fotos...) del que Operation modela hoy,
 * y no toda maniobra es un previo con reporte formal.
 */
#[ORM\Entity(repositoryClass: PrevioReportRepository::class)]
class PrevioReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'previoReports')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    /** Inspección, Ocular o Desycon. */
    #[ORM\Column(length: 255)]
    private ?string $type = null;

    /** Autoridades elegidas + "otra" a mano, unidas por coma. Solo si type = Inspección. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authority = null;

    /**
     * Lugar donde ocurrió el previo. No es el recinto asignado al
     * expediente: un previo puede pasar en otro sitio.
     */
    #[ORM\Column(length: 255)]
    private ?string $place = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

    /** Solo si el expediente es de contenedor. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $containerNum = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sealOrigin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sealFinal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $plates = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transportCompanyName = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $goods = [];

    /**
     * @var list<array{identificador: string, observaciones: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $lots = [];

    /** Cajas/Pallets/Cuñetes/otra a mano, unidas por coma. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $presentation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 255)]
    private ?string $pdfRoute = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photosZipRoute = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAuthority(): ?string
    {
        return $this->authority;
    }

    public function setAuthority(?string $authority): static
    {
        $this->authority = $authority;

        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setPlace(string $place): static
    {
        $this->place = $place;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getContainerNum(): ?string
    {
        return $this->containerNum;
    }

    public function setContainerNum(?string $containerNum): static
    {
        $this->containerNum = $containerNum;

        return $this;
    }

    public function getSealOrigin(): ?string
    {
        return $this->sealOrigin;
    }

    public function setSealOrigin(?string $sealOrigin): static
    {
        $this->sealOrigin = $sealOrigin;

        return $this;
    }

    public function getSealFinal(): ?string
    {
        return $this->sealFinal;
    }

    public function setSealFinal(?string $sealFinal): static
    {
        $this->sealFinal = $sealFinal;

        return $this;
    }

    public function getPlates(): ?string
    {
        return $this->plates;
    }

    public function setPlates(?string $plates): static
    {
        $this->plates = $plates;

        return $this;
    }

    public function getTransportCompanyName(): ?string
    {
        return $this->transportCompanyName;
    }

    public function setTransportCompanyName(?string $transportCompanyName): static
    {
        $this->transportCompanyName = $transportCompanyName;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getGoods(): array
    {
        return $this->goods;
    }

    /**
     * @param list<string> $goods
     */
    public function setGoods(array $goods): static
    {
        $this->goods = $goods;

        return $this;
    }

    /**
     * @return list<array{identificador: string, observaciones: string}>
     */
    public function getLots(): array
    {
        return $this->lots;
    }

    /**
     * @param list<array{identificador: string, observaciones: string}> $lots
     */
    public function setLots(array $lots): static
    {
        $this->lots = $lots;

        return $this;
    }

    public function getPresentation(): ?string
    {
        return $this->presentation;
    }

    public function setPresentation(?string $presentation): static
    {
        $this->presentation = $presentation;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(?string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getPdfRoute(): ?string
    {
        return $this->pdfRoute;
    }

    public function setPdfRoute(string $pdfRoute): static
    {
        $this->pdfRoute = $pdfRoute;

        return $this;
    }

    public function getPhotosZipRoute(): ?string
    {
        return $this->photosZipRoute;
    }

    public function setPhotosZipRoute(?string $photosZipRoute): static
    {
        $this->photosZipRoute = $photosZipRoute;

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
