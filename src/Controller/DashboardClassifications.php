<?php

namespace App\Controller;

use App\Entity\ClassificationRequest;
use App\Entity\Company;
use App\Entity\User;
use App\Notification\ClassificationMailer;
use App\Security\CompanyAccess;
use App\Service\UploadPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Solicitudes de clasificación de mercancía: el cliente (o el ejecutivo, en su
 * nombre) comparte los datos del producto y sus archivos de soporte, la app
 * manda todo al equipo de clasificadores y ellos contestan desde su propio
 * correo. La app no hace seguimiento automático de esa respuesta, pero el
 * ejecutivo puede capturar la fracción arancelaria que confirmaron (ver
 * confirmTariffFraction()) — eso es lo único que queda registrado del
 * resultado, y lo que permite buscar mercancía ya clasificada (ver search())
 * para no repetirle el trabajo al clasificador.
 *
 * Visible para cualquier rol salvo transportista, que no tiene nada que
 * clasificar.
 */
#[IsGranted(new Expression('is_granted("ROLE_EXECUTIVE") or is_granted("ROLE_CLIENT")'))]
class DashboardClassifications extends AbstractController
{
    /** Mismas extensiones que se aceptan en el resto de la app: nada ejecutable. */
    public const ALLOWED_EXTENSIONS = ['pdf', 'xml', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'docx', 'csv'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyAccess $companyAccess,
        private readonly ClassificationMailer $mailer,
        private readonly UploadPath $uploadPath,
    ) {
    }

    /**
     * @return list<Company>
     */
    private function allowedCompanies(User $user): array
    {
        $companyRepo = $this->entityManager->getRepository(Company::class);

        if ($this->isGranted('ROLE_EXECUTIVE')) {
            return $companyRepo->findAll();
        }

        return $companyRepo->findAssociatedCompanies($user);
    }

    /**
     * A qué empresas debe limitarse una búsqueda: null significa "todas" (el
     * ejecutivo puede ver mercancía clasificada de cualquier cliente, ya que
     * la fracción depende del producto, no de quién lo importa); el cliente
     * solo ve las suyas.
     *
     * @return list<Company>|null
     */
    private function searchScopeFor(User $user): ?array
    {
        return $this->isGranted('ROLE_EXECUTIVE') ? null : $this->allowedCompanies($user);
    }

    #[Route('/dashboard/clasificaciones', name: 'classifications')]
    public function classifications(Request $r): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $q = trim((string) $r->query->get('q'));

        $requests = $this->entityManager->getRepository(ClassificationRequest::class)
            ->search($q !== '' ? $q : null, $this->searchScopeFor($user));

