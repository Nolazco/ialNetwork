<?php

namespace App\Soia;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Consulta el estatus de un pedimento en el portal SOIA del SAT
 * (aplicacionesc.mat.sat.gob.mx/SOIANET), simulando el postback del
 * formulario clasico de ASP.NET WebForms.
 *
 * No usa un navegador real: el formulario "por pedimento" no depende de JS
 * (aduana, año, patente, documento y el boton "Buscar" son controles
 * server-side normales), asi que un POST HTTP con los mismos campos, tokens
 * __VIEWSTATE/__EVENTVALIDATION incluidos, basta.
 *
 * El portal muestra ocasionalmente un modal de captcha ("Validación de
 * Seguridad SOIA"); si aparece, esta clase NO intenta resolverlo — lo trata
 * como una consulta fallida (found=false) para que quien la llame reintente
 * mas tarde, igual que ante el error de "puerta de enlace errónea".
 */
final class SoiaClient
{
    private const URL = 'https://aplicacionesc.mat.sat.gob.mx/SOIANET/oia_consultarap_cep.aspx';

    private const MAX_ATTEMPTS = 5;

    /**
     * El servidor negocia TLS con un grupo Diffie-Hellman por debajo del
     * minimo que exige OpenSSL 3 (nivel de seguridad por defecto): sin bajar
     * el nivel para esta conexion en particular, el handshake falla con
     * "dh key too small" antes de llegar siquiera a HTTP.
     */
    private const REQUEST_OPTIONS = ['ciphers' => 'DEFAULT@SECLEVEL=1'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'SOIA_PATENTE')]
        private readonly string $patente,
    ) {
    }

    /**
     * @param string $aduanaSoiaCode Id del combo "cmbAduanas" del portal (ver
     *                                AduanaCatalog::soiaCode()), no la clave
     *                                real de 2 dígitos del pedimento.
     */
    public function consultar(string $pedimento, string $aduanaSoiaCode): SoiaResult
    {
        // El "documento" que usa este portal no lleva el año codificado (se
        // confirmó contra datos reales: no es el numero de pedimento con el
        // formato clasico AAPPTTNNNNNNN), asi que se deja el año actual, que
        // es lo que la propia pagina trae seleccionado por defecto.
        $anio = (new \DateTimeImmutable())->format('Y');

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $tokens = $this->fetchTokens();
            } catch (\Throwable) {
                continue;
            }

            try {
                $response = $this->httpClient->request('POST', self::URL, self::REQUEST_OPTIONS + [
                    'body' => array_merge($tokens, [
                        '__EVENTTARGET' => '',
                        '__EVENTARGUMENT' => '',
                        'cmbAduanas' => $aduanaSoiaCode,
                        'cmbAnios' => $anio,
                        'txtPatente' => $this->patente,
                        'txtDocumento' => $pedimento,
                        'tpoConsulta' => 'rblPatente',
                        'cmdBuscar' => 'Buscar',
                    ]),
                    'timeout' => 30,
                ]);

                $html = $response->getContent(false);
            } catch (\Throwable) {
                continue;
            }

            if (str_contains($html, 'Puerta de enlace errónea') || str_contains($html, 'puerta de enlace errónea')) {
                continue;
            }

            if (!str_contains($html, 'id="grdPedimentos"')) {
                // Sin tabla de resultados: puede ser el modal de captcha u
                // otro bloqueo del portal. No se intenta resolver nada, solo
                // se reporta como no encontrado para reintentar despues.
                return SoiaResult::notFound('El portal no devolvió resultados (posible captcha o bloqueo temporal).');
            }

            return $this->parseResult($html, $pedimento);
        }

        return SoiaResult::notFound('El portal no respondió tras varios intentos.');
    }

    /**
     * @return array<string, string>
     */
    private function fetchTokens(): array
    {
        $response = $this->httpClient->request('GET', self::URL, self::REQUEST_OPTIONS + ['timeout' => 30]);
        $html = $response->getContent(false);

        return [
            '__VIEWSTATE' => $this->extractHiddenValue($html, '__VIEWSTATE'),
            '__VIEWSTATEENCRYPTED' => $this->extractHiddenValue($html, '__VIEWSTATEENCRYPTED'),
            '__EVENTVALIDATION' => $this->extractHiddenValue($html, '__EVENTVALIDATION'),
        ];
    }

    private function extractHiddenValue(string $html, string $name): string
    {
        if (preg_match('/id="'.preg_quote($name, '/').'"[^>]*value="([^"]*)"/', $html, $matches) === 1) {
            return html_entity_decode($matches[1]);
        }

        return '';
    }

    private function parseResult(string $html, string $pedimento): SoiaResult
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//table[@id="grdPedimentos"]//tr');

        if ($rows === false || $rows->length < 2) {
            return SoiaResult::notFound('La tabla de resultados llegó vacía.');
        }

        // La primera fila es el encabezado (DOCUMENTO, PATENTE, ESTADO, FECHA...).
        for ($i = 1; $i < $rows->length; ++$i) {
            $cells = $xpath->query('.//td', $rows->item($i));

            if ($cells === false || $cells->length < 4) {
                continue;
            }

            $documento = trim($cells->item(0)->textContent);

            // Coincidencia exacta: el portal no siempre filtra bien por
            // documento, asi que no basta con tomar la primera fila que venga.
            if ($documento !== $pedimento) {
                continue;
            }

            $estado = trim($cells->item(2)->textContent);
            $fechaTexto = trim($cells->item(3)->textContent);
            $fecha = \DateTimeImmutable::createFromFormat('d/m/Y H:i:s', $fechaTexto) ?: null;

            return SoiaResult::of($estado, $fecha);
        }

        return SoiaResult::notFound('Ese pedimento no aparece en la respuesta del SOIA.');
    }
}
