<?php

namespace App\Notification;

use App\Entity\Delivery;
use App\Service\UploadPath;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final class DeliveryMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        private readonly UploadPath $uploadPath,
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

        $agencyReferences = [];
        $custodiaEmails = [];
        $references = [];
        $attachments = [];

        // La maniobra es opcional: si se adjunto una, se le manda al
        // transporte renombrada con el contenedor (o "MANIOBRA CS ..." si es
        // carga suelta) en vez de con el nombre original del archivo.
        $maniobraRoute = $delivery->getManiobraRoute();

        if ($maniobraRoute) {
            $attachments[] = ['route' => $maniobraRoute, 'name' => $this->maniobraFilename($delivery, pathinfo($maniobraRoute, PATHINFO_EXTENSION))];
        }

        foreach ($delivery->getReferences() as $reference) {
            $agencyReferences[] = $reference->getAgencyReference();

            foreach ($reference->getCustodia()?->getContactEmails() ?? [] as $custodiaEmail) {
                $custodiaEmails[$custodiaEmail] = true;
            }

            $billTo = $reference->getBillTo();
            $company = $reference->getIdCompany();

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
                'deliveryAddress' => $deliveryAddress,
                'deliveryInstructions' => $reference->getDeliveryInstructions(),
                'yard' => $yard ? sprintf('%s (CR %s)', $yard->getName(), $yard->getCr()) : null,
            ];
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('AVISO DE TRANSPORTE // %s', implode(', ', $agencyReferences)))
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
     * Contenedor: el nombre del archivo es el numero de cada contenedor
     * (separados por coma si hay mas de uno). Carga suelta: no hay
     * contenedor con que identificarla, asi que se usa "MANIOBRA CS
     * <empresa> - <recinto>" de la primera referencia del despacho.
     */
    private function maniobraFilename(Delivery $delivery, string $extension): string
    {
        $containers = $delivery->getContainers();

        if (!$containers->isEmpty()) {
            $names = [];

            foreach ($containers as $container) {
                $names[] = $container->getNum();
            }

            return implode(', ', $names).'.'.$extension;
        }

        $primary = $delivery->getReferences()->first() ?: null;
        $company = $primary?->getIdCompany()->getName() ?? '';
        $yard = $primary?->getCr()?->getName() ?? 'recinto pendiente';

        return strtoupper(sprintf('MANIOBRA CS %s - %s', $company, $yard)).'.'.$extension;
    }
}
