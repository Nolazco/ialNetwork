<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $idUser = null;

    /**
     * @var Collection<int, Associated>
     */
    #[ORM\ManyToMany(targetEntity: Associated::class, mappedBy: 'idClient')]
    private Collection $associateds;

    public function __construct()
    {
        $this->associateds = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdUser(): ?User
    {
        return $this->idUser;
    }

    public function setIdUser(User $idUser): static
    {
        $this->idUser = $idUser;

        return $this;
    }

    /**
     * @return Collection<int, Associated>
     */
    public function getAssociateds(): Collection
    {
        return $this->associateds;
    }

    public function addAssociated(Associated $associated): static
    {
        if (!$this->associateds->contains($associated)) {
            $this->associateds->add($associated);
            $associated->addIdClient($this);
        }

        return $this;
    }

    public function removeAssociated(Associated $associated): static
    {
        if ($this->associateds->removeElement($associated)) {
            $associated->removeIdClient($this);
        }

        return $this;
    }
}
