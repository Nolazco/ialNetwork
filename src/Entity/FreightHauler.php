<?php

namespace App\Entity;

use App\Repository\FreightHaulerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FreightHaulerRepository::class)]
class FreightHauler
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Sin cascade remove: dar de baja al transportista no debe borrar la cuenta
    // de usuario, que es una entidad independiente. ManyToOne (no OneToOne):
    // un mismo usuario transportista puede operar varias empresas de
    // transporte, cada una con su propia flota y despachos.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $id_user = null;

    #[ORM\Column(length: 4)]
    private ?string $caat = null;

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255)]
    private ?string $rfc = null;

    /**
     * Correos adicionales que deben enterarse del aviso de transporte (ver
     * DeliveryMailer), además del correo de la cuenta del dueño
     * ($id_user->getEmail()), que siempre recibe el aviso sin necesidad de
     * agregarlo aquí. Nullable a propósito: la mayoría de los transportistas
     * no necesita ninguno adicional.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $contactEmails = null;

    /**
     * @var Collection<int, Delivery>
     */
    #[ORM\OneToMany(targetEntity: Delivery::class, mappedBy: 'transport')]
    private Collection $deliveries;

    /**
     * @var Collection<int, EmptyReturn>
     */
    #[ORM\OneToMany(targetEntity: EmptyReturn::class, mappedBy: 'transport')]
    private Collection $emptyReturns;

    public function __construct()
    {
        $this->deliveries = new ArrayCollection();
        $this->emptyReturns = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdUser(): ?User
    {
        return $this->id_user;
    }

    public function setIdUser(User $id_user): static
    {
        $this->id_user = $id_user;

        return $this;
    }

    public function getCaat(): ?string
    {
        return $this->caat;
    }

    public function setCaat(string $caat): static
    {
        $this->caat = $caat;

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

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
    public function getContactEmails(): array
    {
        return $this->contactEmails ?? [];
    }

    /**
     * @param list<string> $contactEmails
     */
    public function setContactEmails(array $contactEmails): static
    {
        $this->contactEmails = array_values($contactEmails);

        return $this;
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
            $delivery->setTransport($this);
        }

        return $this;
    }

    public function removeDelivery(Delivery $delivery): static
    {
        if ($this->deliveries->removeElement($delivery)) {
            // set the owning side to null (unless already changed)
            if ($delivery->getTransport() === $this) {
                $delivery->setTransport(null);
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
            $emptyReturn->setTransport($this);
        }

        return $this;
    }

    public function removeEmptyReturn(EmptyReturn $emptyReturn): static
    {
        if ($this->emptyReturns->removeElement($emptyReturn)) {
            // set the owning side to null (unless already changed)
            if ($emptyReturn->getTransport() === $this) {
                $emptyReturn->setTransport(null);
            }
        }

        return $this;
    }
}
