<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Repository\NotificationRecipientsRepository;
use App\Workflow\AduanaCatalog;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa a los responsables de la aduana correspondiente en cuanto un cliente
 * da de alta una solicitud nueva — a diferencia de ModuladoMailer, esto NO
 * lleva al cliente ni a los ejecutivos en general: son los encargados de esa
 * aduana en particular (ver NotificationRecipients, sección "Aduana ..."),
 * porque una misma agencia puede tener responsables distintos por aduana.
 */
final class NewImportRequestMailer
{
    /**
     * Sufijo de llave por aduana (ver self::keysFor()) — mismo catálogo que
     * AduanaCatalog::LABELS, pero en formato de llave de NotificationRecipients.
     *
     * @var array<string, string>
     */
    private const KEY_SUFFIXES = [
        AduanaCatalog::MANZANILLO => 'manzanillo',
        AduanaCatalog::LAZARO_CARDENAS => 'lazaro_cardenas',
        AduanaCatalog::VERACRUZ => 'veracruz',
        AduanaCatalog::AICM => 'aicm',
        AduanaCatalog::GUADALAJARA => 'guadalajara',
        AduanaCatalog::AIFA => 'aifa',
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly NotificationRecipientsRepository $notificationRecipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notify(ImportRequest $import): void
    {
        [$toKey, $ccKey] = $this->keysFor($import->getAduana());

        $to = $this->notificationRecipients->emailsFor($toKey);
        $cc = $this->notificationRecipients->emailsFor($ccKey);

        if ($to === [] && $cc === []) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Nueva solicitud %s (%s)', $import->getClientReference(), AduanaCatalog::LABELS[$import->getAduana()] ?? $import->getAduana()))
            ->htmlTemplate('emails/new_import_request.html.twig')
            ->context([
                'import' => $import,
                'aduanas' => AduanaCatalog::LABELS,
            ]);

        if ($to !== []) {
            $email->to(...$to);
        }

        if ($cc !== []) {
            $email->cc(...$cc);
        }

        $this->mailer->send($email);
    }

    /**
     * @return array{0: string, 1: string} [llave TO, llave CC]
     */
    private function keysFor(string $aduana): array
    {
        $suffix = self::KEY_SUFFIXES[$aduana] ?? throw new \InvalidArgumentException("Aduana desconocida: {$aduana}");

        return ["aduana_{$suffix}_to", "aduana_{$suffix}_cc"];
    }
}
