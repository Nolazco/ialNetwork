<?php

namespace App\Controller;

use App\Entity\LegacyClassificationRequest;
use App\Notification\LegacyClassificationMailer;
use App\Service\UploadPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Puente público (sin login) para clientes que todavía no se cambian al
 * módulo de Clasificaciones dentro del dashboard: mismo formulario que la
 * página vieja (clasificacion.php/clas.php), pero sin credenciales
 * hardcodeadas y dejando un registro que el ejecutivo puede revisar en
 * "Solicitudes públicas".
 */
class LegacyClassifications extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegacyClassificationMailer $mailer,
        private readonly UploadPath $uploadPath,
    ) {
    }

    #[Route('/legacy/clasificacion', name: 'legacy_classification_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('/legacy/classification.html.twig', [
            'allowedExtensions' => DashboardClassifications::ALLOWED_EXTENSIONS,
        ]);
    }

    #[Route('/legacy/clasificacion', name: 'legacy_classification_create', methods: ['POST'])]
    public function create(Request $r, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('legacy_classification_create', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('legacy_classification_new');
        }

        $requesterName = trim((string) $r->request->get('requesterName'));
        $requesterEmail = trim((string) $r->request->get('requesterEmail'));
        $companyName = trim((string) $r->request->get('companyName'));
        $merchandiseName = trim((string) $r->request->get('merchandiseName'));
        $merchandiseUse = trim((string) $r->request->get('merchandiseUse'));
        $presentation = trim((string) $r->request->get('presentation'));

        if ($requesterName === '' || $requesterEmail === '' || $companyName === '' || $merchandiseName === '' || $merchandiseUse === '' || $presentation === '') {
            $this->addFlash('error', 'Nombre, correo, empresa, mercancía, uso y presentación son obligatorios.');

            return $this->redirectToRoute('legacy_classification_new');
        }

        $files = $r->files->all('documents');

        if ($files === []) {
            $this->addFlash('error', 'Adjunta al menos un archivo (COA, MSDS, etc.).');

            return $this->redirectToRoute('legacy_classification_new');
        }

        $request = new LegacyClassificationRequest();
        $request->setRequesterName($requesterName);
        $request->setRequesterEmail($requesterEmail);
        $request->setCompanyName($companyName);
        $request->setMerchandiseName($merchandiseName);
        $request->setChemicalName($this->nullableTrim($r->request->get('chemicalName')));
        $request->setCasNumber($this->nullableTrim($r->request->get('casNumber')));
        $request->setMerchandiseUse($merchandiseUse);
        $request->setPresentation($presentation);
        $request->setAttachments([]);
        $request->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        $folder = 'uploads/legacy-clasificaciones/'.$request->getId();
        $physicalFolder = $this->uploadPath->resolve($folder);

        if (!is_dir($physicalFolder) && !mkdir($physicalFolder, 0777, true) && !is_dir($physicalFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta de archivos.');

            return $this->redirectToRoute('legacy_classification_new');
        }

        $attachments = [];
        $rejected = [];

        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            if (!in_array($extension, DashboardClassifications::ALLOWED_EXTENSIONS, true)) {
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
            $this->entityManager->remove($request);
            $this->entityManager->flush();

            $this->addFlash('error', sprintf(
                'No se aceptó ningún archivo. Formatos permitidos: %s.',
                implode(', ', DashboardClassifications::ALLOWED_EXTENSIONS)
            ));

            return $this->redirectToRoute('legacy_classification_new');
        }

        $request->setAttachments($attachments);
        $this->entityManager->flush();

        $this->mailer->notify($request);

        $this->addFlash('success', 'Solicitud enviada, puede cerrar esta página.');

        return $this->redirectToRoute('legacy_classification_new');
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
