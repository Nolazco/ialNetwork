<?php 

namespace App\Controller;

use App\Entity\ContainerYard;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalogo interno de la agencia: ni clientes ni transportistas tienen nada que
 * hacer aqui, y menos dando de alta o editando registros.
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardYards extends AbstractController {
	#[Route('/dashboard/recintos')]
	public function yards(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();

		$yardRepo = $entityManager->getRepository(ContainerYard::class);
		$yards = $yardRepo->findAll();

		return $this->render("/dashboard/yards.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
			'yards' => $yards
		]);
	}

	#[Route('/dashboard/recintos/nuevo')]
	public function createYard(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();

		return $this->render("/dashboard/newyard.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true'
		]);
	}

		#[Route('/dashboard/recintos/new', methods: ['POST'])]
	  public function newYard(Request $r, EntityManagerInterface $entityManager): Response {
	    if (!$this->isCsrfTokenValid('create_yard', $r->request->get('_token'))) {
	      $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');
	      return $this->redirect('/dashboard/recintos/nuevo');
	    }

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