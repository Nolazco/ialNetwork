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

    #[ORM\Column(type: Types::JSON)]
    private array $attachments = []; // list<array{nombre: string, ruta: string}>

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

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
}
