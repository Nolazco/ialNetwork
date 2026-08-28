<?php

namespace App\Controller;

use App\Entity\LegacyPrevioReport;
use App\Notification\LegacyPrevioMailer;
use App\Previo\PrevioReportPdfGenerator;
use App\Workflow\InspectionAuthorityCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Puente público (sin login) para clientes que todavía no se cambian al
 * módulo de Reportes de previo dentro de un expediente real: mismo
 * formulario que la página vieja (previos.html/prev.php), sin credenciales
 * hardcodeadas y dejando un registro que el ejecutivo puede revisar en
 * "Solicitudes públicas". Sin expediente real, referencia/cliente/correo y
 * el tipo de carga son texto libre en vez de venir de un ImportRequest.
 */
class LegacyPrevios extends AbstractController
{
    public const TYPES = ['Inspección', 'Ocular', 'Desycon'];
    public const CARGO_TYPES = ['container' => 'Contenedor', 'lcl' => 'Carga suelta'];
    public const PRESENTATIONS = ['Cajas', 'Pallets', 'Cuñetes'];
    public const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Un despacho con muchas mercancías puede traer fácil 50+ fotos de
     * evidencia; el límite real de cuántas se aceptan por request lo pone
     * php.ini (max_file_uploads), esto solo evita un número arbitrariamente
     * alto.
     */
    public const MAX_PHOTOS = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PrevioReportPdfGenerator $pdfGenerator,
        private readonly LegacyPrevioMailer $mailer,
    ) {
    }

    #[Route('/legacy/reportes', name: 'legacy_previo_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('/legacy/previo.html.twig', [
            'types' => self::TYPES,
            'cargoTypes' => self::CARGO_TYPES,
            'authorities' => InspectionAuthorityCatalog::COMMON,
            'presentations' => self::PRESENTATIONS,
            'maxPhotos' => self::MAX_PHOTOS,
        ]);
    }

    #[Route('/legacy/reportes', name: 'legacy_previo_create', methods: ['POST'])]
    public function create(Request $r): Response
    {
        if (!$this->isCsrfTokenValid('legacy_previo_create', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('legacy_previo_new');
        }

        // Hasta MAX_PHOTOS fotos, cada una reescalada con GD y metida al ZIP:
        // con muchas fotos grandes esto puede tardar más que el
        // max_execution_time por defecto (120s).
        set_time_limit(300);

        $referencia = trim((string) $r->request->get('referencia'));
        $cliente = trim((string) $r->request->get('cliente'));
        $correo = trim((string) $r->request->get('correo'));
        $type = (string) $r->request->get('type');
        $cargoType = (string) $r->request->get('cargoType');

        if ($referencia === '' || $cliente === '' || $correo === '' || !in_array($type, self::TYPES, true) || !array_key_exists($cargoType, self::CARGO_TYPES)) {
            $this->addFlash('error', 'Referencia, cliente, correo, tipo de previo y tipo de carga son obligatorios.');

            return $this->redirectToRoute('legacy_previo_new');
        }

        $place = trim((string) $r->request->get('place'));
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('date'));

        if ($place === '' || !$date) {
            $this->addFlash('error', 'El lugar y la fecha del previo son obligatorios.');

            return $this->redirectToRoute('legacy_previo_new');
        }

        $startTime = $this->parseTime((string) $r->request->get('startTime'));
        $endTime = $this->parseTime((string) $r->request->get('endTime'));

        $authority = null;

        if ($type === 'Inspección') {
            $chosen = $r->request->all('authority');
            $other = trim((string) $r->request->get('authorityOther'));

            if (is_array($chosen)) {
                $chosen = array_values(array_filter($chosen, fn ($value) => in_array($value, InspectionAuthorityCatalog::COMMON, true)));
            } else {
                $chosen = [];
            }

            if ($other !== '') {
                $chosen[] = $other;
            }

            $authority = $chosen === [] ? null : implode(', ', $chosen);
        }

        $presentation = null;
        $chosenPresentations = $r->request->all('presentation');
        $presentationOther = trim((string) $r->request->get('presentationOther'));

        if (is_array($chosenPresentations)) {
            $chosenPresentations = array_values(array_filter($chosenPresentations, fn ($value) => in_array($value, self::PRESENTATIONS, true)));
        } else {
            $chosenPresentations = [];
        }

        if ($presentationOther !== '') {
            $chosenPresentations[] = $presentationOther;
        }

        if ($chosenPresentations !== []) {
            $presentation = implode(', ', $chosenPresentations);
        }

        $goods = $this->filterBlankLines($r->request->all('goods'));

        $lots = [];

        foreach ($r->request->all('lotIdentifier') as $index => $identifier) {
            $identifier = trim((string) $identifier);
            $observations = trim((string) ($r->request->all('lotObservations')[$index] ?? ''));

            if ($identifier === '' && $observations === '') {
                continue;
            }

            $lots[] = ['identificador' => $identifier, 'observaciones' => $observations];
        }

        $report = new LegacyPrevioReport();
        $report->setReferencia($referencia);
        $report->setCliente($cliente);
        $report->setCorreo($correo);
        $report->setCargoType($cargoType);
        $report->setType($type);
        $report->setAuthority($authority);
        $report->setPlace($place);
        $report->setDate($date);
        $report->setStartTime($startTime);
        $report->setEndTime($endTime);
        $report->setContainerNum($cargoType === 'container' ? $this->nullableTrim($r->request->get('containerNum')) : null);
        $report->setSealOrigin($cargoType === 'container' ? $this->nullableTrim($r->request->get('sealOrigin')) : null);
        $report->setSealFinal($cargoType === 'container' ? $this->nullableTrim($r->request->get('sealFinal')) : null);
        $report->setPlates($this->nullableTrim($r->request->get('plates')));
        $report->setTransportCompanyName($this->nullableTrim($r->request->get('transportCompanyName')));
        $report->setGoods($goods);
        $report->setLots($lots);
        $report->setPresentation($presentation);
        $report->setQuantity($this->nullableTrim($r->request->get('quantity')));
        $report->setNotes($this->nullableTrim($r->request->get('notes')));
        $report->setCreatedAt(new \DateTimeImmutable());
        $report->setPdfRoute('');

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $folder = 'uploads/legacy-previos/'.$report->getId();
        $photosFolder = $folder.'/fotos';

        if (!is_dir($photosFolder) && !mkdir($photosFolder, 0777, true) && !is_dir($photosFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta de fotos.');

            return $this->redirectToRoute('legacy_previo_new');
        }

        $photoPaths = [];
        $rejected = [];

        foreach (array_slice($r->files->all('photos'), 0, self::MAX_PHOTOS) as $index => $photo) {
            if (!$photo || !$photo->isValid()) {
                continue;
            }

            $original = $photo->getClientOriginalName();
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            if (!in_array($extension, self::PHOTO_EXTENSIONS, true)) {
                $rejected[] = $original;

                continue;
            }

            $name = sprintf('foto-%d.%s', $index + 1, $extension);

            try {
                $photo->move($photosFolder, $name);
            } catch (FileException) {
                $rejected[] = $original;

                continue;
            }

            $photoPaths[] = $photosFolder.'/'.$name;
        }

        if ($rejected !== []) {
            $this->addFlash('error', sprintf(
                'No se aceptaron: %s. Formatos permitidos: %s.',
                implode(', ', $rejected),
                implode(', ', self::PHOTO_EXTENSIONS)
            ));
        }

        if ($photoPaths !== []) {
            $zipPath = $folder.'/fotos.zip';
            $zip = new \ZipArchive();

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($photoPaths as $path) {
                    $zip->addFile($path, basename($path));
                }

                $zip->close();
                $report->setPhotosZipRoute($zipPath);
            }
        }

        $previoData = [
            'place' => $report->getPlace(),
            'date' => $report->getDate(),
            'startTime' => $report->getStartTime(),
            'endTime' => $report->getEndTime(),
            'containerNum' => $report->getContainerNum(),
            'sealOrigin' => $report->getSealOrigin(),
            'sealFinal' => $report->getSealFinal(),
            'type' => $report->getType(),
            'plates' => $report->getPlates(),
            'transportCompanyName' => $report->getTransportCompanyName(),
            'authority' => $report->getAuthority(),
            'presentation' => $report->getPresentation(),
            'quantity' => $report->getQuantity(),
            'goods' => $report->getGoods(),
            'lots' => $report->getLots(),
            'notes' => $report->getNotes(),
        ];
        $importData = [
            'clientReference' => $report->getReferencia(),
            'idCompany' => ['name' => $report->getCliente()],
            'type' => $report->getCargoType(),
        ];

        $pdfBytes = $this->pdfGenerator->generateFromArrays($previoData, $importData, $photoPaths);
        $pdfPath = $folder.'/reporte.pdf';
        file_put_contents($pdfPath, $pdfBytes);
        $report->setPdfRoute($pdfPath);

        $this->entityManager->flush();

        $this->mailer->notify($report);

        $this->addFlash('success', 'Reporte enviado, puede cerrar esta página.');

        return $this->redirectToRoute('legacy_previo_new');
    }

    private function parseTime(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('H:i', $value);

        return $parsed ?: null;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function filterBlankLines(mixed $lines): array
    {
        if (!is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($line) => trim((string) $line), $lines), static fn ($line) => $line !== ''));
    }
}
