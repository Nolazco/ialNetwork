<?php 

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Dashboard extends AbstractController {
	#[Route(name: 'dashboard', path: '/dashboard')]
	public function dashboard(Request $r, EntityManagerInterface $entityManager): Response {
		$session = $r->getSession();
		$userRepo = $entityManager->getRepository(User::class);
		$pendingUsers = $userRepo->count(['status' => 'pending']);
		//dd($pendingUsers);

		if($session->get('loged') == 'true'){
			return $this->render("/dashboard/admin.html.twig", [
				'name' => $session->get('name'),
				'role' => $session->get('role'),
				'loged' => 'true',
				'pending' => $pendingUsers
			]);
		}else{
			return $this->redirectToRoute("login");
		}
	}

	#[Route(name: 'users', path: '/dashboard/usuarios')]
	public function users(Request $r, EntityManagerInterface $entityManager): Response {
		$session = $r->getSession();
		$userRepo = $entityManager->getRepository(User::class);
		$users = $userRepo->findAll();
		//dd($users);

		if($session->get('loged') == 'true' && $session->get('role') == 'ROLE_ADMIN'){
			return $this->render("/dashboard/users.html.twig", [
				'name' => $session->get('name'),
				'role' => $session->get('role'),
				'loged' => 'true',
				'users' => $users
			]);
		}else{
			return $this->redirectToRoute("login");
		}
	}

	#[Route(name: 'verifyUser', path: '/dashboard/usuarios/{id}/verificar', methods: ['POST'])]
	public function verifyUser(int $id, EntityManagerInterface $entityManager, Request $r): JsonResponse {
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
	public function denyUser(int $id, EntityManagerInterface $entityManager, Request $r): JsonResponse {
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

	#[Route(name: 'disableUser', path: '/dashboard/usuarios/{id}/deshabilitar')]
	public function disableUser(int $id, Request $r, EntityManagerInterface $entityManager): Response {
		$user = $entityManager->getRepository(User::class)->find($id);

		if (!$user) {
			return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
		}

		$user->setStatus('pending');
		$entityManager->flush();

		return new JsonResponse(['success' => true, 'message' => 'Usuario deshabilitado correctamente']);
	}

	#[Route(name: 'enableUser', path: '/dashboard/usuarios/{id}/habilitar')]
	public function enableUser(int $id, Request $r, EntityManagerInterface $entityManager): Response {
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
  public function editUser(int $id, Request $r, EntityManagerInterface $entityManager ): JsonResponse {
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
  public function changeRole(int $id, Request $r, EntityManagerInterface $entityManager): JsonResponse {
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

  #[Route(name: 'companies', path: '/dashboard/empresas')]
  public function companies(Request $r, EntityManagerInterface $entityManager): Response {
  	$session = $r->getSession();
  	$companyRepo = $entityManager->getRepository(Company::class);

  	if($session->get('role' == 'ROLE_ADMIN'))
	  	$companies = $companyRepo->findAll();
	  elseif ($session->get('role' == 'ROLE_CLIENT')) {
	  	$companies = $companyRepo->findAll();
	  }
  	//dd($users);

  	if($session->get('loged') == 'true' && $session->get('role') == 'ROLE_ADMIN'){
  		return $this->render("/dashboard/users.html.twig", [
  			'name' => $session->get('name'),
  			'role' => $session->get('role'),
  			'loged' => 'true',
  			'users' => $users
  		]);
  	}else{
  		return $this->redirectToRoute("login");
  	}
  }
}