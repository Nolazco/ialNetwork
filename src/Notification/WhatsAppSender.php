<?php

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Avisos por WhatsApp a traves de un puente propio (Baileys corriendo en el
 * mismo servidor de producción — ver scripts/whatsapp-bridge-server.js, solo
 * escucha en localhost). No es infraestructura oficial de WhatsApp Business,
 * asi que una falla aqui JAMAS debe tronar el flujo que la dispara
 * (moduladoes, poller de SOIA) — se traga el error y se registra, nada mas.
 *
 * Se apaga solo con dejar WHATSAPP_API_URL vacio.
 */
final class WhatsAppSender
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'WHATSAPP_API_URL')]
        private readonly string $apiUrl,
        #[Autowire(env: 'WHATSAPP_API_TOKEN')]
        private readonly string $apiToken,
    ) {
    }

    /**
     * @param list<string> $destinatarios
     */
    public function send(array $destinatarios, string $mensaje): void
    {
        if ($this->apiUrl === '') {
            return;
        }

        foreach ($destinatarios as $destino) {
            $this->sendOne($destino, $mensaje);
        }
    }

    private function sendOne(string $destino, string $mensaje): void
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'json' => ['numero' => $destino, 'mensaje' => $mensaje],
                'headers' => ['Authorization' => 'Bearer ' . $this->apiToken],
                'timeout' => 5,
            ]);
            // Se fuerza la lectura del cuerpo para detectar aqui mismo un
            // error HTTP (4xx/5xx) o de transporte, en vez de que reviente
            // mas adelante sin nadie atrapandolo.
            $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->warning('No se pudo mandar el aviso de WhatsApp (puente no disponible).', [
                'destino' => $destino,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
