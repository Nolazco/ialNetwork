<?php

namespace App\Controller;

use App\Entity\LegacyClassificationRequest;
use App\Entity\LegacyPrevioReport;
use App\Entity\User;
use App\Service\UploadPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lo que entra por los puentes públicos sin login (/legacy/clasificacion,
 * /legacy/reportes): aparte de los listados reales porque aquí la empresa es
 * texto libre, no una empresa registrada, y mezclarlos les restaría
 * confiabilidad a esos listados.
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardPublicRequests extends AbstractController
{
    public function __construct(
        private readonly UploadPath $uploadPath,
    ) {
    }

    #[Route('/dashboard/solicitudes-publicas', name: 'public_requests')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/publicRequests.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'classifications' => $entityManager->getRepository(LegacyClassificationRequest::class)
                ->findBy([], ['createdAt' => 'DESC']),
            'previos' => $entityManager->getRepository(LegacyPrevioReport::class)
                ->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/dashboard/solicitudes-publicas/clasificaciones/{id}/adjuntos/{index}', name: 'legacy_classification_attachment_download', methods: ['GET'])]
    public function downloadClassificationAttachment(int $id, int $index, EntityManagerInterface $entityManager): BinaryFileResponse
    {
        $request = $entityManager->getRepository(LegacyClassificationRequest::class)->find($id);

        if (!$request) {
            throw $this->createNotFoundException();
        }

        $attachment = $request->getAttachments()[$index] ?? null;

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

    #[Route('/dashboard/solicitudes-publicas/previos/{id}/pdf', name: 'legacy_previo_pdf_download', methods: ['GET'])]
    public function downloadPrevioPdf(int $id, EntityManagerInterface $entityManager): BinaryFileResponse
    {
        $report = $entityManager->getRepository(LegacyPrevioReport::class)->find($id);

        if (!$report || !$report->getPdfRoute()) {
            throw $this->createNotFoundException();
        }

        $path = $this->uploadPath->resolve($report->getPdfRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

        return $response;
    }
}
