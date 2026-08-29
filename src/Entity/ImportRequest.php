<?php

namespace App\Entity;

use App\Repository\ImportRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

    // Nullable: la mayoria de las mercancias vienen consignadas al cliente
    // directo. Si no es nulo, el expediente esta consignado a ese forwarder
    // en vez de al cliente (ver Forwarder y ForwarderMailer).
    #[ORM\ManyToOne(inversedBy: 'importRequests')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Forwarder $forwarder = null;

    #[ORM\Column(length: 255)]
    private ?string $clientReference = null;

    #[ORM\Column(length: 255)]
    private ?string $agencyReference = null;

    #[ORM\Column(length: 255)]
    private ?string $importNumber = null;

    /** "import" o "export": junto con $type determina la secuencia de estados. */
    #[ORM\Column(length: 16)]
    private ?string $direction = null;

    /** "container" o "lcl". */
    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $eta = null;

    // Nullable a proposito: el cliente no suele saber a que recinto va a
    // llegar su mercancia, asi que ya no lo elige al dar de alta la
    // solicitud. Lo asigna el ejecutivo despues, en el alta del pedimento.
    #[ORM\ManyToOne(inversedBy: 'importRequests')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ContainerYard $cr = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    /**
     * Pasos opcionales por los que el expediente si paso.
     *
     * El estatus solo dice donde esta ahora, no por donde vino, asi que sin esto
     * no habria forma de distinguir una inspeccion fuera de puerto realizada de
     * una omitida una vez que el expediente avanza.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $optionalStepsTaken = [];

    /**
     * @var Collection<int, ImportDocument>
     */
    #[ORM\OneToMany(targetEntity: ImportDocument::class, mappedBy: 'reference')]
    private Collection $importDocuments;

    /**
     * @var Collection<int, RequiredDocument>
     */
    #[ORM\OneToMany(targetEntity: RequiredDocument::class, mappedBy: 'reference')]
    private Collection $requiredDocuments;

    /**
     * @var Collection<int, PrevioReport>
     */
    #[ORM\OneToMany(targetEntity: PrevioReport::class, mappedBy: 'reference')]
    private Collection $previoReports;

    /**
     * Fecha real de modulación que reporta el SOIA, no la fecha en que se
     * detectó desde la app.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $moduladoAt = null;

    /**
     * Última vez que se consultó el SOIA (manual o automático), para que el
     * poller no vuelva a consultar antes de los 5 minutos.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSoiaCheckAt = null;

    /**
     * Cuántas veces el poller automático ya consultó el SOIA por este
     * expediente. No cuenta las consultas manuales ("Consultar SOIA"): el
     * límite es para no seguir golpeando el portal indefinidamente si un
     * expediente nunca modula solo, no para limitar al ejecutivo.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $soiaPollAttempts = 0;

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

    /**
     * @var Collection<int, Delivery>
     */
    #[ORM\ManyToMany(targetEntity: Delivery::class, mappedBy: 'references')]
    private Collection $deliveries;

    #[ORM\Column(length: 255)]
    private ?string $goods = null;

    /**
     * Lo que el cliente anticipa sobre la inspección al dar de alta la
     * solicitud (autoridad esperada, "No requiere" o "Por confirmar"). No
     * decide nada por si solo: el certificado real en "Documentos del
     * ejecutivo" sigue siendo lo que gatea "Inspección fuera de puerto".
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $expectedInspectionAuthority = null;

    public function __construct()
    {
        $this->importDocuments = new ArrayCollection();
        $this->requiredDocuments = new ArrayCollection();
        $this->previoReports = new ArrayCollection();
        $this->containers = new ArrayCollection();
        $this->emptyReturns = new ArrayCollection();
        $this->internInvoices = new ArrayCollection();
        $this->operations = new ArrayCollection();
        $this->deliveries = new ArrayCollection();
    }

    /**
     * @return Collection<int, Delivery>
     */
    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    public function addDelivery(Delivery $delivery): static
    {
        if (!$this->deliveries->contains($delivery)) {
            $this->deliveries->add($delivery);
            $delivery->addReference($this);
        }

        return $this;
    }

    public function removeDelivery(Delivery $delivery): static
    {
        if ($this->deliveries->removeElement($delivery)) {
            $delivery->removeReference($this);
        }

        return $this;
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

    public function getForwarder(): ?Forwarder
    {
        return $this->forwarder;
    }

    public function setForwarder(?Forwarder $forwarder): static
    {
        $this->forwarder = $forwarder;

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

    public function getDirection(): ?string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): static
    {
        $this->direction = $direction;

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

    public function getEta(): ?\DateTimeImmutable
    {
        return $this->eta;
    }

    public function setEta(\DateTimeImmutable $eta): static
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
     * @return list<string>
     */
    public function getOptionalStepsTaken(): array
    {
        return $this->optionalStepsTaken;
    }

    public function markOptionalStepTaken(string $status): static
    {
        if (!in_array($status, $this->optionalStepsTaken, true)) {
            $this->optionalStepsTaken[] = $status;
        }

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
     * @return Collection<int, RequiredDocument>
     */
    public function getRequiredDocuments(): Collection
    {
        return $this->requiredDocuments;
    }

    public function addRequiredDocument(RequiredDocument $requiredDocument): static
    {
        if (!$this->requiredDocuments->contains($requiredDocument)) {
            $this->requiredDocuments->add($requiredDocument);
            $requiredDocument->setReference($this);
        }

        return $this;
    }

    public function removeRequiredDocument(RequiredDocument $requiredDocument): static
    {
        if ($this->requiredDocuments->removeElement($requiredDocument)) {
            if ($requiredDocument->getReference() === $this) {
                $requiredDocument->setReference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PrevioReport>
     */
    public function getPrevioReports(): Collection
    {
        return $this->previoReports;
    }

    public function addPrevioReport(PrevioReport $previoReport): static
    {
        if (!$this->previoReports->contains($previoReport)) {
            $this->previoReports->add($previoReport);
            $previoReport->setReference($this);
        }

        return $this;
    }

    public function removePrevioReport(PrevioReport $previoReport): static
    {
        if ($this->previoReports->removeElement($previoReport)) {
            if ($previoReport->getReference() === $this) {
                $previoReport->setReference(null);
            }
        }

        return $this;
    }

    public function getModuladoAt(): ?\DateTimeImmutable
    {
        return $this->moduladoAt;
    }

    public function setModuladoAt(?\DateTimeImmutable $moduladoAt): static
    {
        $this->moduladoAt = $moduladoAt;

        return $this;
    }

    public function getLastSoiaCheckAt(): ?\DateTimeImmutable
    {
        return $this->lastSoiaCheckAt;
    }

    public function setLastSoiaCheckAt(?\DateTimeImmutable $lastSoiaCheckAt): static
    {
        $this->lastSoiaCheckAt = $lastSoiaCheckAt;

        return $this;
    }

    public function getSoiaPollAttempts(): int
    {
        return $this->soiaPollAttempts;
    }

    public function incrementSoiaPollAttempts(): static
    {
        ++$this->soiaPollAttempts;

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

    public function getGoods(): ?string
    {
        return $this->goods;
    }

    public function setGoods(string $goods): static
    {
        $this->goods = $goods;

        return $this;
    }

    public function getExpectedInspectionAuthority(): ?string
    {
        return $this->expectedInspectionAuthority;
    }

    public function setExpectedInspectionAuthority(?string $expectedInspectionAuthority): static
    {
        $this->expectedInspectionAuthority = $expectedInspectionAuthority;

        return $this;
    }
}
