<?php

namespace App\Notification;

use App\Entity\Delivery;
use App\Entity\ImportRequest;
use App\Service\UploadPath;
use App\Workflow\RequiredDocumentType;
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

        // El pedimento simplificado adjunto manualmente en el despacho (si lo
        // hay) aplica a todo el camion y tiene prioridad; si no se adjunto
        // ninguno, cada referencia toma el suyo del expediente (subido desde
        // la fase "Pagado" — ver RequiredDocumentType::SIMPLIFIED_PEDIMENTO).
        $manualPedimentoRoute = $delivery->getPedimentoSimplificadoRoute();

        if ($manualPedimentoRoute) {
            $attachments[] = ['route' => $manualPedimentoRoute, 'name' => 'Pedimento simplificado.'.pathinfo($manualPedimentoRoute, PATHINFO_EXTENSION)];
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

            if (!$manualPedimentoRoute) {
                $route = $this->documentRoute($reference, RequiredDocumentType::SIMPLIFIED_PEDIMENTO);

                if ($route !== null) {
                    $attachments[] = ['route' => $route, 'name' => sprintf('Pedimento simplificado %s.%s', $reference->getAgencyReference(), pathinfo($route, PATHINFO_EXTENSION))];
                }
            }

            $bl = $this->documentRoute($reference, RequiredDocumentType::REVALIDATED_BL);

            if ($bl !== null) {
                $attachments[] = ['route' => $bl, 'name' => sprintf('BL revalidado %s.%s', $reference->getAgencyReference(), pathinfo($bl, PATHINFO_EXTENSION))];
            }
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

    private function documentRoute(ImportRequest $import, string $type): ?string
    {
        foreach ($import->getRequiredDocuments() as $document) {
            if ($document->getType() === $type && $document->getRoute() !== null) {
                return $document->getRoute();
            }
        }

        return null;
    }
}
