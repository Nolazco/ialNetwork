<?php

namespace App\Controller;

use App\Entity\ImportRequest;
use App\Entity\PrevioReport;
use App\Entity\User;
use App\Notification\PrevioReportMailer;
use App\Previo\PrevioReportPdfGenerator;
use App\Security\CompanyAccess;
use App\Service\UploadPath;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\InspectionAuthorityCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Reportes de previo/inspección: hasta ahora se generaban a mano en una
 * página aparte (previos.html/prev.php), sin ninguna liga con el expediente
 * real. Aquí quedan ligados al expediente y el correo se manda a los
 * destinatarios reales (cliente afiliado + ejecutivos), ademas de las
 * direcciones fijas heredadas del sistema anterior.
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardPrevios extends AbstractController
{
    public const TYPES = ['Inspección', 'Ocular', 'Desycon'];

    /** Presentaciones fijas del formulario viejo; "Otro" se captura aparte. */
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
        private readonly CompanyAccess $companyAccess,
        private readonly PrevioReportPdfGenerator $pdfGenerator,
        private readonly PrevioReportMailer $mailer,
        private readonly UploadPath $uploadPath,
    ) {
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/previos/nuevo', name: 'case_file_previo_new', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function new(#[MapEntity(id: 'id')] ImportRequest $import): Response
    {
        if (!$this->companyAccess->canAccess($import->getIdCompany())) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/previoForm.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'import' => $import,
            'types' => self::TYPES,
            'authorities' => InspectionAuthorityCatalog::COMMON,
            'presentations' => self::PRESENTATIONS,
            'maxPhotos' => self::MAX_PHOTOS,
            'previewTo' => $this->mailer->resolveTo($import),
            'previewCc' => $this->mailer->resolveCc(),
        ]);
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/previos', name: 'case_file_previo_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->companyAccess->canAccess($import->getIdCompany())) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        if (!$this->isCsrfTokenValid('case_file_previo_create', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Hasta MAX_PHOTOS fotos, cada una reescalada con GD y metida al ZIP:
        // con muchas fotos grandes esto puede tardar más que el
        // max_execution_time por defecto (120s).
        set_time_limit(300);

        $type = (string) $r->request->get('type');

        if (!in_array($type, self::TYPES, true)) {
            $this->addFlash('error', 'Selecciona un tipo de previo válido.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $place = trim((string) $r->request->get('place'));
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('date'));

        if ($place === '' || !$date) {
            $this->addFlash('error', 'El lugar y la fecha del previo son obligatorios.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
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

        $containerNum = null;

        if ($import->getType() === ImportRequestWorkflow::TYPE_CONTAINER) {
            $containerId = (string) $r->request->get('containerId');

            foreach ($import->getContainers() as $container) {
                if ((string) $container->getId() === $containerId) {
                    $containerNum = $container->getNum();

                    break;
                }
            }
        }

        /** @var User $user */
        $user = $this->getUser();

        $previo = new PrevioReport();
        $previo->setReference($import);
        $previo->setType($type);
        $previo->setAuthority($authority);
        $previo->setPlace($place);
        $previo->setDate($date);
        $previo->setStartTime($startTime);
        $previo->setEndTime($endTime);
        $previo->setContainerNum($containerNum);
        $previo->setSealOrigin($this->nullableTrim($r->request->get('sealOrigin')));
        $previo->setSealFinal($this->nullableTrim($r->request->get('sealFinal')));
        $previo->setPlates($this->nullableTrim($r->request->get('plates')));
        $previo->setTransportCompanyName($this->nullableTrim($r->request->get('transportCompanyName')));
        $previo->setGoods($goods);
        $previo->setLots($lots);
        $previo->setPresentation($presentation);
        $previo->setQuantity($this->nullableTrim($r->request->get('quantity')));
        $previo->setNotes($this->nullableTrim($r->request->get('notes')));
        $previo->setCreatedAt(new \DateTimeImmutable());
        $previo->setCreatedBy($user);
        $previo->setPdfRoute('');

        $import->addPrevioReport($previo);
        $this->entityManager->persist($previo);
        $this->entityManager->flush();

        $folder = 'uploads/previos/'.$import->getId().'/'.$previo->getId();
        $photosFolder = $folder.'/fotos';

        if (!is_dir($photosFolder) && !mkdir($photosFolder, 0777, true) && !is_dir($photosFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta de fotos.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
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
                $previo->setPhotosZipRoute($zipPath);
            }
        }

        // El PDF si se protege (a diferencia de las fotos/zip de arriba, que
        // a proposito se quedan bajo public/ para poder descargarse sin
        // sesion en cualquier momento): se escribe fuera de public/, aunque
        // el valor guardado en pdfRoute sigue siendo la misma cadena
        // relativa de siempre (se resuelve contra var/ al servirlo, ver
        // UploadPath y DashboardCaseFiles::downloadPrevioPdf()).
        $pdfRelativePath = $folder.'/reporte.pdf';
        $pdfAbsolutePath = $this->uploadPath->resolve($pdfRelativePath);

        if (!is_dir(dirname($pdfAbsolutePath))) {
            mkdir(dirname($pdfAbsolutePath), 0777, true);
        }

        $pdfBytes = $this->pdfGenerator->generate($previo, $photoPaths);
        file_put_contents($pdfAbsolutePath, $pdfBytes);
        $previo->setPdfRoute($pdfRelativePath);

        $this->entityManager->flush();

        $this->mailer->notify($previo);

        $this->addFlash('success', 'Reporte de previo generado y enviado por correo.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/previos/{previo}/eliminar', name: 'case_file_previo_delete', requirements: ['id' => '\d+', 'previo' => '\d+'], methods: ['POST'])]
    public function delete(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'previo')] PrevioReport $previo, Request $r): Response
    {
        if (!$this->companyAccess->canAccess($import->getIdCompany())) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        if (!$this->isCsrfTokenValid('case_file_previo_delete', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($previo->getReference() !== $import) {
            $this->addFlash('error', 'Ese reporte no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($previo->getPdfRoute() && is_file($this->uploadPath->resolve($previo->getPdfRoute()))) {
            unlink($this->uploadPath->resolve($previo->getPdfRoute()));
        }

        // Las fotos/zip se quedan bajo public/ a proposito (ver create()),
        // asi que aqui si se borran con la ruta relativa de siempre.
        if ($previo->getPhotosZipRoute() && is_file($previo->getPhotosZipRoute())) {
            unlink($previo->getPhotosZipRoute());
        }

        $this->entityManager->remove($previo);
        $this->entityManager->flush();

        $this->addFlash('success', 'Reporte de previo eliminado.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
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
     * @param mixed $lines
     *
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
