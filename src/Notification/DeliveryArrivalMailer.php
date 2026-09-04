<?php

namespace App\Notification;

use App\Entity\Delivery;
use App\Service\UploadPath;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa al cliente en cuanto el transportista confirma la entrega en destino
 * (ver DashboardDeliveries::confirmArrival()), con la prueba de entrega
 * adjunta si el transportista la subió — antes de esto el cliente no se
 * enteraba de la entrega salvo que preguntara directamente al ejecutivo.
 *
 * Un mismo despacho puede cubrir varios expedientes (ver Delivery::$references),
 * así que se manda un correo por expediente: cada uno puede ser de una empresa
 * distinta, y RecipientResolver resuelve los destinatarios por empresa.
 */
final class DeliveryArrivalMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        private readonly UploadPath $uploadPath,
    ) {
    }

    public function notify(Delivery $delivery): void
    {
        $proofPath = $delivery->getProofRoute() ? $this->uploadPath->resolve($delivery->getProofRoute()) : null;
        $hasProof = $proofPath && is_file($proofPath);

        foreach ($delivery->getReferences() as $import) {
            $to = $this->recipients->clientEmails($import);

            if ($to === []) {
                continue;
            }

            $cc = $this->recipients->executiveEmails();

            $email = (new TemplatedEmail())
                ->from($this->fromAddress)
                ->subject(sprintf('Mercancía entregada — %s', $import->getClientReference()))
                ->htmlTemplate('emails/delivery_arrival.html.twig')
                ->context(['import' => $import, 'delivery' => $delivery, 'hasProof' => $hasProof])
                ->to(...$to);

            if ($cc !== []) {
                $email->cc(...$cc);
            }

            if ($hasProof) {
                $email->attachFromPath($proofPath, 'Prueba de entrega.'.pathinfo($proofPath, PATHINFO_EXTENSION));
            }

            $this->mailer->send($email);
        }
    }
}