        return $this->render('/dashboard/classifications.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'requests' => $requests,
            'q' => $q,
        ]);
    }

    /**
     * Búsqueda en vivo (AJAX) de mercancía ya clasificada — la usa el
     * formulario de "Nueva solicitud" para avisar, antes de mandarla, si ese
     * producto ya se clasificó. Es de solo lectura, así que no exige CSRF
     * (igual que cualquier otro GET de la app).
     */
    #[Route('/dashboard/clasificaciones/buscar', name: 'classification_search', methods: ['GET'])]
    public function search(Request $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $q = trim((string) $r->query->get('q'));

        if (mb_strlen($q) < 3) {
            return new JsonResponse(['results' => []]);
        }

        $requests = $this->entityManager->getRepository(ClassificationRequest::class)
            ->search($q, $this->searchScopeFor($user), 8);

        $results = array_map(static fn (ClassificationRequest $c): array => [
            'merchandiseName' => $c->getMerchandiseName(),
            'chemicalName' => $c->getChemicalName(),
            'casNumber' => $c->getCasNumber(),
            'company' => $c->getCompany()?->getName(),
            'confirmedTariffFraction' => $c->getConfirmedTariffFraction(),
            'createdAt' => $c->getCreatedAt()?->format('d/m/Y'),
        ], $requests);

        return new JsonResponse(['results' => $results]);
    }

    /**
     * El ejecutivo captura la fracción que el equipo de clasificadores ya
     * confirmó por su propio correo. Es la única forma en que ese resultado
     * queda en el sistema (ver docblock de la clase).
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/clasificaciones/{id}/confirmar', name: 'classification_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirmTariffFraction(#[MapEntity(id: 'id')] ClassificationRequest $classificationRequest, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('classification_confirm', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('classifications');
        }

        $fraction = trim((string) $r->request->get('confirmedTariffFraction'));

        if (!$this->isValidTariffFraction($fraction)) {
            $this->addFlash('error', 'La fracción arancelaria va a 10 dígitos, con el formato XXXX.XX.XX.XX (ej. 8471.30.01.99).');

            return $this->redirectToRoute('classifications');
        }

        /** @var User $user */
        $user = $this->getUser();

        $classificationRequest->setConfirmedTariffFraction($fraction);
        $classificationRequest->setConfirmedBy($user);
        $classificationRequest->setConfirmedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->addFlash('success', 'Fracción arancelaria confirmada y guardada.');

        return $this->redirectToRoute('classifications');
    }

    #[Route('/dashboard/clasificaciones/nueva', name: 'classification_new', methods: ['GET'])]
    public function newClassification(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/newclassification.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'companies' => $this->allowedCompanies($user),
            'allowedExtensions' => self::ALLOWED_EXTENSIONS,
        ]);
    }

    #[Route('/dashboard/clasificaciones/nueva', name: 'classification_create', methods: ['POST'])]
    public function createClassification(Request $r, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('classification_create', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('classification_new');
        }

        /** @var User $user */
        $user = $this->getUser();

        $company = $this->entityManager->getRepository(Company::class)->find($r->request->get('company'));

        if (!$company || !$this->companyAccess->canAccess($company)) {
            $this->addFlash('error', 'Selecciona una empresa válida.');

            return $this->redirectToRoute('classification_new');
        }

        $merchandiseName = trim((string) $r->request->get('merchandiseName'));
        $merchandiseUse = trim((string) $r->request->get('merchandiseUse'));
        $presentation = trim((string) $r->request->get('presentation'));

        if ($merchandiseName === '' || $merchandiseUse === '' || $presentation === '') {
            $this->addFlash('error', 'El nombre comercial, el uso y la presentación de la mercancía son obligatorios.');

            return $this->redirectToRoute('classification_new');
        }

        $files = $r->files->all('documents');

        if ($files === []) {
            $this->addFlash('error', 'Adjunta al menos un archivo (COA, MSDS, etc.).');

            return $this->redirectToRoute('classification_new');
        }

        $classificationRequest = new ClassificationRequest();
        $classificationRequest->setCompany($company);
        $classificationRequest->setRequestedBy($user);
        $classificationRequest->setMerchandiseName($merchandiseName);
        $classificationRequest->setChemicalName($this->nullableTrim($r->request->get('chemicalName')));
        $classificationRequest->setCasNumber($this->nullableTrim($r->request->get('casNumber')));
        $classificationRequest->setMerchandiseUse($merchandiseUse);
        $classificationRequest->setPresentation($presentation);
        $classificationRequest->setAttachments([]);
        $classificationRequest->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($classificationRequest);
        $this->entityManager->flush();

        $folder = 'uploads/clasificaciones/'.$classificationRequest->getId();
        $physicalFolder = $this->uploadPath->resolve($folder);

        if (!is_dir($physicalFolder) && !mkdir($physicalFolder, 0777, true) && !is_dir($physicalFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta de archivos.');

            return $this->redirectToRoute('classification_new');
        }

        $attachments = [];
        $rejected = [];

        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $rejected[] = $original;

                continue;
            }

            $name = $slugger->slug(pathinfo($original, PATHINFO_FILENAME)).'-'.uniqid().'.'.$extension;

            try {
                $file->move($physicalFolder, $name);
            } catch (FileException) {
                $rejected[] = $original;

                continue;
            }

            $attachments[] = ['nombre' => $original, 'ruta' => $folder.'/'.$name];
        }

        if ($attachments === []) {
            $this->entityManager->remove($classificationRequest);
            $this->entityManager->flush();

            $this->addFlash('error', sprintf(
                'No se aceptó ningún archivo. Formatos permitidos: %s.',
                implode(', ', self::ALLOWED_EXTENSIONS)
            ));

            return $this->redirectToRoute('classification_new');
        }

        $classificationRequest->setAttachments($attachments);
        $this->entityManager->flush();

        $this->mailer->notify($classificationRequest);

        if ($rejected !== []) {
            $this->addFlash('error', sprintf(
                'No se aceptaron: %s. Formatos permitidos: %s.',
                implode(', ', $rejected),
                implode(', ', self::ALLOWED_EXTENSIONS)
            ));
        }

        $this->addFlash('success', 'Solicitud de clasificación enviada.');

        return $this->redirectToRoute('classifications');
    }

    #[Route('/dashboard/clasificaciones/{id}/adjuntos/{index}', name: 'classification_attachment_download', methods: ['GET'])]
    public function downloadAttachment(int $id, int $index): BinaryFileResponse
    {
        $classificationRequest = $this->entityManager->getRepository(ClassificationRequest::class)->find($id);

        if (!$classificationRequest) {
            throw $this->createNotFoundException();
        }

        if (!$this->companyAccess->canAccess($classificationRequest->getCompany())) {
            throw $this->createAccessDeniedException();
        }

        $attachment = $classificationRequest->getAttachments()[$index] ?? null;

        if (!$attachment) {
            throw $this->createNotFoundException();
        }

        $path = $this->uploadPath->resolve($attachment['ruta']);

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $attachment['nombre'] ?? basename($path));

        return $response;
    }

    /**
     * Todos los adjuntos de la solicitud en un solo ZIP, armado al vuelo (no
     * se guarda en disco): es más facil de revisar de un jalon que descargar
     * archivo por archivo, y evita la desconfianza de solo ver clips sueltos.
     */
    #[Route('/dashboard/clasificaciones/{id}/adjuntos.zip', name: 'classification_attachments_zip', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadAttachmentsZip(#[MapEntity(id: 'id')] ClassificationRequest $classificationRequest): BinaryFileResponse
    {
        if (!$this->companyAccess->canAccess($classificationRequest->getCompany())) {
            throw $this->createAccessDeniedException();
        }

        $attachments = $classificationRequest->getAttachments();

        if ($attachments === []) {
            throw $this->createNotFoundException();
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'clasificacion_');
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw $this->createNotFoundException('No se pudo armar el ZIP.');
        }

        // Dos adjuntos pueden compartir el mismo nombre original (ej. dos
        // "MSDS.pdf" de proveedores distintos); sin desempatar, el ZIP se
        // quedaria solo con el ultimo que se le agrego con ese nombre.
        $usedNames = [];

        foreach ($attachments as $attachment) {
            $path = $this->uploadPath->resolve($attachment['ruta']);

            if (!is_file($path)) {
                continue;
            }

            $name = $attachment['nombre'] ?? basename($path);
            $entryName = $name;
            $suffix = 1;

            while (isset($usedNames[$entryName])) {
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $base = pathinfo($name, PATHINFO_FILENAME);
                $entryName = $extension !== '' ? sprintf('%s (%d).%s', $base, ++$suffix, $extension) : sprintf('%s (%d)', $base, ++$suffix);
            }

            $usedNames[$entryName] = true;
            $zip->addFile($path, $entryName);
        }

        $zip->close();

        $downloadName = $this->slugForZip($classificationRequest->getMerchandiseName()).'.zip';

        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function slugForZip(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));

        return $slug !== '' ? 'documentos-'.$slug : 'documentos';
    }

    /**
     * La fracción arancelaria mexicana va a 10 dígitos: 8 de la fracción
     * internacional/nacional (XXXX.XX.XX) más 2 del NICO (identificación
     * comercial), ej. 8471.30.01.99.
     */
    private function isValidTariffFraction(string $fraction): bool
    {
        return preg_match('/^\d{4}\.\d{2}\.\d{2}\.\d{2}$/', $fraction) === 1;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
