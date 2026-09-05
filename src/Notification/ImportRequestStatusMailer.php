<?php

namespace App\Notification;

use App\Entity\Delivery;
use App\Entity\ImportRequest;
use App\Service\UploadPath;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\RequiredDocumentType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Avisa al cliente cada vez que su expediente pasa de fase, adjuntando el
 * documento que respalda ese avance cuando ya se subió — Capturado,
 * Revalidado, Pagado, Programado, "En tránsito" (sin documento, solo los
 * datos del transporte), "Vacío devuelto" y Finalizado. Modulado y Entregado
 * tienen su propio mailer (ver ModuladoMailer y DeliveryArrivalMailer): esos
 * dependen de un evento externo (SOIA, confirmación del transportista) en vez
 * de solo un documento o un botón, así que ya traían su propio aviso desde
 * antes de este.
 */
final class ImportRequestStatusMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        private readonly UploadPath $uploadPath,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    /**
     * Punto único para los controladores: notifica el estatus que se acaba
     * de alcanzar, sin que cada uno tenga que saber cuál método le toca. Los
     * estatus que no manejamos aquí (Pendiente, Desconsolidado, Ingresado,
     * Liberado en terminal, Modulado, Inspección fuera de puerto, Entregado)
     * no hacen nada — Modulado y Entregado ya avisan por su cuenta, y los
     * demás no los pidió el usuario.
     */
    public function notifyStatusReached(ImportRequest $import, string $status): void
    {
        match ($status) {
            ImportRequestWorkflow::CAPTURED => $this->notifyCaptured($import),
            ImportRequestWorkflow::REVALIDATED => $this->notifyRevalidated($import),
            ImportRequestWorkflow::PAID => $this->notifyPaid($import),
            ImportRequestWorkflow::SCHEDULED => $this->notifyScheduled($import),
            ImportRequestWorkflow::EMPTY_RETURNED => $this->notifyEmptyReturned($import),
            ImportRequestWorkflow::FINISHED => $this->notifyFinished($import),
            default => null,
        };
    }

    public function notifyCaptured(ImportRequest $import): void
    {
        $documents = [];
        $attachments = [];

        $this->collectDocument($import, RequiredDocumentType::PROFORMA, $documents, $attachments);

        $advanceRequestRoutes = $this->documentRoutes($import, RequiredDocumentType::ADVANCE_REQUEST);

        foreach ($advanceRequestRoutes as $index => $route) {
            $attachments[] = ['route' => $route, 'name' => sprintf('Solicitud de anticipo %d.%s', $index + 1, pathinfo($route, PATHINFO_EXTENSION))];
        }

        if ($advanceRequestRoutes !== []) {
            $documents[] = ['label' => RequiredDocumentType::ADVANCE_REQUEST, 'attached' => true];
        }

        $this->send(
            $import,
            sprintf('Expediente %s capturado', $import->getClientReference()),
            'Se dio de alta el pedimento de tu expediente.',
            $documents,
            $attachments,
        );
    }

    public function notifyRevalidated(ImportRequest $import): void
    {
        $documents = [];
        $attachments = [];

        $this->collectDocument($import, RequiredDocumentType::REVALIDATED_BL, $documents, $attachments);

        $this->send(
            $import,
            sprintf('Expediente %s revalidado', $import->getClientReference()),
            'El BL de tu expediente ya fue revalidado.',
            $documents,
            $attachments,
        );
    }

    public function notifyPaid(ImportRequest $import): void
    {
        $documents = [];
        $attachments = [];

        $this->collectDocument($import, RequiredDocumentType::FULL_PEDIMENTO, $documents, $attachments);
        $this->collectDocument($import, RequiredDocumentType::SIMPLIFIED_PEDIMENTO, $documents, $attachments);

        $this->send(
            $import,
            sprintf('Expediente %s pagado', $import->getClientReference()),
            'Tu pedimento ya fue pagado.',
            $documents,
            $attachments,
        );
    }

    public function notifyScheduled(ImportRequest $import): void
    {
        $documents = [];
        $attachments = [];

        $this->collectDocument($import, RequiredDocumentType::SCHEDULE_PROOF, $documents, $attachments);

        $this->send(
            $import,
            sprintf('Expediente %s programado', $import->getClientReference()),
            'Tu expediente ya tiene cita programada.',
            $documents,
            $attachments,
        );
    }

    /**
     * A diferencia de las demas, no depende de un documento: el propio
     * transportista y la cita ya son la constancia de que el camión salió.
     */
    public function notifyInTransit(ImportRequest $import, Delivery $delivery): void
    {
        $this->send(
            $import,
            sprintf('Expediente %s en tránsito', $import->getClientReference()),
            'Tu mercancía ya va en tránsito.',
            [],
            [],
            ['delivery' => $delivery],
        );
    }

    public function notifyEmptyReturned(ImportRequest $import): void
    {
        $this->send(
            $import,
            sprintf('Expediente %s: vacío devuelto', $import->getClientReference()),
            'Ya se devolvió el contenedor vacío de tu expediente.',
            [],
            [],
        );
    }

    public function notifyFinished(ImportRequest $import): void
    {
        $this->send(
            $import,
            sprintf('Expediente %s finalizado', $import->getClientReference()),
            'Tu expediente fue finalizado.',
            [],
            [],
        );
    }

    /**
     * @param list<array{label: string, attached: bool}> $documents
     * @param list<array{route: string, name: string}>    $attachments
     * @param array<string, mixed>                        $extraContext
     */
    private function send(ImportRequest $import, string $subject, string $headline, array $documents, array $attachments, array $extraContext = []): void
    {
        $to = $this->recipients->clientEmails($import);

        if ($to === []) {
            return;
        }

        $cc = $this->recipients->executiveEmails();

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject($subject)
            ->htmlTemplate('emails/import_status_update.html.twig')
            ->context($extraContext + [
                'import' => $import,
                'headline' => $headline,
                'documents' => $documents,
            ])
            ->to(...$to);

        if ($cc !== []) {
            $email->cc(...$cc);
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
     * @param list<array{label: string, attached: bool}> $documents
     * @param list<array{route: string, name: string}>   $attachments
     */
    private function collectDocument(ImportRequest $import, string $type, array &$documents, array &$attachments): void
    {
        $route = $this->documentRoute($import, $type);
        $documents[] = ['label' => $type, 'attached' => $route !== null];

        if ($route !== null) {
            $attachments[] = ['route' => $route, 'name' => $type.'.'.pathinfo($route, PATHINFO_EXTENSION)];
        }
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

    /**
     * @return list<string>
     */
    private function documentRoutes(ImportRequest $import, string $type): array
    {
        $routes = [];

        foreach ($import->getRequiredDocuments() as $document) {
            if ($document->getType() === $type && $document->getRoute() !== null) {
                $routes[] = $document->getRoute();
            }
        }

        return $routes;
    }
}
