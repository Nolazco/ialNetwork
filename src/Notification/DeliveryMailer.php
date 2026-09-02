<?php

namespace App\Notification;

use App\Entity\Delivery;
use App\Service\UploadPath;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa al transportista asignado los datos de la mercancia que debe
 * recoger — clave SAT, descripcion, embalaje, bultos, peso, cubicaje,
 * contenedores (si aplica), pedimento simplificado y folio de XCF (si el
 * expediente viaja con el consolidador de carga). El destinatario ya se
 * conoce con certeza (la cuenta del propio transportista, ver
 * FreightHauler::getIdUser(), mas los correos de contacto adicionales que el
 * transportista haya agregado en "Mis empresas transportistas", ver
 * FreightHauler::getContactEmails()), no un catalogo de correos fijos.
 */
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

        if ($hauler === null) {
            return;
        }

        $to = array_keys(array_flip(array_filter(array_merge(
            [$hauler->getIdUser()->getEmail()],
            $hauler->getContactEmails(),
        ))));

        if ($to === []) {
            return;
        }

        $agencyReferences = [];

        foreach ($delivery->getReferences() as $reference) {
            $agencyReferences[] = $reference->getAgencyReference();
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('AVISO DE TRANSPORTE // %s', implode(', ', $agencyReferences)))
            ->htmlTemplate('emails/delivery_notice.html.twig')
            ->context(['delivery' => $delivery])
            ->to(...$to);

        if ($delivery->getPedimentoSimplificadoRoute()) {
            $path = $this->uploadPath->resolve($delivery->getPedimentoSimplificadoRoute());

            if (is_file($path)) {
                $email->attachFromPath($path, 'Pedimento simplificado.'.pathinfo($path, PATHINFO_EXTENSION));
            }
        }

        $this->mailer->send($email);
    }
}
