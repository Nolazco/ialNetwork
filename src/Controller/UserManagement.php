<?php 

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserManagement extends AbstractController{

	#[Route('/user/new', methods: [ 'GET', 'POST' ])]
	public function create(Request $r, EntityManagerInterface $entityManager): Response{
		$usersRepo = $entityManager->getRepository(User::class);
		$users = $usersRepo->findAll();
		//dd($users);
		for ($i = 0; $i < $usersRepo->count(); $i++){
			if($r->request->get('email') == $users[$i]->getEmail()){
				$this->addFlash('error', 'Correo registrado previamente.');
				return $this->redirectToRoute("register");
				die();
			}
		}

		$user = new User();

		$roles = ['','ROLE_EXECUTIVE','ROLE_CLIENT','ROLE_FH'];

		$user->setName($r->request->get('name'));
		$user->setLastName($r->request->get('lastName'));
		$user->setEmail($r->request->get('email'));
		$user->setStatus('pending');
		$rol = $r->request->get('rol');
		$user->setRoles([$roles[$rol]]);
		$pass1 = $r->request->get('password1');
		$pass2 = $r->request->get('password2');

		if($pass1 != $pass2){
			$this->addFlash('error', 'Las contraseñas no coinciden.');
			return $this->redirectToRoute("register");
		}else{
			$password = password_hash($pass1, PASSWORD_DEFAULT);
			$user->setPassword($password);
			$entityManager->persist($user);
			$entityManager->flush();
			$this->addFlash('success', 'Cuenta creada, espere su validacion.');
			return $this->redirectToRoute("register");
		}
	}

	#[Route('/user/login', methods: [ 'GET', 'POST' ])]
	public function login(Request $r, EntityManagerInterface $entityManager): Response{
		$usersRepo = $entityManager->getRepository(User::class);
		$users = $usersRepo->findOneBy(['email' => $r->request->get('email')]);
		//dd($users->getRoles()[0]);
		if(!$users || !password_verify($r->request->get('password'), $users->getPassword())){
			$this->addFlash('error', 'Credenciales incorrectas.');
			return $this->redirectToRoute("login");
		}else{
			if($users->getStatus() == 'pending'){
				$this->addFlash('warning', 'Cuenta pendiente de validacion.');
				return $this->redirectToRoute("login");
			}elseif($users->getStatus() == 'inactive'){
				$this->addFlash('error', 'Cuenta inactiva o rechazada.');
				return $this->redirectToRoute("login");
			}else{
				$session = $r->getSession();
				$session->set('name', $users->getName());
				$session->set('role', $users->getRoles()[0]);
				$session->set('loged', 'true');
				return $this->redirect("/");
			}
		}
	}
}