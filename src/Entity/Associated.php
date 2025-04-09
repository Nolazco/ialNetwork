<?php

namespace App\Entity;

use App\Repository\AssociatedRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssociatedRepository::class)]
class Associated
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Client>
     */
    #[ORM\ManyToMany(targetEntity: Client::class, inversedBy: 'associateds')]
    private Collection $idClient;

    /**
     * @var Collection<int, Company>
     */
    #[ORM\ManyToMany(targetEntity: Company::class, inversedBy: 'associateds')]
    private Collection $idCompany;

    public function __construct()
    {
        $this->idClient = new ArrayCollection();
        $this->idCompany = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Client>
     */
    public function getIdClient(): Collection
    {
        return $this->idClient;
    }

    public function addIdClient(Client $idClient): static
    {
        if (!$this->idClient->contains($idClient)) {
            $this->idClient->add($idClient);
        }

        return $this;
    }

    public function removeIdClient(Client $idClient): static
    {
        $this->idClient->removeElement($idClient);

        return $this;
    }

    /**
     * @return Collection<int, Company>
     */
    public function getIdCompany(): Collection
    {
        return $this->idCompany;
    }

    public function addIdCompany(Company $idCompany): static
    {
        if (!$this->idCompany->contains($idCompany)) {
            $this->idCompany->add($idCompany);
        }

        return $this;
    }

    public function removeIdCompany(Company $idCompany): static
    {
        $this->idCompany->removeElement($idCompany);

        return $this;
    }
}
