<?php

namespace App\Entity;

use App\Repository\ClassificationRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClassificationRequestRepository::class)]
class ClassificationRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $requestedBy = null;

    #[ORM\Column(length: 255)]
    private ?string $merchandiseName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $chemicalName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $casNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $merchandiseUse = null;

    #[ORM\Column(length: 255)]
    private ?string $presentation = null;

    /**
     * Documentos de soporte. "tipo" distingue los que vienen con la
     * solicitud original ('solicitud', el valor de siempre — también el que
     * asume cualquier fila vieja sin esa clave) de los que el ejecutivo
     * adjunta al confirmar la fracción, normalmente el correo del
     * clasificador ('confirmacion').
     *
     * @var list<array{nombre: string, ruta: string, tipo?: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $attachments = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Fracción arancelaria que confirmó el equipo de clasificadores. Nullable
     * a propósito: la respuesta llega por su propio correo (ver
     * ClassificationMailer), no por la app, así que un ejecutivo la captura
     * aquí cuando llega — mientras sea null, la solicitud sigue pendiente.
     * Es lo que permite buscar mercancía ya clasificada y no repetir el
     * trabajo con el clasificador.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $confirmedTariffFraction = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $confirmedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    /**
     * La justificación completa que manda el clasificador junto con la
     * fracción (regulaciones, anexos, tasas...) — el ejecutivo la pega tal
     * cual del correo al confirmar. Ya llega saneada (ver
     * DashboardClassifications::confirmTariffFraction() y
     * config/packages/html_sanitizer.yaml), así que es segura de mostrar
     * directamente. Nullable: no siempre viene una justificación tan
     * detallada, a veces solo la fracción.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $confirmedJustification = null;

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

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): static
    {
        $this->requestedBy = $requestedBy;

        return $this;
    }

    public function getMerchandiseName(): ?string
    {
        return $this->merchandiseName;
    }

    public function setMerchandiseName(string $merchandiseName): static
    {
        $this->merchandiseName = $merchandiseName;

        return $this;
    }

    public function getChemicalName(): ?string
    {
        return $this->chemicalName;
    }

    public function setChemicalName(?string $chemicalName): static
    {
        $this->chemicalName = $chemicalName;

        return $this;
    }

    public function getCasNumber(): ?string
    {
        return $this->casNumber;
    }

    public function setCasNumber(?string $casNumber): static
    {
        $this->casNumber = $casNumber;

        return $this;
    }

    public function getMerchandiseUse(): ?string
    {
        return $this->merchandiseUse;
    }

    public function setMerchandiseUse(string $merchandiseUse): static
    {
        $this->merchandiseUse = $merchandiseUse;

        return $this;
    }

    public function getPresentation(): ?string
    {
        return $this->presentation;
    }

    public function setPresentation(string $presentation): static
    {
        $this->presentation = $presentation;

        return $this;
    }

    /**
     * @return list<array{nombre: string, ruta: string}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param list<array{nombre: string, ruta: string}> $attachments
     */
    public function setAttachments(array $attachments): static
    {
        $this->attachments = $attachments;

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

    public function getConfirmedTariffFraction(): ?string
    {
        return $this->confirmedTariffFraction;
    }

    public function setConfirmedTariffFraction(?string $confirmedTariffFraction): static
    {
        $this->confirmedTariffFraction = $confirmedTariffFraction;

        return $this;
    }

    public function getConfirmedBy(): ?User
    {
        return $this->confirmedBy;
    }

    public function setConfirmedBy(?User $confirmedBy): static
    {
        $this->confirmedBy = $confirmedBy;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedTariffFraction !== null;
    }

    public function getConfirmedJustification(): ?string
    {
        return $this->confirmedJustification;
    }

    public function setConfirmedJustification(?string $confirmedJustification): static
    {
        $this->confirmedJustification = $confirmedJustification;

        return $this;
    }
}
