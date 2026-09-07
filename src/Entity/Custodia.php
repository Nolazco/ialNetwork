<?php

namespace App\Entity;

use App\Repository\CustodiaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Empresa de custodia armada que puede acompañar la mercancia durante el
 * traslado. El cliente elige una del catalogo al dar de alta la solicitud si
 * la mercancia la requiere (ver ImportRequest::$custodia); al avisar al
 * transporte del despacho se le agrega en copia (ver DeliveryMailer).
 */
#[ORM\Entity(repositoryClass: CustodiaRepository::class)]
class Custodia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $contactEmails = [];

    /**
     * @var Collection<int, ImportRequest>
     */
    #[ORM\OneToMany(targetEntity: ImportRequest::class, mappedBy: 'custodia')]
    private Collection $importRequests;

    public function __construct()
    {
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

    /**
     * @return list<string>
     */
    public function getContactEmails(): array
    {
        return $this->contactEmails;
    }

    /**
     * @param list<string> $contactEmails
     */
    public function setContactEmails(array $contactEmails): static
    {
        $this->contactEmails = $contactEmails;

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
            $importRequest->setCustodia($this);
        }

        return $this;
    }

    public function removeImportRequest(ImportRequest $importRequest): static
    {
        if ($this->importRequests->removeElement($importRequest)) {
            if ($importRequest->getCustodia() === $this) {
                $importRequest->setCustodia(null);
            }
        }

        return $this;
    }
}
