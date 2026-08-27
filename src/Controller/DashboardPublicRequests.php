<?php

namespace App\Controller;

use App\Entity\LegacyClassificationRequest;
use App\Entity\LegacyPrevioReport;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
}
