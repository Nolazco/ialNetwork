<?php

namespace App\Entity;

use App\Repository\EmptyReturnYardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Patio especializado en devolucion de contenedores vacios — catalogo aparte
 * de ContainerYard (los recintos fiscalizados que se asignan en el alta del
 * pedimento): son lugares distintos aunque antes se manejaban con la misma
 * entidad.
 */
#[ORM\Entity(repositoryClass: EmptyReturnYardRepository::class)]
class EmptyReturnYard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, EmptyReturn>
     */
    #[ORM\OneToMany(targetEntity: EmptyReturn::class, mappedBy: 'yard')]
    private Collection $emptyReturns;

    public function __construct()
    {
        $this->emptyReturns = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return Collection<int, EmptyReturn>
     */
    public function getEmptyReturns(): Collection
    {
        return $this->emptyReturns;
    }

    public function addEmptyReturn(EmptyReturn $emptyReturn): static
    {
        if (!$this->emptyReturns->contains($emptyReturn)) {
            $this->emptyReturns->add($emptyReturn);
            $emptyReturn->setYard($this);
        }

        return $this;
    }

    public function removeEmptyReturn(EmptyReturn $emptyReturn): static
    {
        if ($this->emptyReturns->removeElement($emptyReturn)) {
            if ($emptyReturn->getYard() === $this) {
                $emptyReturn->setYard(null);
            }
        }

        return $this;
    }
}
