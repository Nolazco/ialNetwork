<?php

namespace App\Entity;

use App\Repository\AssociatedRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Afiliacion de un cliente a una empresa.
 *
 * Es lo que decide que expedientes, documentos y cuentas de gastos ve cada
 * cliente, asi que no puede concederse sola: una afiliacion nace pendiente y
 * solo da acceso cuando la agencia la aprueba.
 */
#[ORM\Entity(repositoryClass: AssociatedRepository::class)]
class Associated
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private string $status = self::PENDING;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
