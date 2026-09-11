<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

    #[ORM\Column(length: 255)]
    private ?string $rfc = null;

    /**
     * Correos de trafico/logistica de la empresa: reciben las alertas del
     * expediente (modulado, vacío devuelto, instrucciones a XCF, etc.) — todo
     * lo que no sea el aviso al transportista del camion (ver DeliveryMailer,
     * que usa su propia lista por aduana, no este campo).
     *
     * Es la unica fuente de "a quien le toca enterarse" del lado del
     * cliente: ya no importa quien tenga cuenta aprobada en el portal (ver
     * RecipientResolver::traficoEmails()) — antes se avisaba a todos los
     * usuarios afiliados y aprobados, ahora solo a quien la propia empresa
     * ponga aqui.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $trafico = [];

    /**
     * Correos de compras de la empresa: reciben las solicitudes de
     * clasificación de mercancía — nada mas (ver ClassificationMailer).
     * Antes era un solo correo suelto (classificationContactEmail); ahora es
     * una lista, igual que $trafico.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $compras = [];

    /**
     * Numeros de WhatsApp de la empresa: reciben el mismo tipo de aviso que
     * $trafico (modulado, reconocimiento aduanero), pero en una version corta
     * sin jerga tecnica, pensada para el cliente final (ver
     * ModuladoConfirmer). Vacio = no se manda WhatsApp a esta empresa.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $whatsapp = [];

    /**
     * Campos nullable de aqui en adelante: solo hacen falta para llenar el
     * bloque "facturador" de las instrucciones al consolidador de carga (ver
     * ConsolidatorInstruction) — nombre/rfc ya existen arriba y se reusan.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $extNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $intNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $neighborhood = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $locality = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $municipality = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $zipCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactEmail = null;

    /**
     * @var Collection<int, CompanyDocument>
     */
    #[ORM\OneToMany(targetEntity: CompanyDocument::class, mappedBy: 'idCompany')]
    private Collection $companyDocuments;

    /**
     * @var Collection<int, Associated>
     */
    #[ORM\OneToMany(targetEntity: Associated::class, mappedBy: 'idCompany')]
    private Collection $associateds;

    /**
     * @var Collection<int, ImportRequest>
     */
    #[ORM\OneToMany(targetEntity: ImportRequest::class, mappedBy: 'idCompany')]
    private Collection $importRequests;

    public function __construct()
    {
        $this->companyDocuments = new ArrayCollection();
        $this->associateds = new ArrayCollection();
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
     * @return list<string>
     */
    public function getTrafico(): array
    {
        return $this->trafico;
    }

    /**
     * @param list<string> $trafico
     */
    public function setTrafico(array $trafico): static
    {
        $this->trafico = $trafico;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCompras(): array
    {
        return $this->compras;
    }

    /**
     * @param list<string> $compras
     */
    public function setCompras(array $compras): static
    {
        $this->compras = $compras;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getWhatsapp(): array
    {
        return $this->whatsapp;
    }

    /**
     * @param list<string> $whatsapp
     */
    public function setWhatsapp(array $whatsapp): static
    {
        $this->whatsapp = $whatsapp;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getExtNumber(): ?string
    {
        return $this->extNumber;
    }

    public function setExtNumber(?string $extNumber): static
    {
        $this->extNumber = $extNumber;

        return $this;
    }

    public function getIntNumber(): ?string
    {
        return $this->intNumber;
    }

    public function setIntNumber(?string $intNumber): static
    {
        $this->intNumber = $intNumber;

        return $this;
    }

    public function getNeighborhood(): ?string
    {
        return $this->neighborhood;
    }

    public function setNeighborhood(?string $neighborhood): static
    {
        $this->neighborhood = $neighborhood;

        return $this;
    }

    public function getLocality(): ?string
    {
        return $this->locality;
    }

    public function setLocality(?string $locality): static
    {
        $this->locality = $locality;

        return $this;
    }

    public function getMunicipality(): ?string
    {
        return $this->municipality;
    }

    public function setMunicipality(?string $municipality): static
    {
        $this->municipality = $municipality;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(?string $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): static
    {
        $this->contactName = $contactName;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

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
