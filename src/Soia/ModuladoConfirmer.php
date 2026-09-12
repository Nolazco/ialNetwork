<?php

namespace App\Soia;

use App\Entity\ImportRequest;
use App\Notification\ModuladoMailer;
use App\Notification\WhatsAppSender;
use App\Repository\NotificationRecipientsRepository;
use App\Workflow\AduanaCatalog;
use App\Workflow\ImportRequestWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Unico punto que decide "¿ya se puede pasar a Modulado?" y lo ejecuta.
 *
 * Lo usan tanto el boton manual ("Consultar SOIA" en el expediente) como el
 * poller automatico (PollSoiaCommand), para no duplicar la logica de avance +
 * correo en dos lados.
 */
final class ModuladoConfirmer
{
    private const WHATSAPP_EXECUTIVES_KEY = 'modulado_whatsapp';

    public function __construct(
        private readonly SoiaClient $client,
        private readonly ImportRequestWorkflow $workflow,
        private readonly ModuladoMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly AduanaCatalog $aduanaCatalog,
        private readonly WhatsAppSender $whatsApp,
        private readonly NotificationRecipientsRepository $notificationRecipients,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'SOIA_PATENTE')]
        private readonly string $patente,
    ) {
    }

    /**
     * @param bool $throttledWhatsApp Ver WhatsAppSender::send() — solo se
     *                                pasa en true desde el poller automatico
     *                                (PollSoiaCommand), nunca desde el boton
     *                                manual "Consultar SOIA".
     */
    public function attemptConfirm(ImportRequest $import, bool $throttledWhatsApp = false): SoiaResult
    {
        // Si el expediente ya se rectifico, el pedimento vigente ante el SAT
        // es el de la ultima rectificacion, no el original (ver
        // ImportRequest::getEffectiveImportNumber()).
        $result = $this->client->consultar((string) $import->getEffectiveImportNumber(), $this->aduanaCatalog->soiaCode($import->getAduana()));
        $import->setLastSoiaCheckAt(new \DateTimeImmutable());

        // El semaforo fiscal selecciono el pedimento para revision: no es un
        // resultado final (isResolved() sigue en false), asi que el poller
        // debe seguir intentando despues. Solo se avisa la primera vez que
        // se detecta -- mientras el SOIA siga reportando lo mismo en cada
        // poll, reconocimientoAt ya no es null y no se repite el correo.
        if ($result->isUnderInspection()) {
            if ($import->getReconocimientoAt() === null) {
                $import->setReconocimientoAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->mailer->notifyReconocimiento($import);
                $this->whatsApp->send($import->getIdCompany()->getWhatsapp(), $this->clientReconocimientoMessage($import), $throttledWhatsApp);
            } else {
                $this->entityManager->flush();
            }

            return $result;
        }

        if ($result->isResolved() && $this->workflow->canTransitionTo($import, ImportRequestWorkflow::MODULATED)) {
            $import->setModuladoAt($result->fecha ?? new \DateTimeImmutable());
            $import->setStatus(ImportRequestWorkflow::MODULATED);
            $this->entityManager->flush();

            // isResolved() ya garantiza que $result->estado viene lleno.
            $this->mailer->notify($import, $result->estado);
            $this->whatsApp->send($this->notificationRecipients->phonesFor(self::WHATSAPP_EXECUTIVES_KEY), $this->executiveModuladoMessage($import, $result->estado), $throttledWhatsApp);
            $this->whatsApp->send($import->getIdCompany()->getWhatsapp(), $this->clientModuladoMessage($import, $result->estado), $throttledWhatsApp);

            return $result;
        }

        $this->entityManager->flush();

        return $result;
    }

    /**
     * Aviso de WhatsApp para ejecutivos: con detalle tecnico (estado crudo
     * del SOIA), pensado para quien le da seguimiento al expediente.
     */
    private function executiveModuladoMessage(ImportRequest $import, string $soiaEstado): string
    {
        return sprintf(
            "✅ Modulado: %s — %s (%s)\nAduana: %s\nEstado SOIA: %s",
            $import->getEffectiveAgencyReference(),
            $import->getIdCompany()->getName(),
            $import->getClientReference(),
            AduanaCatalog::LABELS[$import->getAduana()] ?? $import->getAduana(),
            $soiaEstado,
        );
    }

    /**
     * Aviso de WhatsApp para el cliente final: sin jerga tecnica, con el
     * mismo formato que ya usaba la agencia en su sistema anterior (VCA).
     */
    private function clientModuladoMessage(ImportRequest $import, string $soiaEstado): string
    {
        return $this->clientMessage($import, '🟢', $soiaEstado, $import->getModuladoAt());
    }

    private function clientReconocimientoMessage(ImportRequest $import): string
    {
        return $this->clientMessage($import, '🔴', 'RECONOCIMIENTO ADUANERO', $import->getReconocimientoAt());
    }

    private function clientMessage(ImportRequest $import, string $semaforo, string $estado, ?\DateTimeImmutable $fecha): string
    {
        $company = $import->getIdCompany();

        return sprintf(
            "🚨 Aviso de Modulación\n".
            "📍 Aduana: %s - %s\n".
            "========================================\n".
            "📄 Pedimento: %s (Patente: %s)\n".
            "🔖 Referencia: %s\n".
            "🏬 Recinto: %s\n".
            "🏢 Cliente: %s (%s)\n".
            "📈 Estado: %s %s\n".
            "🕒 Fecha: %s\n".
            "🔗 Archivo Digital:\n%s",
            $import->getAduana(),
            AduanaCatalog::LABELS[$import->getAduana()] ?? $import->getAduana(),
            $import->getEffectiveImportNumber(),
            $this->patente,
            $import->getEffectiveAgencyReference(),
            $import->getCr()?->getName() ?? 'Por asignar',
            $company->getName(),
            $company->getRfc(),
            $semaforo,
            $estado,
            ($fecha ?? new \DateTimeImmutable())->format('d/m/Y H:i:s'),
            $this->urlGenerator->generate('case_file', ['id' => $import->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
