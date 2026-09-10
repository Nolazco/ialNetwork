<?php

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Avisos por WhatsApp a traves de un puente casero (whatsapp-web.js corriendo
 * en una laptop, vinculado a un numero personal, ver WHATSAPP_API_URL) —
 * solucion temporal mientras se evalua la API oficial de WhatsApp Business.
 * No es infraestructura confiable: el puente puede estar apagado, sin
 * internet, o el numero puede quedar bloqueado por WhatsApp en cualquier
 * momento, asi que una falla aqui JAMAS debe tronar el flujo que la dispara
 * (moduladoes, poller de SOIA) — se traga el error y se registra, nada mas.
 *
 * Se apaga solo con dejar WHATSAPP_API_URL vacio — asi es como queda en
 * produccion por ahora, mientras esto sigue siendo un experimento.
 */
final class WhatsAppSender
{
    /** @var list<string> */
    private readonly array $supervisorRecipients;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'WHATSAPP_API_URL')]
        private readonly string $apiUrl,
        #[Autowire(env: 'WHATSAPP_SUPERVISOR_RECIPIENTS')]
        string $supervisorRecipientsRaw,
    ) {
        $this->supervisorRecipients = array_values(array_filter(array_map('trim', explode(';', $supervisorRecipientsRaw))));
    }

    public function notifySupervisors(string $mensaje): void
    {
        if ($this->apiUrl === '') {
            return;
        }

        foreach ($this->supervisorRecipients as $destino) {
            $this->send($destino, $mensaje);
        }
    }

    private function send(string $destino, string $mensaje): void
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'json' => ['numero' => $destino, 'mensaje' => $mensaje],
                'timeout' => 5,
            ]);
            // Se fuerza la lectura del cuerpo para detectar aqui mismo un
            // error HTTP (4xx/5xx) o de transporte, en vez de que reviente
            // mas adelante sin nadie atrapandolo.
            $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->warning('No se pudo mandar el aviso de WhatsApp (puente casero no disponible).', [
                'destino' => $destino,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
