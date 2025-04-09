<?php

namespace App\Entity;

use App\Repository\ImportRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportRequestRepository::class)]
class ImportRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'importRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $idCompany = null;

    #[ORM\ManyToOne(inversedBy: 'importRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Provider $idProvider = null;

    #[ORM\Column(length: 255)]
    private ?string $clientReference = null;

    #[ORM\Column(length: 255)]
    private ?string $agencyReference = null;

    #[ORM\Column(length: 255)]
    private ?string $importNumber = null;

    #[ORM\Column]
    private ?int $type = null;

    #[ORM\Column(length: 255)]
    private ?string $eta = null;

    #[ORM\ManyToOne(inversedBy: 'importRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ContainerYard $cr = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    /**
     * @var Collection<int, ImportDocument>
     */
    #[ORM\OneToMany(targetEntity: ImportDocument::class, mappedBy: 'reference')]
    private Collection $importDocuments;

    /**
     * @var Collection<int, Container>
     */
    #[ORM\ManyToMany(targetEntity: Container::class, mappedBy: 'reference')]
    private Collection $containers;

    /**
     * @var Collection<int, EmptyReturn>
     */
    #[ORM\OneToMany(targetEntity: EmptyReturn::class, mappedBy: 'reference')]
    private Collection $emptyReturns;

    /**
     * @var Collection<int, InternInvoice>
     */
    #[ORM\OneToMany(targetEntity: InternInvoice::class, mappedBy: 'reference')]
    private Collection $internInvoices;

    /**
     * @var Collection<int, Operation>
     */
    #[ORM\OneToMany(targetEntity: Operation::class, mappedBy: 'reference')]
    private Collection $operations;

    public function __construct()
    {
        $this->importDocuments = new ArrayCollection();
        $this->containers = new ArrayCollection();
        $this->emptyReturns = new ArrayCollection();
        $this->internInvoices = new ArrayCollection();
        $this->operations = new ArrayCollection();
    }

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

    public function getIdProvider(): ?Provider
    {
        return $this->idProvider;
    }

    public function setIdProvider(?Provider $idProvider): static
    {
        $this->idProvider = $idProvider;

        return $this;
    }

    public function getClientReference(): ?string
    {
        return $this->clientReference;
    }

    public function setClientReference(string $clientReference): static
    {
        $this->clientReference = $clientReference;

        return $this;
    }

    public function getAgencyReference(): ?string
    {
        return $this->agencyReference;
    }

    public function setAgencyReference(string $agencyReference): static
    {
        $this->agencyReference = $agencyReference;

        return $this;
    }

    public function getImportNumber(): ?string
    {
        return $this->importNumber;
    }

    public function setImportNumber(string $importNumber): static
    {
        $this->importNumber = $importNumber;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getEta(): ?string
    {
        return $this->eta;
    }

    public function setEta(string $eta): static
    {
        $this->eta = $eta;

        return $this;
    }

    public function getCr(): ?ContainerYard
    {
        return $this->cr;
    }

    public function setCr(?ContainerYard $cr): static
    {
        $this->cr = $cr;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, ImportDocument>
     */
    public function getImportDocuments(): Collection
    {
        return $this->importDocuments;
    }

    public function addImportDocument(ImportDocument $importDocument): static
    {
        if (!$this->importDocuments->contains($importDocument)) {
            $this->importDocuments->add($importDocument);
            $importDocument->setReference($this);
        }

        return $this;
    }

    public function removeImportDocument(ImportDocument $importDocument): static
    {
        if ($this->importDocuments->removeElement($importDocument)) {
            // set the owning side to null (unless already changed)
            if ($importDocument->getReference() === $this) {
                $importDocument->setReference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Container>
     */
    public function getContainers(): Collection
    {
        return $this->containers;
    }

    public function addContainer(Container $container): static
    {
        if (!$this->containers->contains($container)) {
            $this->containers->add($container);
            $container->addReference($this);
        }

        return $this;
    }

    public function removeContainer(Container $container): static
    {
        if ($this->containers->removeElement($container)) {
            $container->removeReference($this);
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
            $emptyReturn->setReference($this);
        }

        return $this;
    }

    public function removeEmptyReturn(EmptyReturn $emptyReturn): static
    {
        if ($this->emptyReturns->removeElement($emptyReturn)) {
            // set the owning side to null (unless already changed)
            if ($emptyReturn->getReference() === $this) {
                $emptyReturn->setReference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InternInvoice>
     */
    public function getInternInvoices(): Collection
    {
        return $this->internInvoices;
    }

    public function addInternInvoice(InternInvoice $internInvoice): static
    {
        if (!$this->internInvoices->contains($internInvoice)) {
            $this->internInvoices->add($internInvoice);
            $internInvoice->setReference($this);
        }

        return $this;
    }

    public function removeInternInvoice(InternInvoice $internInvoice): static
    {
        if ($this->internInvoices->removeElement($internInvoice)) {
            // set the owning side to null (unless already changed)
            if ($internInvoice->getReference() === $this) {
                $internInvoice->setReference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Operation>
     */
    public function getOperations(): Collection
    {
        return $this->operations;
    }

    public function addOperation(Operation $operation): static
    {
        if (!$this->operations->contains($operation)) {
            $this->operations->add($operation);
            $operation->setReference($this);
        }

        return $this;
    }

    public function removeOperation(Operation $operation): static
    {
        if ($this->operations->removeElement($operation)) {
            // set the owning side to null (unless already changed)
            if ($operation->getReference() === $this) {
                $operation->setReference(null);
            }
        }

        return $this;
    }
}
