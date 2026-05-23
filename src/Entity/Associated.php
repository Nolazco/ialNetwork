<?php

namespace App\Entity;

use App\Repository\AssociatedRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssociatedRepository::class)]
class Associated
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'associateds')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $idClient = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'associateds')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $idCompany = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdClient(): ?User
    {
        return $this->idClient;
    }

    public function setIdClient(?User $idClient): self
    {
        $this->idClient = $idClient;
        return $this;
    }

    public function getIdCompany(): ?Company
    {
        return $this->idCompany;
    }

    public function setIdCompany(?Company $idCompany): self
    {
        $this->idCompany = $idCompany;
        return $this;
    }
}
