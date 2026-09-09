<?php

namespace App\Entity;

use App\Repository\DeliveryPointRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Almacen/punto de entrega del catalogo propio de un cliente. Cada cliente
 * tiene su propia red de bodegas (a diferencia de Provider/Forwarder, que son
 * de toda la agencia) — primer catalogo de este tipo en el proyecto.
 *
 * Un mismo punto puede pertenecer a varias Company a la vez: un cliente con
 * mas de una empresa suele compartir la misma bodega entre ellas, y antes
 * tenia que darla de alta por duplicado en cada una (ver
 * DashboardDeliveryPoints, que administra ese compartir).
 */
#[ORM\Entity(repositoryClass: DeliveryPointRepository::class)]
class DeliveryPoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Company>
     */
    #[ORM\ManyToMany(targetEntity: Company::class)]
    #[ORM\JoinTable(name: 'delivery_point_company')]
    private Collection $companies;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    /**
     * Campos nullable de aqui en adelante: solo hacen falta para llenar el
     * bloque "destinatario" de las instrucciones al consolidador de carga
     * (ver ConsolidatorInstruction), no para el uso normal de "Entregar en"
     * al dar de alta un expediente — $name/$address siguen bastando ahi.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rfc = null;

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

    public function __construct()
    {
        $this->companies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Company>
     */
    public function getCompanies(): Collection
    {
        return $this->companies;
    }

    public function addCompany(Company $company): static
    {
        if (!$this->companies->contains($company)) {
            $this->companies->add($company);
        }

        return $this;
    }

    public function removeCompany(Company $company): static
    {
        $this->companies->removeElement($company);

        return $this;
    }

    public function belongsTo(Company $company): bool
    {
        return $this->companies->contains($company);
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

    public function setRfc(?string $rfc): static
    {
        $this->rfc = $rfc;

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
}
