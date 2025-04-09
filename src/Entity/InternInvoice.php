<?php

namespace App\Entity;

use App\Repository\InternInvoiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InternInvoiceRepository::class)]
class InternInvoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'internInvoices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    #[ORM\Column(length: 255)]
    private ?string $concept = null;

    #[ORM\Column(length: 255)]
    private ?string $route = null;

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

    public function getConcept(): ?string
    {
        return $this->concept;
    }

    public function setConcept(string $concept): static
    {
        $this->concept = $concept;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(string $route): static
    {
        $this->route = $route;

        return $this;
    }
}
