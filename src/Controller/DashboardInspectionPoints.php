<?php

namespace App\Controller;

use App\Entity\InspectionPoint;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalogo interno de puntos de inspeccion (XCF, Acoman...) donde puede
 * ocurrir un traspaso local. Igual que Recintos/Proveedores: ni clientes ni
 * transportistas tienen nada que hacer aqui.
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardInspectionPoints extends AbstractController
{
    use AjaxCsrfTrait;

    #[Route('/dashboard/puntos-inspeccion')]
    public function inspectionPoints(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $points = $entityManager->getRepository(InspectionPoint::class)->findAll();

        return $this->render('/dashboard/inspectionPoints.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'points' => $points,
        ]);
    }

    #[Route('/dashboard/puntos-inspeccion/nuevo')]
    public function createInspectionPoint(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/newinspectionpoint.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
        ]);
    }

    #[Route('/dashboard/puntos-inspeccion/new', methods: ['POST'])]
    public function newInspectionPoint(Request $r, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('create_inspection_point', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirect('/dashboard/puntos-inspeccion/nuevo');
        }

        $name = trim((string) $r->request->get('name'));

        if ($name === '') {
            $this->addFlash('error', 'El nombre es obligatorio.');

            return $this->redirect('/dashboard/puntos-inspeccion/nuevo');
        }

        $point = new InspectionPoint();
        $point->setName($name);

        $entityManager->persist($point);
        $entityManager->flush();

        $this->addFlash('success', 'Punto de inspección registrado correctamente.');

        return $this->redirect('/dashboard/puntos-inspeccion');
    }

    #[Route('/dashboard/puntos-inspeccion/{id}/editar', methods: ['POST'])]
    public function editInspectionPoint(int $id, Request $r, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
            return $csrf;
        }

        $point = $entityManager->getRepository(InspectionPoint::class)->find($id);

        if (!$point) {
            return new JsonResponse(['success' => false, 'message' => 'Punto no encontrado.'], 404);
        }

        $data = json_decode($r->getContent(), true);
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return new JsonResponse(['success' => false, 'message' => 'El nombre es obligatorio.'], 400);
        }

        $point->setName($name);

        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
