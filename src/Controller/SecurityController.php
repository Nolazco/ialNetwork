<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils, TranslatorInterface $translator): Response
    {
        // if the user is already authenticated, send them to their dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        // The authentication error is surfaced as a flash so it travels through
        // the same channel as every other message (see templates/_flashes.html.twig)
        // instead of needing its own inline script in the template.
        if ($error = $authenticationUtils->getLastAuthenticationError()) {
            $this->addFlash('error', $translator->trans($error->getMessageKey(), $error->getMessageData(), 'security'));
        }

        return $this->render('login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
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
