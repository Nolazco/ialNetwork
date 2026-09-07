<?php

namespace App\Entity;

use App\Repository\LegacyPrevioReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Reporte de previo entrado por el puente público /legacy/reportes (sin
 * sesión, sin expediente real): a diferencia de PrevioReport, no hay
 * ImportRequest al que ligarse, así que referencia/cliente/correo/tipo de
 * carga son texto libre, igual que en el formulario viejo (previos.html).
 */
#[ORM\Entity(repositoryClass: LegacyPrevioReportRepository::class)]
class LegacyPrevioReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $referencia = null;

    #[ORM\Column(length: 255)]
    private ?string $cliente = null;

    #[ORM\Column(length: 255)]
    private ?string $correo = null;

    #[ORM\Column(length: 255)]
    private ?string $cargoType = null; // container / lcl

    #[ORM\Column(length: 255)]
    private ?string $type = null; // Inspección / Ocular / Desycon

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authority = null;

    #[ORM\Column(length: 255)]
    private ?string $place = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

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

    #[ORM\Column(type: Types::JSON)]
    private array $goods = []; // list<string>

    #[ORM\Column(type: Types::JSON)]
    private array $lots = []; // list<array{identificador: string, observaciones: string}>

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReferencia(): ?string
    {
        return $this->referencia;
    }

    public function setReferencia(string $referencia): static
    {
        $this->referencia = $referencia;

        return $this;
    }

    public function getCliente(): ?string
    {
        return $this->cliente;
    }

    public function setCliente(string $cliente): static
    {
        $this->cliente = $cliente;

        return $this;
    }

    public function getCorreo(): ?string
    {
        return $this->correo;
    }

    public function setCorreo(string $correo): static
    {
        $this->correo = $correo;

        return $this;
    }

    public function getCargoType(): ?string
    {
        return $this->cargoType;
    }

    public function setCargoType(string $cargoType): static
    {
        $this->cargoType = $cargoType;

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
}
