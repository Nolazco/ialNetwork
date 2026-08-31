<?php

namespace App\Entity;

use App\Repository\LocalTransferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traspaso local: un transportista que ya salio deja parte (o toda) su carga
 * para que otro la continue, sin que el expediente avance de estatus todavia
 * (eso solo lo hace la entrega final, en el despacho que sea). Es un registro
 * de auditoria puro — no enlaza a que despacho continua, porque un mismo
 * traspaso puede repartirse entre varios despachos nuevos: el rastro queda
 * aqui, la continuacion se agenda por separado con el flujo normal de
 * "Avisar al transporte".
 *
 * El cliente nunca debe ver esto (ver DeliveryFailureCatalog para el
 * contraste: un despacho fallido si es visible al cliente, un traspaso no).
 */
#[ORM\Entity(repositoryClass: LocalTransferRepository::class)]
class LocalTransfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transfersOut')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Delivery $fromDelivery = null;

    // Explicito y no derivado: en carga suelta no hay contenedor que diga de
    // que expediente es, y un despacho puede ser compartido entre varios.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    /**
     * Contenedores que se traspasan. Vacio en carga suelta: ahi el traspaso
     * es de todo el despacho, no de una parte.
     *
     * @var Collection<int, Container>
     */
    #[ORM\ManyToMany(targetEntity: Container::class)]
    private Collection $containers;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $at = null;

    #[ORM\Column(length: 255)]
    private ?string $placeType = null;

    /** Solo si placeType es "Lugar libre". */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $place = null;

    /** Solo si placeType es "Punto de inspección". */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?InspectionPoint $inspectionPoint = null;

    /** Libre: en carga suelta es donde se anota que parte se traspasa. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reportedBy = null;

    public function __construct()
    {
        $this->containers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromDelivery(): ?Delivery
    {
        return $this->fromDelivery;
    }

    public function setFromDelivery(?Delivery $fromDelivery): static
    {
        $this->fromDelivery = $fromDelivery;

        return $this;
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

    public function getAt(): ?\DateTimeImmutable
    {
        return $this->at;
    }

    public function setAt(\DateTimeImmutable $at): static
    {
        $this->at = $at;

        return $this;
    }

    public function getPlaceType(): ?string
    {
        return $this->placeType;
    }

    public function setPlaceType(string $placeType): static
    {
        $this->placeType = $placeType;

        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setPlace(?string $place): static
    {
        $this->place = $place;

        return $this;
    }

    public function getInspectionPoint(): ?InspectionPoint
    {
        return $this->inspectionPoint;
    }

    public function setInspectionPoint(?InspectionPoint $inspectionPoint): static
    {
        $this->inspectionPoint = $inspectionPoint;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getReportedBy(): ?User
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?User $reportedBy): static
    {
        $this->reportedBy = $reportedBy;

        return $this;
    }
}
