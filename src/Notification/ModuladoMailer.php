<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Repository\NotificationRecipientsRepository;
use App\Workflow\AduanaCatalog;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa sobre cambios de estatus en el SOIA de un expediente: modulación y
 * reconocimiento aduanero. Van a los clientes afiliados a la empresa
 * (destinatarios) y a todos los ejecutivos en copia, porque los expedientes
 * rotan entre ellos — más lo que se agregue desde /admin (ver
 * NotificationRecipients), sin fijos heredados: antes de esto, esta alerta
 * era 100% dinámica. Comparten destinatarios a propósito: es la misma
 * consulta al SOIA la que detecta ambos resultados.
 */
final class ModuladoMailer
{
    public const TO_KEY = 'modulado_to';
    public const CC_KEY = 'modulado_cc';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        private readonly NotificationRecipientsRepository $notificationRecipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        #[Autowire(env: 'SOIA_PATENTE')]
        private readonly string $patente,
    ) {
    }

    /**
     * @param string $soiaEstado Texto tal cual del SOIA (ej. "DESADUANADO",
     *                           "CUMPLIDO") — distinto de $import->getStatus(),
     *                           que ya es nuestra etiqueta interna del flujo.
     */
    public function notify(ImportRequest $import, string $soiaEstado): void
    {
        $this->send(
            $import,
            sprintf('Expediente %s modulado', $import->getClientReference()),
            'emails/modulado.html.twig',
            ['soiaEstado' => $soiaEstado],
        );
    }

    /**
     * El pedimento salió seleccionado para revisión física/documental: no es
     * un resultado final (ver SoiaResult::isUnderInspection()), así que este
     * correo es aparte del de modulación, no un reemplazo — cuando el SOIA
     * por fin confirme cumplido/desaduanado, notify() se dispara normal.
     */
    public function notifyReconocimiento(ImportRequest $import): void
    {
        $this->send(
            $import,
            sprintf('Expediente %s en reconocimiento aduanero', $import->getClientReference()),
            'emails/reconocimiento.html.twig',
            [],
        );
    }

    /**
     * @param array<string, mixed> $extraContext
     */
    private function send(ImportRequest $import, string $subject, string $template, array $extraContext): void
    {
        $to = $this->dedupe(array_merge(
            $this->recipients->clientEmails($import),
            $this->notificationRecipients->emailsFor(self::TO_KEY),
        ));
        $cc = $this->dedupe(array_merge(
            $this->recipients->executiveEmails(),
            $this->notificationRecipients->emailsFor(self::CC_KEY),
        ));

        if ($to === [] && $cc === []) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($extraContext + [
                'import' => $import,
                'patente' => $this->patente,
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
     * @param list<string> $emails
     *
     * @return list<string>
     */
    private function dedupe(array $emails): array
    {
        return array_keys(array_flip($emails));
    }
}
