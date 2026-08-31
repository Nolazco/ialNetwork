<?php

namespace App\Controller;

use App\Entity\ClassificationRequest;
use App\Entity\Company;
use App\Entity\User;
use App\Notification\ClassificationMailer;
use App\Security\CompanyAccess;
use App\Service\UploadPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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
 * correo — la app no hace seguimiento de la respuesta, solo deja constancia
 * de que la solicitud se mandó.
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

    #[Route('/dashboard/clasificaciones', name: 'classifications')]
    public function classifications(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGranted('ROLE_EXECUTIVE')) {
            $requests = $this->entityManager->getRepository(ClassificationRequest::class)
                ->findBy([], ['createdAt' => 'DESC']);
        } else {
            $companies = $this->allowedCompanies($user);
            $requests = $companies === [] ? [] : $this->entityManager->getRepository(ClassificationRequest::class)
                ->findBy(['company' => $companies], ['createdAt' => 'DESC']);
        }

        return $this->render('/dashboard/classifications.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'requests' => $requests,
        ]);
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

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
