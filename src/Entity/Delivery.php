<?php

namespace App\Entity;

use App\Repository\DeliveryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRepository::class)]
class Delivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportRequest $reference = null;

    #[ORM\ManyToOne(inversedBy: 'deliveries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FreightHauler $transport = null;

    #[ORM\Column(length: 255)]
    private ?string $date = null;

    #[ORM\Column(length: 255)]
    private ?string $hour = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?ImportRequest
    {
        return $this->reference;
    }

    public function setReference(ImportRequest $reference): static
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

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(string $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHour(): ?string
    {
        return $this->hour;
    }

    public function setHour(string $hour): static
    {
        $this->hour = $hour;

        return $this;
    }
}
