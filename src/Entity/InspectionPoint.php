<?php

namespace App\Entity;

use App\Repository\InspectionPointRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Puntos donde puede ocurrir un traspaso por inspeccion (ej. XCF, Acoman).
 * En la practica casi siempre son los mismos dos, pero puede aparecer otro,
 * asi que va en catalogo en vez de una lista cerrada en codigo.
 */
#[ORM\Entity(repositoryClass: InspectionPointRepository::class)]
class InspectionPoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

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
}
