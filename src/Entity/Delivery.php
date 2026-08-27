<?php

namespace App\Entity;

use App\Repository\DeliveryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un movimiento de transporte sobre un expediente.
 *
 * La carga suelta lleva un solo despacho. La mercancia contenerizada lleva
 * tantos como haga falta, porque cada contenedor puede salir en fecha distinta
 * y con transportista distinto, pero un camion nunca carga mas de dos.
 */
#[ORM\Entity(repositoryClass: DeliveryRepository::class)]
class Delivery
{
    /** Cuantos contenedores caben en un mismo camion. */
    public const MAX_CONTAINERS = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'deliveries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    // Nullable a proposito: la cita se puede agendar antes de saber que
    // transportista la cubrira ("Transporte pendiente"), y se asigna despues.
    #[ORM\ManyToOne(inversedBy: 'deliveries')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FreightHauler $transport = null;

    /**
     * Contenedores que carga este camion. Vacio cuando es carga suelta.
     *
     * @var Collection<int, Container>
     */
    #[ORM\ManyToMany(targetEntity: Container::class, inversedBy: 'deliveries')]
    private Collection $containers;

    /** Fecha y hora acordadas para la maniobra. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $hour = null;

    /** Momentos reales, que confirma el transportista desde su panel. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $departedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    /**
     * Prueba de entrega opcional (foto, acuse firmado...). Nullable a
     * proposito, igual que EmptyReturn::eirRoute: no todos los transportistas
     * la suben al momento.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $proofRoute = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $proofUploadedAt = null;

    public function __construct()
    {
        $this->containers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?ImportRequest
    {
        return $this->reference;
    }

    public function setReference(?ImportRequest $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getTransport(): ?FreightHauler
    {
        return $this->transport;
    }

    public function setTransport(?FreightHauler $transport): static
    {
        $this->transport = $transport;

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
        }

        return $this;
    }

    public function removeContainer(Container $container): static
    {
        $this->containers->removeElement($container);

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHour(): ?\DateTimeImmutable
    {
        return $this->hour;
    }

    public function setHour(\DateTimeImmutable $hour): static
    {
        $this->hour = $hour;

        return $this;
    }

    public function getDepartedAt(): ?\DateTimeImmutable
    {
        return $this->departedAt;
    }

    public function setDepartedAt(?\DateTimeImmutable $departedAt): static
    {
        $this->departedAt = $departedAt;

        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getProofRoute(): ?string
    {
        return $this->proofRoute;
    }

    public function setProofRoute(?string $proofRoute): static
    {
        $this->proofRoute = $proofRoute;

        return $this;
    }

    public function getProofUploadedAt(): ?\DateTimeImmutable
    {
        return $this->proofUploadedAt;
    }

    public function setProofUploadedAt(?\DateTimeImmutable $proofUploadedAt): static
    {
        $this->proofUploadedAt = $proofUploadedAt;

        return $this;
    }

    public function isDeparted(): bool
    {
        return $this->departedAt !== null;
    }

    public function isDelivered(): bool
    {
        return $this->deliveredAt !== null;
    }

    /**
     * Etiqueta del avance de este despacho, para pintarla en los listados.
     */
    public function getStage(): string
    {
        return match (true) {
            $this->isDelivered() => 'Entregado',
            $this->isDeparted() => 'En tránsito',
            default => 'Asignado',
        };
    }
}
