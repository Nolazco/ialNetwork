<?php

namespace App\Entity;

use App\Repository\CompanyDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyDocumentRepository::class)]
class CompanyDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'companyDocuments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $idCompany = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $documentRoute = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdCompany(): ?Company
    {
        return $this->idCompany;
    }

    public function setIdCompany(?Company $idCompany): static
    {
        $this->idCompany = $idCompany;

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

    public function getDocumentRoute(): ?string
    {
        return $this->documentRoute;
    }

    public function setDocumentRoute(string $documentRoute): static
    {
        $this->documentRoute = $documentRoute;

        return $this;
    }
}
