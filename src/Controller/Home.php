<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Home extends AbstractController {
	/**
	 * Portada.
	 *
	 * Las dos rutas rinden la misma plantilla; lo unico que cambia es el idioma.
	 * "_locale" es un parametro especial de Symfony: fijarlo en la ruta cambia el
	 * idioma de la peticion, y con eso |trans resuelve contra el catalogo que
	 * toca. Antes eran dos plantillas de 300 lineas practicamente identicas.
	 */
	#[Route(name: 'index', path: '/', defaults: ['_locale' => 'es'])]
	#[Route(name: 'index_en', path: '/en', defaults: ['_locale' => 'en'])]
	function index(): Response {
		return $this->render('index.html.twig');
	}

	#[Route(name: 'register', path: '/register')]
	function register(): Response {
		return $this->render("register.html.twig");
	}
}
