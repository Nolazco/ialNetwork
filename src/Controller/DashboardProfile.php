<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Autoservicio de cada usuario sobre sus propios datos — hoy solo el
 * WhatsApp, que dejo de ser un numero compartido por toda la empresa (ver
 * Company::$whatsapp) para ser propio de cada cliente (ver User::$whatsapp),
 * a donde le llegan los avisos de los expedientes que el mismo dio de alta
 * (ver RecipientResolver::clientWhatsapp()). Abierto a cualquier rol: no hay
 * nada aqui que solo le sirva a un cliente, aunque hoy en la practica sea
 * quien mas lo usa.
 */
class DashboardProfile extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/dashboard/mi-perfil', name: 'my_profile', methods: ['GET'])]
    public function show(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/myProfile.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'profileUser' => $user,
        ]);
    }

    #[Route('/dashboard/mi-perfil', name: 'my_profile_update', methods: ['POST'])]
    public function update(Request $r): Response
    {
        if (!$this->isCsrfTokenValid('my_profile_update', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('my_profile');
        }

        /** @var User $user */
        $user = $this->getUser();

        $whatsapp = trim((string) $r->request->get('whatsapp'));
        $user->setWhatsapp($whatsapp !== '' ? $whatsapp : null);

        $this->entityManager->flush();

        $this->addFlash('success', 'Perfil actualizado.');

        return $this->redirectToRoute('my_profile');
    }
}
