<?php 

namespace App\Controller;

use App\Entity\Provider;
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
class DashboardProviders extends AbstractController {
	#[Route('/dashboard/proveedores')]
	public function providers(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();

		$providerRepo = $entityManager->getRepository(Provider::class);
		$providers = $providerRepo->findAll();

		return $this->render("/dashboard/providers.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
			'providers' => $providers
		]);
	}

	#[Route('/dashboard/proveedores/nuevo')]
	public function createProvider(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();

		return $this->render("/dashboard/newprovider.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true'
		]);
	}

		#[Route('/dashboard/proveedores/new', methods: ['POST'])]
	  public function newProvider(Request $r, EntityManagerInterface $entityManager): Response {
	    if (!$this->isCsrfTokenValid('create_provider', $r->request->get('_token'))) {
	      $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');
	      return $this->redirect('/dashboard/proveedores/nuevo');
	    }

	    $provider = new Provider();
	    $provider->setName($r->request->get('name'));
	    $provider->setTaxId($r->request->get('taxId'));
	    $provider->setAddress($r->request->get('address'));

	    $entityManager->persist($provider);

	    $entityManager->flush();

	    $this->addFlash('success', 'Proveedor registrado correctamente.');
	    return $this->redirect('/dashboard/proveedores');
	  }

	  #[Route('/dashboard/proveedores/{id}/editar', methods: ['POST'])]
	  public function editProvider(int $id, Request $r, EntityManagerInterface $entityManager ): JsonResponse {
	    $provider = $entityManager->getRepository(Provider::class)->find($id);

	    if (!$provider) {
	      return new JsonResponse(['success' => false, 'message' => 'Proveedor no encontrado.'], 404);
	    }

	    $data = json_decode($r->getContent(), true);

	    $name = $data['name'] ?? null;
	    $address = $data['address'] ?? null;
	    $taxId = $data['taxId'] ?? null;

	    if (!$name || !$address || !$taxId) {
	      return new JsonResponse(['success' => false, 'message' => 'Faltan campos obligatorios.'], 400);
	    }

	    $provider->setName($name);
	    $provider->setAddress($address);
	    $provider->setTaxId($taxId);

	    $entityManager->persist($provider);
	    $entityManager->flush();

	    return new JsonResponse(['success' => true]);
	  }
}