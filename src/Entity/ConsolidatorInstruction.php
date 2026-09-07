<?php

namespace App\Entity;

use App\Repository\ConsolidatorInstructionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Instrucciones mandadas a la empresa de fletes consolidados (XCF, hasta
 * ahora la unica) cuando la mercancia de un expediente se le entrega para
 * que la lleve a su destino final. Es una maniobra aparte del despacho
 * normal: no cambia el estatus del expediente ni depende de el (ver
 * ConsolidatorMailer y ConsolidatorInstructionSheetGenerator).
 */
#[ORM\Entity(repositoryClass: ConsolidatorInstructionRepository::class)]
class ConsolidatorInstruction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'consolidatorInstructions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    /**
     * El destinatario final al que XCF debe entregar. Nullable: null significa
     * domicilio fiscal de la empresa del expediente (mismo patron que
     * ImportRequest::deliveryPoint) — Company ya tiene los mismos campos de
     * domicilio/contacto que DeliveryPoint (ver ConsolidatorInstructionSheetGenerator).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?DeliveryPoint $deliveryPoint = null;

    /**
     * Nullable: la mercancia se pudo capturar al vuelo sin guardarla en el
     * catalogo del cliente.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?MerchandiseProfile $merchandiseProfile = null;

    /**
     * El transportista que se presentara en XCF con el folio que ellos
     * generan al recibir estas instrucciones — sin decirselo en el correo,
     * XCF no sabe a quien dejar pasar por la mercancia.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FreightHauler $transport = null;

    #[ORM\Column(length: 255)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 255)]
    private ?string $claveSat = null;

    #[ORM\Column(length: 255)]
    private ?string $claveUnidad = null;

    #[ORM\Column(length: 255)]
    private ?string $unidad = null;

    /**
     * Día en que se entregaría la mercancía en XCF. Nullable a propósito: se
     * puede avisar la instrucción sin tener todavía la cita — es solo un
     * estimado para que XCF se organice, no un compromiso en firme.
     */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveryDate = null;

    #[ORM\Column]
    private bool $estibable = false;

    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column]
    private float $weightKg = 0;

    /**
     * true: el cliente paga directo (se llena "cobrar a" con los datos del
     * facturador). false: se deja el valor de la plantilla (la agencia
     * cobra), el caso mas comun.
     */
    #[ORM\Column]
    private bool $billedToClient = false;

    /** Ruta del xlsx generado, resuelta via UploadPath igual que el resto de documentos protegidos. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileRoute = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

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

    public function getDeliveryPoint(): ?DeliveryPoint
    {
        return $this->deliveryPoint;
    }

    public function setDeliveryPoint(?DeliveryPoint $deliveryPoint): static
    {
        $this->deliveryPoint = $deliveryPoint;

        return $this;
    }

    public function getMerchandiseProfile(): ?MerchandiseProfile
    {
        return $this->merchandiseProfile;
    }

    public function setMerchandiseProfile(?MerchandiseProfile $merchandiseProfile): static
    {
        $this->merchandiseProfile = $merchandiseProfile;

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

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getClaveSat(): ?string
    {
        return $this->claveSat;
    }

    public function setClaveSat(string $claveSat): static
    {
        $this->claveSat = $claveSat;

        return $this;
    }

    public function getClaveUnidad(): ?string
    {
        return $this->claveUnidad;
    }

    public function setClaveUnidad(string $claveUnidad): static
    {
        $this->claveUnidad = $claveUnidad;

        return $this;
    }

    public function getUnidad(): ?string
    {
        return $this->unidad;
    }

    public function setUnidad(string $unidad): static
    {
        $this->unidad = $unidad;

        return $this;
    }

    public function getDeliveryDate(): ?\DateTimeImmutable
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?\DateTimeImmutable $deliveryDate): static
    {
        $this->deliveryDate = $deliveryDate;

        return $this;
    }

    public function isEstibable(): bool
    {
        return $this->estibable;
    }

    public function setEstibable(bool $estibable): static
    {
        $this->estibable = $estibable;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getWeightKg(): float
    {
        return $this->weightKg;
    }

    public function setWeightKg(float $weightKg): static
    {
        $this->weightKg = $weightKg;

        return $this;
    }

    public function isBilledToClient(): bool
    {
        return $this->billedToClient;
    }

    public function setBilledToClient(bool $billedToClient): static
    {
        $this->billedToClient = $billedToClient;

        return $this;
    }

    public function getFileRoute(): ?string
    {
        return $this->fileRoute;
    }

    public function setFileRoute(?string $fileRoute): static
    {
        $this->fileRoute = $fileRoute;

        return $this;
    }

    /**
     * Nombre con el que se manda el xlsx a XCF (adjunto de correo) y con el
     * que se descarga desde el expediente — nunca el nombre real del archivo
     * en disco (un id/uniqid), que a ojos de XCF se ve como un hash sin
     * explicación y puede levantar sospechas.
     */
    public function suggestedFileName(): string
    {
        $company = $this->reference?->getIdCompany()?->getName() ?? '';
        $name = sprintf('INST XCF %s - %s', $company, $this->descripcion);
        $name = preg_replace('/[\\\\\/:*?"<>|]/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name.'.xlsx';
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
