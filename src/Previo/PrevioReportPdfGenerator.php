<?php

namespace App\Previo;

use App\Entity\PrevioReport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Genera el PDF del reporte de previo.
 *
 * Todo (logo y fotos) se incrusta como data: URI directamente en el HTML: es
 * mas simple que resolverle rutas de disco a Dompdf (el docroot real es
 * public/, no la raiz del proyecto), y ya es como lo hacia el script viejo
 * para las fotos.
 */
final class PrevioReportPdfGenerator
{
    /** Ancho maximo al que se reescala cada foto antes de incrustarla. */
    private const MAX_PHOTO_WIDTH = 1000;

    private const LOGO_PATH = __DIR__.'/../../public/index/img/IAL NETWORK.png';

    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @param list<string> $photoPaths rutas absolutas de las fotos ya guardadas en disco
     */
    public function generate(PrevioReport $previo, array $photoPaths): string
    {
        return $this->render(['previo' => $previo, 'import' => $previo->getReference()], $photoPaths);
    }

    /**
     * Igual que generate(), pero para cuando no hay un PrevioReport/ImportRequest
     * reales detrás (el puente público /legacy/reportes, sin sesión ni
     * expediente): $previoData/$importData son arreglos con las mismas claves
     * que la plantilla ya espera de las entidades (previo.date, import.idCompany.name...),
     * Twig resuelve el acceso por punto igual sobre un array que sobre un getter.
     *
     * @param array<string, mixed> $previoData
     * @param array<string, mixed> $importData
     * @param list<string>         $photoPaths rutas absolutas de las fotos ya guardadas en disco
     */
    public function generateFromArrays(array $previoData, array $importData, array $photoPaths): string
    {
        return $this->render(['previo' => $previoData, 'import' => $importData], $photoPaths);
    }

    /**
     * @param array{previo: mixed, import: mixed} $context
     * @param list<string>                         $photoPaths
     */
    private function render(array $context, array $photoPaths): string
    {
        $html = $this->twig->render('previos/pdf.html.twig', $context + [
            'logo' => $this->fileToDataUri(self::LOGO_PATH),
            // El reporte solo trae evidencia de las primeras fotos; el resto
            // va completo en el ZIP que se manda por separado.
            'photos' => array_map(fn (string $path) => $this->embeddablePhoto($path), array_slice($photoPaths, 0, 4)),
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function fileToDataUri(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    /**
     * Reescala la foto antes de incrustarla: un data URI con la foto tal
     * cual como la manda un celular hace el HTML pesadísimo y Dompdf se
     * pone lento (no hay cola en este proyecto, el envío es síncrono).
     */
    private function embeddablePhoto(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $size = @getimagesize($path);
        $raw = file_get_contents($path);

        if ($size === false || $raw === false) {
            return $this->fileToDataUri($path);
        }

        [$width, $height] = $size;
        $source = @imagecreatefromstring($raw);

        if ($source === false) {
            return $this->fileToDataUri($path);
        }

        if ($width > self::MAX_PHOTO_WIDTH) {
            $ratio = self::MAX_PHOTO_WIDTH / $width;
            $newWidth = self::MAX_PHOTO_WIDTH;
            $newHeight = (int) round($height * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        ob_start();
        imagejpeg($source, null, 80);
        $jpeg = ob_get_clean();
        imagedestroy($source);

        return 'data:image/jpeg;base64,'.base64_encode((string) $jpeg);
    }
}
