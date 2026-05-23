<?php 

namespace App\Controller;

use App\Entity\ContainerYard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardYards extends AbstractController {
	#[Route('/dashboard/recintos')]
	public function yards(Request $r, EntityManagerInterface $entityManager): Response {
		$session = $r->getSession();

		$yardRepo = $entityManager->getRepository(ContainerYard::class);
		$yards = $yardRepo->findAll();

		return $this->render("/dashboard/yards.html.twig", [
			'name' => $session->get('name'),
			'role' => $session->get('role'),
			'loged' => 'true',
			'yards' => $yards
		]);
	}

	#[Route('/dashboard/recintos/nuevo')]
	public function createYard(Request $r, EntityManagerInterface $entityManager): Response {
		$session = $r->getSession();

		return $this->render("/dashboard/newyard.html.twig", [
			'name' => $session->get('name'),
			'role' => $session->get('role'),
			'loged' => 'true'
		]);
	}

		#[Route('/dashboard/recintos/new', methods: ['POST'])]
	  public function newYard(Request $r, EntityManagerInterface $entityManager): Response {
    	$session = $r->getSession();

	    $yard = new ContainerYard();
	    $yard->setName($r->request->get('name'));
	    $yard->setCr($r->request->get('cr'));

	    $entityManager->persist($yard);

	    $entityManager->flush();

	    $this->addFlash('success', 'Recinto registrado correctamente.');
	    return $this->redirect('/dashboard/recintos');
	  }

	  #[Route('/dashboard/recintos/{id}/editar', methods: ['POST'])]
	  public function editYard(int $id, Request $r, EntityManagerInterface $entityManager ): JsonResponse {
	    $yard = $entityManager->getRepository(ContainerYard::class)->find($id);

	    if (!$yard) {
	      return new JsonResponse(['success' => false, 'message' => 'Proveedor no encontrado.'], 404);
	    }

	    $data = json_decode($r->getContent(), true);

	    $name = $data['name'] ?? null;
	    $cr = $data['cr'] ?? null;

	    if (!$name || !$cr) {
	      return new JsonResponse(['success' => false, 'message' => 'Faltan campos obligatorios.'], 400);
	    }

	    $yard->setName($name);
	    $yard->setCr($cr);

	    $entityManager->persist($yard);
	    $entityManager->flush();

	    return new JsonResponse(['success' => true]);
	  }
}