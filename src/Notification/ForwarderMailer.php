<?php

namespace App\Notification;

use App\Entity\EmptyReturn;
use App\Entity\ImportRequest;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa al forwarder cuando se registra la devolucion de vacio / EIR de un
 * expediente consignado a el.
 *
 * El contexto del correo se arma a mano con puros escalares (nunca se pasa
 * $import ni $forwarder completos a la plantilla): el forwarder jamas debe
 * enterarse de los costos que la agencia maneja con el cliente
 * (InternInvoice y similares no tienen forma de llegar aqui aunque alguien
 * edite la plantilla despues sin cuidado), ni de las cuentas bancarias que
 * el mismo reporto.
 */
final class ForwarderMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notifyEmptyReturn(EmptyReturn $return, ImportRequest $import): void
    {
        $forwarder = $import->getForwarder();

        if ($forwarder === null) {
            return;
        }

        $to = $this->recipients->forwarderEmails($import);

        if ($to === []) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Devolución de vacío registrada - %s', $return->getContainer()?->getNum()))
            ->htmlTemplate('emails/forwarder_empty_return.html.twig')
            ->context([
                'agencyReference' => $import->getAgencyReference(),
                'clientReference' => $import->getClientReference(),
                'containerNum' => $return->getContainer()?->getNum(),
                'containerType' => $return->getContainer()?->getType(),
                'eir' => $return->getEir(),
                'yardName' => $return->getYard()?->getName(),
                'returnDate' => $return->getDate(),
                'returnType' => $return->getType(),
            ])
            ->to(...$to);

        $this->mailer->send($email);
    }
}
