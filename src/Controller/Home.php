<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Home extends AbstractController {
	#[Route(name: 'index', path: "/")]
	function index(): Response {
		return $this->render("indexEs.html.twig");
	}

	#[Route(name: 'indexEn', path: "/en")]
	function indexEn(): Response {
		return $this->render("indexEn.html.twig");
	}

	#[Route(name: 'register', path: '/register')]
	function register(): Response {
		return $this->render("register.html.twig");
	}
}
