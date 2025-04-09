<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 13)]
    private ?string $rfc = null;

    /**
     * @var Collection<int, Associated>
     */
    #[ORM\ManyToMany(targetEntity: Associated::class, mappedBy: 'idCompany')]
    private Collection $associateds;

    /**
     * @var Collection<int, CompanyDocument>
     */
    #[ORM\OneToMany(targetEntity: CompanyDocument::class, mappedBy: 'idCompany')]
    private Collection $companyDocuments;

    /**
     * @var Collection<int, ImportRequest>
     */
    #[ORM\OneToMany(targetEntity: ImportRequest::class, mappedBy: 'idCompany')]
    private Collection $importRequests;

    public function __construct()
    {
        $this->associateds = new ArrayCollection();
        $this->companyDocuments = new ArrayCollection();
        $this->importRequests = new ArrayCollection();
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getRfc(): ?string
    {
        return $this->rfc;
    }

    public function setRfc(string $rfc): static
    {
        $this->rfc = $rfc;

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
            $associated->addIdCompany($this);
        }

        return $this;
    }

    public function removeAssociated(Associated $associated): static
    {
        if ($this->associateds->removeElement($associated)) {
            $associated->removeIdCompany($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, CompanyDocument>
     */
    public function getCompanyDocuments(): Collection
    {
        return $this->companyDocuments;
    }

    public function addCompanyDocument(CompanyDocument $companyDocument): static
    {
        if (!$this->companyDocuments->contains($companyDocument)) {
            $this->companyDocuments->add($companyDocument);
            $companyDocument->setIdCompany($this);
        }

        return $this;
    }

    public function removeCompanyDocument(CompanyDocument $companyDocument): static
    {
        if ($this->companyDocuments->removeElement($companyDocument)) {
            // set the owning side to null (unless already changed)
            if ($companyDocument->getIdCompany() === $this) {
                $companyDocument->setIdCompany(null);
            }
        }

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
            $importRequest->setIdCompany($this);
        }

        return $this;
    }

    public function removeImportRequest(ImportRequest $importRequest): static
    {
        if ($this->importRequests->removeElement($importRequest)) {
            // set the owning side to null (unless already changed)
            if ($importRequest->getIdCompany() === $this) {
                $importRequest->setIdCompany(null);
            }
        }

        return $this;
    }
}
