<?php

namespace App\Entity;

use App\Repository\ContainerYardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContainerYardRepository::class)]
class ContainerYard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 10)]
    private ?string $cr = null;

    /**
     * @var Collection<int, ImportRequest>
     */
    #[ORM\OneToMany(targetEntity: ImportRequest::class, mappedBy: 'cr')]
    private Collection $importRequests;

    /**
     * @var Collection<int, EmptyReturn>
     */
    #[ORM\OneToMany(targetEntity: EmptyReturn::class, mappedBy: 'yard')]
    private Collection $emptyReturns;

    public function __construct()
    {
        $this->importRequests = new ArrayCollection();
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

    public function getCr(): ?string
    {
        return $this->cr;
    }

    public function setCr(string $cr): static
    {
        $this->cr = $cr;

        return $this;
    }

    /**
     * @return Collection<int, ImportRequest>
     */
    public function getImportRequests(): Collection
    {
        return $this->importRequests;
    }

    public function addImportRequest(ImportRequest $importRequest): static
    {
        if (!$this->importRequests->contains($importRequest)) {
            $this->importRequests->add($importRequest);
            $importRequest->setCr($this);
        }

        return $this;
    }

    public function removeImportRequest(ImportRequest $importRequest): static
    {
        if ($this->importRequests->removeElement($importRequest)) {
            // set the owning side to null (unless already changed)
            if ($importRequest->getCr() === $this) {
                $importRequest->setCr(null);
            }
        }

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
            // set the owning side to null (unless already changed)
            if ($emptyReturn->getYard() === $this) {
                $emptyReturn->setYard(null);
            }
        }

        return $this;
    }
}
