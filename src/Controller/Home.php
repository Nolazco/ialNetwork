<?php 

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class Home extends AbstractController {
	#[Route(name: 'index', path: "/")]
	function index(Request $r){
		$session = $r->getSession();

		if($session->get('loged') == 'true'){
			return $this->render("indexEs.html.twig", ['name' => $session->get('name'), 'role' => $session->get('role'), 'loged' => 'true']);
		}else{
			return $this->render("indexEs.html.twig", ['loged' => 'false']);
		}
	}

	#[Route(name: 'indexEn', path: "/en")]
	function indexEn(Request $r){
		$session = $r->getSession();

		if($session->get('loged') == 'true'){
			return $this->render("indexEn.html.twig", ['name' => $session->get('name'), 'role' => $session->get('role'), 'loged' => 'true']);
		}else{
			return $this->render("indexEn.html.twig", ['loged' => 'false']);
		}
	}

	#[Route(name: 'register', path: '/register')]
	function register(){
		return $this->render("register.html.twig");
	}

	#[Route(name: 'login', path: '/login')]
	function login(){
		return $this->render("login.html.twig");
	}

	#[Route(name: 'logout', path: '/logout')]
	function logout(Request $r){
		$session = $r->getSession()->set('loged', 'false');

		return $this->redirect('/');
	}
}