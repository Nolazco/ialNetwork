<?php 

namespace App\Controller;

use App\Entity\Provider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardProviders extends AbstractController {
	#[Route('/dashboard/proveedores')]
	public function providers(Request $r, EntityManagerInterface $entityManager): Response {
		$session = $r->getSession();

		$providerRepo = $entityManager->getRepository(Provider::class);
		$providers = $providerRepo->findAll();

		return $this->render("/dashboard/providers.html.twig", [
			'name' => $session->get('name'),
			'role' => $session->get('role'),
			'loged' => 'true',
			'providers' => $providers
		]);
	}

	#[Route('/dashboard/proveedores/nuevo')]
	public function createProvider(Request $r, EntityManagerInterface $entityManager): Response {
		$session = $r->getSession();

		return $this->render("/dashboard/newprovider.html.twig", [
			'name' => $session->get('name'),
			'role' => $session->get('role'),
			'loged' => 'true'
		]);
	}

		#[Route('/dashboard/proveedores/new', methods: ['POST'])]
	  public function newProvider(Request $r, EntityManagerInterface $entityManager): Response {
    	$session = $r->getSession();

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