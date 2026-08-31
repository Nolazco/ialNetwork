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

    /**
     * Expediente(s) que carga este despacho. Normalmente uno solo, pero un
     * mismo camion puede cargar mercancia de varios expedientes del mismo
     * cliente cuando se pagan el mismo dia y van al mismo domicilio — mismo
     * patron que Container::$reference, que ya es ManyToMany por la misma
     * razon. La BD ya no garantiza "al menos un expediente" (se perdio el
     * NOT NULL que tenia el FK singular): esa garantia la dan los
     * controladores, no el esquema.
     *
     * @var Collection<int, ImportRequest>
     */
    #[ORM\ManyToMany(targetEntity: ImportRequest::class, inversedBy: 'deliveries')]
    private Collection $references;

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

    /**
     * Cuando la carga no se pudo realizar (la autoridad la rechazó, la unidad
     * no cumplió requisitos...). Se marca, no se borra: queda como registro
     * permanente para que el ejecutivo tenga el contexto al reprogramar.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $failureReportedBy = null;

    /**
     * Transportista que devolvera los contenedores vacios, si es distinto
     * del que entrego. Nullable: por defecto es el mismo (ver getTransport()
     * en DashboardDeliveries::registerEmptyReturn(), que hace el fallback).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?FreightHauler $returnTransport = null;

    /**
     * Traspasos locales que salieron de este despacho (ver LocalTransfer).
     *
     * @var Collection<int, LocalTransfer>
     */
    #[ORM\OneToMany(targetEntity: LocalTransfer::class, mappedBy: 'fromDelivery')]
    private Collection $transfersOut;

    public function __construct()
    {
        $this->containers = new ArrayCollection();
        $this->references = new ArrayCollection();
        $this->transfersOut = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, ImportRequest>
     */
    public function getReferences(): Collection
    {
        return $this->references;
    }

    public function addReference(ImportRequest $reference): static
    {
        if (!$this->references->contains($reference)) {
            $this->references->add($reference);
        }

        return $this;
    }

    public function removeReference(ImportRequest $reference): static
    {
        $this->references->removeElement($reference);

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

    public function isFailed(): bool
    {
        return $this->failedAt !== null;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function setFailedAt(?\DateTimeImmutable $failedAt): static
    {
        $this->failedAt = $failedAt;

        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): static
    {
        $this->failureReason = $failureReason;

        return $this;
    }

    public function getFailureReportedBy(): ?User
    {
        return $this->failureReportedBy;
    }

    public function setFailureReportedBy(?User $failureReportedBy): static
    {
        $this->failureReportedBy = $failureReportedBy;

        return $this;
    }

    public function getReturnTransport(): ?FreightHauler
    {
        return $this->returnTransport;
    }

    public function setReturnTransport(?FreightHauler $returnTransport): static
    {
        $this->returnTransport = $returnTransport;

        return $this;
    }

    /**
     * @return Collection<int, LocalTransfer>
     */
    public function getTransfersOut(): Collection
    {
        return $this->transfersOut;
    }

    /**
     * ¿Este despacho todavia le debe algo (un contenedor, o su lugar de
     * carga suelta) al expediente indicado? Es la unidad correcta para
     * decidir avance, no "isDelivered()/isFailed()" a secas: un despacho
     * compartido de carga suelta puede haber traspasado la parte de UN
     * expediente y seguir debiendole la entrega al otro, y eso no lo dice
     * ningun timestamp del despacho completo.
     */
    public function stillOwes(ImportRequest $reference): bool
    {
        if (!$this->references->contains($reference) || $this->isDelivered() || $this->isFailed()) {
            return false;
        }

        if ($reference->getContainers()->isEmpty()) {
            // Carga suelta: aqui el traspaso es del expediente completo, no
            // de una parte — si ya hay uno registrado para el, este
            // despacho ya no le debe nada.
            foreach ($this->transfersOut as $transfer) {
                if ($transfer->getReference() === $reference) {
                    return false;
                }
            }

            return true;
        }

        // Contenerizado: le debe mientras le quede aqui algun contenedor
        // suyo (los traspasados ya se quitaron de $containers).
        foreach ($this->containers as $container) {
            if ($container->getReference()->contains($reference)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ya salio y ya no le debe nada a ninguno de sus expedientes (todo lo
     * que traia se traspaso a otras unidades): es un callejon sin salida que
     * una o mas continuaciones reemplazan, no un despacho que siga "en
     * transito" de verdad.
     */
    public function isHandedOff(): bool
    {
        if (!$this->isDeparted() || $this->isDelivered()) {
            return false;
        }

        if ($this->transfersOut->isEmpty()) {
            return false;
        }

        foreach ($this->references as $reference) {
            if ($this->stillOwes($reference)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Etiqueta del avance de este despacho, para pintarla en los listados.
     */
    public function getStage(): string
    {
        return match (true) {
            $this->isFailed() => 'Fallido',
            $this->isDelivered() => 'Entregado',
            $this->isHandedOff() => 'Traspasado',
            $this->isDeparted() => 'En tránsito',
            default => 'Asignado',
        };
    }
}
