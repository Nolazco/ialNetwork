<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardUsers extends AbstractController {
	use AjaxCsrfTrait;

	#[Route(name: 'dashboard', path: '/dashboard')]
	public function dashboard(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();

		// ROLE_ADMIN inherits ROLE_EXECUTIVE (see role_hierarchy in security.yaml)
		if ($this->isGranted('ROLE_EXECUTIVE')) {
			$pendingUsers = $entityManager->getRepository(User::class)->count(['status' => 'pending']);

			return $this->render("/dashboard/admin.html.twig", [
				'name' => $user->getName(),
				'role' => $user->getRoles()[0],
				'loged' => 'true',
				'pending' => $pendingUsers
			]);
		}

		// El transportista no tiene nada que hacer en la vista de cliente: su
		// trabajo son los despachos que le asignaron.
		if ($this->isGranted('ROLE_FH')) {
			return $this->redirectToRoute('deliveries');
		}

		return $this->render("/dashboard/client.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
		]);
	}

	#[Route(name: 'users', path: '/dashboard/usuarios')]
	#[IsGranted('ROLE_ADMIN')]
	public function users(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();
		$users = $entityManager->getRepository(User::class)->findAll();

		return $this->render("/dashboard/users.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
			'users' => $users
		]);
	}

	#[Route(name: 'verifyUser', path: '/dashboard/usuarios/{id}/verificar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
	public function verifyUser(int $id, EntityManagerInterface $entityManager, Request $r): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    if (!$r->isXmlHttpRequest()) {
      return new JsonResponse(['success' => false, 'message' => 'Petición no válida'], 400);
    }

    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
			return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
    }

    $user->setStatus('active');
    $entityManager->flush();

    return new JsonResponse(['success' => true, 'message' => 'Usuario verificado con éxito']);
	}

	#[Route(name: 'denyUser', path: '/dashboard/usuarios/{id}/rechazar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
	public function denyUser(int $id, EntityManagerInterface $entityManager, Request $r): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    if (!$r->isXmlHttpRequest()) {
      return new JsonResponse(['success' => false, 'message' => 'Petición no válida'], 400);
    }

    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
      return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
    }

    $user->setStatus('inactive');
    $entityManager->flush();

    return new JsonResponse(['success' => true, 'message' => 'Usuario rechazado con éxito']);
	}

	#[Route(name: 'disableUser', path: '/dashboard/usuarios/{id}/deshabilitar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
	public function disableUser(int $id, Request $r, EntityManagerInterface $entityManager): Response {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

		$user = $entityManager->getRepository(User::class)->find($id);

		if (!$user) {
			return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
		}

		$user->setStatus('pending');
		$entityManager->flush();

		return new JsonResponse(['success' => true, 'message' => 'Usuario deshabilitado correctamente']);
	}

	#[Route(name: 'enableUser', path: '/dashboard/usuarios/{id}/habilitar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
	public function enableUser(int $id, Request $r, EntityManagerInterface $entityManager): Response {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

		$user = $entityManager->getRepository(User::class)->find($id);

		if (!$user) {
			return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
		}

		$user->setStatus('active');
		$entityManager->flush();

		return new JsonResponse(['success' => true, 'message' => 'Usuario reactivado correctamente', 'user_id' => $user->getId()
    ]);
	}

	#[Route(name: 'editUser', path: '/dashboard/usuarios/{id}/editar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
  public function editUser(int $id, Request $r, EntityManagerInterface $entityManager ): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
      return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }

    $data = json_decode($r->getContent(), true);

    $name = $data['name'] ?? null;
    $lastName = $data['lastName'] ?? null;
    $email = $data['email'] ?? null;

    if (!$name || !$lastName || !$email) {
      return new JsonResponse(['success' => false, 'message' => 'Faltan campos obligatorios.'], 400);
    }

    // Validar formato del email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return new JsonResponse(['success' => false, 'message' => 'El email no es válido.'], 400);
    }

    $user->setName($name);
    $user->setLastName($lastName);
    $user->setEmail($email);

    $entityManager->persist($user);
    $entityManager->flush();

    return new JsonResponse(['success' => true]);
  }

  #[Route(name: 'changeRole', path: '/dashboard/usuarios/{id}/cambiarRol', methods: ['POST'])]
  #[IsGranted('ROLE_ADMIN')]
  public function changeRole(int $id, Request $r, EntityManagerInterface $entityManager): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
      return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }

    $data = json_decode($r->getContent(), true);
    $newRole = $data['newRole'] ?? null;

    $validRoles = ['ROLE_ADMIN', 'ROLE_EXECUTIVE', 'ROLE_CLIENT', 'ROLE_FH'];

    if (!$newRole || !in_array($newRole, $validRoles)) {
      return new JsonResponse(['success' => false, 'message' => 'Rol inválido.'], 400);
    }

    $user->setRoles([$newRole]);
    $entityManager->persist($user);
    $entityManager->flush();

    return new JsonResponse(['success' => true]);
  }
}
