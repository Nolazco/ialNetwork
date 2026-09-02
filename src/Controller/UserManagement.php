<?php

namespace App\Controller;

use App\Entity\User;
use App\Notification\NewUserMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class UserManagement extends AbstractController{

	#[Route('/user/new', name: 'user_new', methods: ['POST'])]
	public function create(Request $r, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, NewUserMailer $newUserMailer): Response{
		if (!$this->isCsrfTokenValid('register', $r->request->get('_token'))) {
			$this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');
			return $this->redirectToRoute("register");
		}

		$userRepo = $entityManager->getRepository(User::class);

		$email = $r->request->get('email');

		if ($userRepo->findOneBy(['email' => $email])) {
			$this->addFlash('error', 'Correo registrado previamente.');
			return $this->redirectToRoute("register");
		}

		$pass1 = $r->request->get('password1');
		$pass2 = $r->request->get('password2');

		if ($pass1 !== $pass2) {
			$this->addFlash('error', 'Las contraseñas no coinciden.');
			return $this->redirectToRoute("register");
		}

		$user = new User();
		$user->setName($r->request->get('name'));
		$user->setLastName($r->request->get('lastName'));
		$user->setEmail($email);
		$user->setStatus('pending');
		// Self-registration always creates a client account pending validation.
		// Staff roles (executive, freight hauler, admin) are assigned by an admin.
		$user->setRoles(['ROLE_CLIENT']);
		$user->setPassword($passwordHasher->hashPassword($user, $pass1));

		$entityManager->persist($user);
		$entityManager->flush();

		$newUserMailer->notify($user);

		$this->addFlash('success', 'Cuenta creada, espere su validacion.');
		return $this->redirectToRoute("register");
	}
}
