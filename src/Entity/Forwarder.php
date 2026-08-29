<?php

namespace App\Entity;

use App\Repository\ForwarderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Agente de carga (forwarder): a veces la mercancia viene consignada a el en
 * vez de al cliente directo. Solo interesa a la agencia para poder avisarle
 * (correos de contacto) y para llevar sus cuentas bancarias, nunca al reves:
 * el forwarder jamas debe enterarse de los costos que la agencia maneja con
 * el cliente. Ver ForwarderMailer y DashboardForwarders para donde se
 * garantiza esa frontera.
 */
#[ORM\Entity(repositoryClass: ForwarderRepository::class)]
class Forwarder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * Correos a los que se avisa cuando se registra una devolucion de vacio
     * / EIR de un expediente consignado a este forwarder.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $contactEmails = [];

    /**
     * Cuentas bancarias que el forwarder reporta para que el cliente le
     * liquide directamente; la agencia no participa en ese pago. Solo se
     * lee/muestra desde DashboardForwarders (ROLE_EXECUTIVE) — nunca desde un
     * correo ni desde una pantalla que vea el cliente.
     *
     * @var list<array{bank: string, accountNumber: string, clabe: string, swift: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $bankAccounts = [];

    /**
     * Ruta (fuera de public/) del archivo donde el forwarder redacto sus
     * cuentas a mano, si existe, como respaldo del listado estructurado.
     * Se sirve unicamente via DashboardForwarders::downloadBankFile(), nunca
     * como link estatico: a diferencia de los demas archivos de esta app,
     * este es confidencial.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bankAccountsFileRoute = null;

    /**
     * @var Collection<int, ImportRequest>
     */
    #[ORM\OneToMany(targetEntity: ImportRequest::class, mappedBy: 'forwarder')]
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
     * @return list<array{bank: string, accountNumber: string, clabe: string, swift: string}>
     */
    public function getBankAccounts(): array
    {
        return $this->bankAccounts;
    }

    /**
     * @param list<array{bank: string, accountNumber: string, clabe: string, swift: string}> $bankAccounts
     */
    public function setBankAccounts(array $bankAccounts): static
    {
        $this->bankAccounts = $bankAccounts;

        return $this;
    }

    public function getBankAccountsFileRoute(): ?string
    {
        return $this->bankAccountsFileRoute;
    }

    public function setBankAccountsFileRoute(?string $bankAccountsFileRoute): static
    {
        $this->bankAccountsFileRoute = $bankAccountsFileRoute;

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
            $importRequest->setForwarder($this);
        }

        return $this;
    }

    public function removeImportRequest(ImportRequest $importRequest): static
    {
        if ($this->importRequests->removeElement($importRequest)) {
            if ($importRequest->getForwarder() === $this) {
                $importRequest->setForwarder(null);
            }
        }

        return $this;
    }
}
