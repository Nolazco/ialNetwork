<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if the user is already authenticated, send them to their dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        // last authentication error (if any) and last email entered by the user
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): void
    {
        // This method is intentionally left blank: it is intercepted by the
        // "logout" key configured in the firewall (config/packages/security.yaml).
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
