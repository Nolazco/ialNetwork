<?php

namespace App\Entity;

use App\Repository\EmptyReturnRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmptyReturnRepository::class)]
class EmptyReturn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Container $container = null;

    #[ORM\ManyToOne(inversedBy: 'emptyReturns')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    #[ORM\ManyToOne(inversedBy: 'emptyReturns')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FreightHauler $transport = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $date = null;

    #[ORM\ManyToOne(inversedBy: 'emptyReturns')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ContainerYard $yard = null;

    #[ORM\Column(length: 255)]
    private ?string $eir = null;

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

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(string $date): static
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

    public function getEir(): ?string
    {
        return $this->eir;
    }

    public function setEir(string $eir): static
    {
        $this->eir = $eir;

        return $this;
    }
}
