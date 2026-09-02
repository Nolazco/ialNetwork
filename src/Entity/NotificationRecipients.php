<?php

namespace App\Entity;

use App\Repository\NotificationRecipientsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lista de correos fija de una notificación (a diferencia de los
 * destinatarios dinámicos de RecipientResolver, resueltos de clientes o
 * ejecutivos reales). Antes vivían como `public const` en cada Mailer;
 * ahora el ejecutivo las edita desde /admin sin tocar código — pensado sobre
 * todo para poder probar mandando a un correo propio sin pedirle a nadie que
 * edite el código y lo revierta despues.
 *
 * `key` y `required` los fija el código (una fila por cada clave usada en
 * los Mailer::TO_KEY/CC_KEY), no el admin: por eso no tienen setter público
 * pensado para el formulario y el CRUD los deja de solo lectura.
 */
#[ORM\Entity(repositoryClass: NotificationRecipientsRepository::class)]
class NotificationRecipients
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $key = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $emails = [];

    /**
     * true en las que siempre tuvieron una dirección real (to() sin ningún
     * destinatario revienta el envío); false en las que solo agregan copia a
     * lo que ya manda RecipientResolver, y por eso pueden quedar vacías.
     */
    #[ORM\Column]
    private bool $required = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEmails(): array
    {
        return $this->emails;
    }

    /**
     * @param list<string> $emails
     */
    public function setEmails(array $emails): static
    {
        $this->emails = array_values($emails);

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;

        return $this;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->required && $this->emails === []) {
            $context->buildViolation('Esta lista no puede quedar vacía.')
                ->atPath('emails')
                ->addViolation();
        }
    }
}
