<?php

namespace App\Entity;

use App\Repository\EmptyReturnRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmptyReturnRepository::class)]
class EmptyReturn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Sin cascade remove: borrar el registro de devolucion no debe borrar el
    // contenedor, que sigue existiendo y pertenece al expediente.
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Container $container = null;

    #[ORM\ManyToOne(inversedBy: 'emptyReturns')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    /**
     * Quien hizo la devolucion. Nullable a proposito: el ejecutivo programa
     * el patio y la cita antes de que el vacio se devuelva de verdad, y en
     * ese momento no conviene fijar ya el transportista (Delivery::$transport
     * o $returnTransport pueden reasignarse despues, ver
     * DashboardCaseFiles::assignReturnTransport()) — se fija hasta que el
     * transportista (o el ejecutivo) registra la devolucion real.
     */
    #[ORM\ManyToOne(inversedBy: 'emptyReturns')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FreightHauler $transport = null;

    /** Nullable: se llena hasta que se registra la devolucion real. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    /** Fecha real de la devolucion. Nullable: se llena hasta que ocurre. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(inversedBy: 'emptyReturns')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ContainerYard $yard = null;

    /**
     * Fecha de la cita que agenda el ejecutivo con el patio, segun las
     * instrucciones de la naviera. Se asigna junto con el patio y la
     * papeleta, antes de que el transportista devuelva el contenedor.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $appointmentDate = null;

    /**
     * Papeleta del patio (autorizacion para devolver ahi), que sube el
     * ejecutivo al programar la cita.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slipRoute = null;

    /** Folio del EIR que entrega el patio al recibir el vacio. Nullable: se llena hasta que se registra la devolucion real. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $eir = null;

    /**
     * Ruta del EIR escaneado.
     *
     * Nullable a proposito: el formulario lo pide, pero la columna no debe
     * impedir registrar la devolucion si el documento llega despues — lo
     * puede subir el transportista al registrar la devolucion, o el
     * ejecutivo despues si el patio lo emite mas tarde.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $eirRoute = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContainer(): ?Container
    {
        return $this->container;
    }

    public function setContainer(Container $container): static
    {
        $this->container = $container;

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

    public function getTransport(): ?FreightHauler
    {
        return $this->transport;
    }

    public function setTransport(?FreightHauler $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getYard(): ?ContainerYard
    {
        return $this->yard;
    }

    public function setYard(?ContainerYard $yard): static
    {
        $this->yard = $yard;

        return $this;
    }

    public function getAppointmentDate(): ?\DateTimeImmutable
    {
        return $this->appointmentDate;
    }

    public function setAppointmentDate(?\DateTimeImmutable $appointmentDate): static
    {
        $this->appointmentDate = $appointmentDate;

        return $this;
    }

    public function getSlipRoute(): ?string
    {
        return $this->slipRoute;
    }

    public function setSlipRoute(?string $slipRoute): static
    {
        $this->slipRoute = $slipRoute;

        return $this;
    }

    public function getEir(): ?string
    {
        return $this->eir;
    }

    public function setEir(?string $eir): static
    {
        $this->eir = $eir;

        return $this;
    }

    public function getEirRoute(): ?string
    {
        return $this->eirRoute;
    }

    public function setEirRoute(?string $eirRoute): static
    {
        $this->eirRoute = $eirRoute;

        return $this;
    }

    /**
     * La devolucion ya ocurrio de verdad (no solo esta programada). Se usa
     * en vez de "el registro existe" porque ahora el registro se crea desde
     * que el ejecutivo programa la cita, antes de que el transportista
     * devuelva el contenedor.
     */
    public function isExecuted(): bool
    {
        return $this->date !== null;
    }
}
