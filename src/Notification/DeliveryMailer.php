<?php

namespace App\Notification;

use App\Entity\Delivery;
use App\Repository\NotificationRecipientsRepository;
use App\Service\UploadPath;
use App\Workflow\AduanaCatalog;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final class DeliveryMailer
{
    /**
     * Sufijo de llave por aduana (ver self::keysFor()) — mismo catalogo que
     * NewImportRequestMailer::KEY_SUFFIXES, para la lista to/cc propia del
     * aviso de transporte (ver NotificationRecipients).
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
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        private readonly UploadPath $uploadPath,
        private readonly NotificationRecipientsRepository $notificationRecipients,
    ) {
    }

    public function notify(Delivery $delivery): void
    {
        $hauler = $delivery->getTransport();

        if ($hauler === null && $delivery->getUnregisteredHaulerEmails() === null) {
            return;
        }

        $to = $hauler !== null
            ? array_keys(array_flip(array_filter(array_merge(
                [$hauler->getIdUser()->getEmail()],
                $hauler->getContactEmails(),
            ))))
            : array_keys(array_flip(array_filter($delivery->getUnregisteredHaulerEmails())));

        if ($to === []) {
            return;
        }

        $custodiaEmails = [];
        $fleteToEmails = [];
        $references = [];
        $attachments = [];
        $seenAduanas = [];

        // La maniobra es opcional: si se adjunto una, se le manda al
        // transporte renombrada con el contenedor (o "MANIOBRA CS ..." si es
        // carga suelta) en vez de con el nombre original del archivo.
        $maniobraRoute = $delivery->getManiobraRoute();

        if ($maniobraRoute) {
            $attachments[] = ['route' => $maniobraRoute, 'name' => $this->maniobraFilename($delivery, pathinfo($maniobraRoute, PATHINFO_EXTENSION))];
        }

        foreach ($delivery->getReferences() as $reference) {
            foreach ($reference->getCustodia()?->getContactEmails() ?? [] as $custodiaEmail) {
                $custodiaEmails[$custodiaEmail] = true;
            }

            // Antes esto solo le llegaba al transportista y a la custodia,
            // sin nadie de la agencia en copia. Un despacho casi siempre
            // comparte una sola aduana entre sus referencias, pero por si
            // acaso combina mas de una, se juntan las listas de todas (ver
            // NotificationRecipients, "aviso de transporte").
            $aduana = $reference->getAduana();

            if (!isset($seenAduanas[$aduana])) {
                $seenAduanas[$aduana] = true;
                [$fleteToKey, $fleteCcKey] = $this->keysFor($aduana);

                foreach ($this->notificationRecipients->emailsFor($fleteToKey) as $email) {
                    $fleteToEmails[$email] = true;
                }

                foreach ($this->notificationRecipients->emailsFor($fleteCcKey) as $email) {
                    $custodiaEmails[$email] = true;
                }
            }

            $billTo = $reference->getBillTo();
            $company = $reference->getIdCompany();

            // Si viaja con el consolidador de carga, la mercancia no se
            // entrega en el domicilio del cliente sino en XCF: mostrar ese
            // domicilio ahi era enganoso. El folio se sube desde el bottom
            // de la ficha para que no se pierda hasta abajo del correo.
            $deliveryPoint = $reference->getDeliveryPoint();
            $deliveryAddress = $deliveryPoint
                ? sprintf('%s (%s)', $deliveryPoint->getName(), $deliveryPoint->getAddress())
                : $company->getAddress();

            $yard = $reference->getCr();

            $references[] = [
                'agencyReference' => $reference->getAgencyReference(),
                'clientReference' => $reference->getClientReference(),
                'companyName' => $company->getName(),
                'custodia' => $reference->getCustodia(),
                'billingName' => $billTo ? $billTo->getName() : $company->getName(),
                'billingAddress' => $billTo ? $billTo->getAddress() : $company->getAddress(),
                'billingRfc' => $billTo ? $billTo->getRfc() : $company->getRfc(),
                'travelsWithConsolidator' => $reference->travelsWithConsolidator(),
                'deliveryAddress' => $deliveryAddress,
                'deliveryInstructions' => $reference->getDeliveryInstructions(),
                'yard' => $yard ? sprintf('%s (CR %s)', $yard->getName(), $yard->getCr()) : null,
            ];
        }

        $to = array_values(array_unique(array_merge($to, array_keys($fleteToEmails))));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject($this->subjectFor($delivery))
            ->htmlTemplate('emails/delivery_notice.html.twig')
            ->context(['delivery' => $delivery, 'references' => $references])
            ->to(...$to);

        if ($custodiaEmails !== []) {
            $email->cc(...array_keys($custodiaEmails));
        }

        foreach ($attachments as $attachment) {
            $path = $this->uploadPath->resolve($attachment['route']);

            if (is_file($path)) {
                $email->attachFromPath($path, $attachment['name']);
            }
        }

        $this->mailer->send($email);
    }

    /**
     * @return array{0: string, 1: string} [llave TO, llave CC]
     */
    private function keysFor(string $aduana): array
    {
        $suffix = self::KEY_SUFFIXES[$aduana] ?? throw new \InvalidArgumentException("Aduana desconocida: {$aduana}");

        return ["aduana_{$suffix}_flete_to", "aduana_{$suffix}_flete_cc"];
    }

    /**
     * "DESPACHO // <empresa> // <contenedor(es)>", con la empresa de la
     * primera referencia del despacho (un mismo camion casi siempre es de un
     * solo cliente) y sus contenedores; en carga suelta, que no tiene
     * contenedor con que identificarla, se usa "CARGA SUELTA" en su lugar.
     */
    private function subjectFor(Delivery $delivery): string
    {
        $primary = $delivery->getReferences()->first() ?: null;
        $client = $primary?->getIdCompany()->getName() ?? '';

        $containerNames = $this->containerNames($delivery);
        $containerPart = $containerNames !== [] ? implode(', ', $containerNames) : 'CARGA SUELTA';

        return strtoupper(sprintf('DESPACHO // %s // %s', $client, $containerPart));
    }

    /**
     * Contenedor: el nombre del archivo es el numero de cada contenedor
     * (separados por coma si hay mas de uno). Carga suelta: no hay
     * contenedor con que identificarla, asi que se usa "MANIOBRA CS
     * <empresa> - <recinto>" de la primera referencia del despacho.
     */
    private function maniobraFilename(Delivery $delivery, string $extension): string
    {
        $containerNames = $this->containerNames($delivery);

        if ($containerNames !== []) {
            return implode(', ', $containerNames).'.'.$extension;
        }

        $primary = $delivery->getReferences()->first() ?: null;
        $company = $primary?->getIdCompany()->getName() ?? '';
        $yard = $primary?->getCr()?->getName() ?? 'recinto pendiente';

        return strtoupper(sprintf('MANIOBRA CS %s - %s', $company, $yard)).'.'.$extension;
    }

    /**
     * @return list<string>
     */
    private function containerNames(Delivery $delivery): array
    {
        $names = [];

        foreach ($delivery->getContainers() as $container) {
            $names[] = $container->getNum();
        }

        return $names;
    }
}
