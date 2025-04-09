<?php

namespace App\Entity;

use App\Repository\ContainerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContainerRepository::class)]
class Container
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, ImportRequest>
     */
    #[ORM\ManyToMany(targetEntity: ImportRequest::class, inversedBy: 'containers')]
    private Collection $reference;

    #[ORM\Column(length: 255)]
    private ?string $num = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    public function __construct()
    {
        $this->reference = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, ImportRequest>
     */
    public function getReference(): Collection
    {
        return $this->reference;
    }

    public function addReference(ImportRequest $reference): static
    {
        if (!$this->reference->contains($reference)) {
            $this->reference->add($reference);
        }

        return $this;
    }

    public function removeReference(ImportRequest $reference): static
    {
        $this->reference->removeElement($reference);

        return $this;
    }

    public function getNum(): ?string
    {
        return $this->num;
    }

    public function setNum(string $num): static
    {
        $this->num = $num;

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
}
