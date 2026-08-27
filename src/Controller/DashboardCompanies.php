<?php

namespace App\Controller;

use App\Entity\Associated;
use App\Entity\Company;
use App\Entity\CompanyDocument;
use App\Entity\User;
use App\Security\CompanyAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class DashboardCompanies extends AbstractController{
  use AjaxCsrfTrait;

	public function __construct(private readonly CompanyAccess $companyAccess) {
	}

	#[Route(name: 'companies', path: '/dashboard/empresas')]
	public function companies(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();

		$companyRepo = $entityManager->getRepository(Company::class);

	  // Staff (admin/executive) see every company; everyone else sees only their own.
	  if ($this->isGranted('ROLE_EXECUTIVE')) {
		  $companies = $companyRepo->findAll();
    } else {
			$companies = $companyRepo->findAssociatedCompanies($user);
		}

		return $this->render("/dashboard/companies.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
			'companies' => $companies
		]);
	}

	#[Route(name: 'createCompany', path: '/dashboard/empresas/nueva')]
	public function createCompany(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();

		return $this->render("/dashboard/newcompany.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true'
		]);
	}

	#[Route('/dashboard/empresas/new', methods: ['POST'])]
  public function newCompany(Request $r, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response {
    if (!$this->isCsrfTokenValid('create_company', $r->request->get('_token'))) {
      $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');
      return $this->redirect('/dashboard/empresas/nueva');
    }

    /** @var User $user */
    $user = $this->getUser();

    // 1. Crear empresa
    $company = new Company();
    $company->setName($r->request->get('name'));
    $company->setRfc($r->request->get('rfc'));
    $company->setAddress($r->request->get('address'));
    $company->setClassificationContactEmail($this->nullableTrim($r->request->get('classificationContactEmail')));

    $entityManager->persist($company);

    // 2. Asociar con usuario actual
    //$usuario = $security->getUser();

    // La empresa la esta dando de alta el propio cliente, asi que su afiliacion
    // no necesita autorizacion: no hay nada de nadie mas que proteger.
    $asociacion = new Associated();
    $asociacion->setIdClient($user);
    $asociacion->setIdCompany($company);
    $asociacion->setStatus(Associated::APPROVED);
    $entityManager->persist($asociacion);

    // 3. Manejar documentos
    $files = $r->files->all();
    $types = $r->request->all('documentTypes');

    $route = 'uploads/empresas/' . $company->getRfc(); // Ruta de carpeta
    
    if (!is_dir($route)) {
      mkdir($route, 0777, true);
    }

    foreach ($files as $index => $fileGroup) {
      if (!is_array($fileGroup)) continue;

      foreach ($fileGroup as $i => $file) {
        if ($file && $file->isValid()) {
          $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
          $safeFilename = $slugger->slug($originalFilename);
          $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

          try {
            $file->move($route, $newFilename);
          } catch (FileException $e) {
            continue;
          }

          $documento = new CompanyDocument();
          $documento->setIdCompany($company);
          $documento->setType($types[$i] ?? 'Desconocido');
          $documento->setRoute($route . '/' . $newFilename);

          $entityManager->persist($documento);
        }
      }
    }

    // Guardar todo
    $entityManager->flush();

    $this->addFlash('success', 'Empresa registrada correctamente.');
    return $this->redirect('/dashboard/empresas');
  }

  #[Route('/dashboard/empresas/disponibles', methods: ['GET'])]
  public function availableCompanies(EntityManagerInterface $entityManager): JsonResponse {
    /** @var User $user */
    $user = $this->getUser();

    // 1. Obtener IDs de empresas ya asociadas
    $associatedCompanyIds = $entityManager->createQueryBuilder()
      ->select('IDENTITY(a.idCompany)')
      ->from(Associated::class, 'a')
      ->where('a.idClient = :user')
      ->setParameter('user', $user)
      ->getQuery()
      ->getResult();

    // Aplanar el array de IDs (puede venir como array de arrays)
    $ids = array_map(fn($row) => $row[1] ?? array_values($row)[0], $associatedCompanyIds);

    // 2. Obtener empresas NO asociadas
    $notAssociated = $entityManager->getRepository(Company::class)->createQueryBuilder('c');

    if (!empty($ids)) {
      $notAssociated->where($notAssociated->expr()->notIn('c.id', ':ids'))->setParameter('ids', $ids);
    }

    $companies = $notAssociated->getQuery()->getResult();

    $data = array_map(function ($company) {
      return [
        'id' => $company->getId(),
        'name' => $company->getName(),
        'rfc' => $company->getRfc()
      ];
    }, $companies);

    return new JsonResponse(['empresas' => $data]);
  }

  #[Route('/dashboard/empresas/afiliar/{id}', methods: ['POST'])]
  public function associateCompany(int $id, Request $r, EntityManagerInterface $entityManager): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    /** @var User $usuario */
    $usuario = $this->getUser();
    $companyRepo = $entityManager->getRepository(Company::class);
    $company = $companyRepo->find($id);

    foreach ($company->getAssociateds() as $asoc) {
      if ($asoc->getIdClient() === $usuario) {
        return new JsonResponse([
          'status' => 'duplicada',
          'message' => $asoc->isPending()
            ? 'Ya tienes una solicitud pendiente para esa empresa.'
            : 'Ya estás afiliado a esa empresa.',
        ]);
      }
    }

    // Nace pendiente: afiliarse da acceso a los expedientes y a las cuentas de
    // gastos de la empresa, asi que lo autoriza la agencia, no el solicitante.
    $asociacion = new Associated();
    $asociacion->setIdClient($usuario);
    $asociacion->setIdCompany($company);
    $asociacion->setStatus(Associated::PENDING);

    $entityManager->persist($asociacion);
    $entityManager->flush();

    return new JsonResponse([
      'status' => 'pendiente',
      'message' => 'Tu solicitud quedó pendiente de autorización por la agencia.',
    ]);
  }

  #[Route('/dashboard/empresas/{id}/editar', methods: ['POST'])]
  public function editCompany(int $id, Request $r, EntityManagerInterface $entityManager ): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    $company = $entityManager->getRepository(Company::class)->find($id);

    if (!$company) {
      return new JsonResponse(['success' => false, 'message' => 'Empresa no encontrada.'], 404);
    }

    if (!$this->companyAccess->canAccess($company)) {
      return new JsonResponse(['error' => 'Esa empresa no está entre las tuyas.'], 403);
    }

    $data = json_decode($r->getContent(), true);

    $name = $data['name'] ?? null;
    $address = $data['address'] ?? null;
    $rfc = $data['rfc'] ?? null;

    if (!$name || !$address || !$rfc) {
      return new JsonResponse(['success' => false, 'message' => 'Faltan campos obligatorios.'], 400);
    }

    $company->setName($name);
    $company->setAddress($address);
    $company->setRfc($rfc);
    $company->setClassificationContactEmail($this->nullableTrim($data['classificationContactEmail'] ?? null));

    $entityManager->persist($company);
    $entityManager->flush();

    return new JsonResponse(['success' => true]);
  }

  private function nullableTrim(mixed $value): ?string
  {
    $value = trim((string) $value);

    return $value === '' ? null : $value;
  }

  #[Route('/dashboard/empresas/{id}/documentos', methods: ['GET'])]
  public function getDocuments(int $id, EntityManagerInterface $entityManager): JsonResponse {
    $company = $entityManager->getRepository(Company::class)->find($id);

    if (!$company) {
      return new JsonResponse(['error' => 'Empresa no encontrada'], 404);
    }

    if (!$this->companyAccess->canAccess($company)) {
      return new JsonResponse(['error' => 'Esa empresa no está entre las tuyas.'], 403);
    }

    $docs = [];

    foreach ($company->getCompanyDocuments() as $doc) {
      $docs[] = [
        'id' => $doc->getId(),
        'type' => $doc->getType(),
        'route' => $doc->getRoute(),
      ];
    }

    return new JsonResponse(['documents' => $docs]);
  }

  #[Route('/dashboard/empresas/{id}/documentos/nuevo', methods: ['POST'])]
  public function addDocument(Request $r, int $id, EntityManagerInterface $entityManager, SluggerInterface $slugger): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    $company = $entityManager->getRepository(Company::class)->find($id);

    if (!$company) {
      return new JsonResponse(['success' => false, 'message' => 'Empresa no encontrada.'], 404);
    }

    if (!$this->companyAccess->canAccess($company)) {
      return new JsonResponse(['error' => 'Esa empresa no está entre las tuyas.'], 403);
    }

    $file = $r->files->get('document');
    $type = $r->request->get('type');

    if (!$file || !$type) {
      return new JsonResponse(['error' => 'Faltan datos.'], 400);
    }

    $route = 'uploads/empresas/' . $company->getRfc(); // Ruta de carpeta
    
    if (!is_dir($route)) {
      mkdir($route, 0777, true);
    }

    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $safeFilename = $slugger->slug($originalFilename);
    $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

    $file->move($route, $newFilename);

    $document = new CompanyDocument();
    $document->setType($type);
    $document->setRoute($route . '/' . $newFilename);
    $document->setIdCompany($company);

    $entityManager->persist($document);
    $entityManager->flush();

    return new JsonResponse(['success' => true]);
  }

  #[Route('/dashboard/empresas/documentos/{id}/eliminar', methods: ['DELETE'])]
  public function deleteDocument(int $id, Request $r, EntityManagerInterface $entityManager): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

      $document = $entityManager->getRepository(CompanyDocument::class)->find($id);

      if (!$document) {
        return new JsonResponse(['error' => 'Documento no encontrado.'], 404);
      }

      if (!$this->companyAccess->canAccess($document->getIdCompany())) {
        return new JsonResponse(['error' => 'Ese documento no es de una de tus empresas.'], 403);
      }

      $filePath = $document->getRoute();
      if (file_exists($filePath)) {
        unlink($filePath);
      }

      $entityManager->remove($document);
      $entityManager->flush();

      return new JsonResponse(['success' => true]);
  }

  #[Route('/dashboard/empresas/documentos/{id}/editar', methods: ['POST'])]
  public function editDocument(Request $r, int $id, EntityManagerInterface $entityManager, SluggerInterface $slugger): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    $document = $entityManager->getRepository(CompanyDocument::class)->find($id);

    if (!$document) {
      return new JsonResponse(['error' => 'Documento no encontrado.'], 404);
    }

    if (!$this->companyAccess->canAccess($document->getIdCompany())) {
      return new JsonResponse(['error' => 'Ese documento no es de una de tus empresas.'], 403);
    }

    $newType = $r->request->get('type');
    $newFile = $r->files->get('document');

    if ($newType) {
      $document->setType($newType);
    }

    if ($newFile) {
      // Elimina archivo anterior
      $oldPath = $document->getRoute();
      if ($oldPath && file_exists($oldPath)) {
        unlink($oldPath);
      }

      // Guarda el nuevo
      $companyRfc = $document->getIdCompany()->getRfc();
      $folder = 'uploads/empresas/' . $companyRfc;

      if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
      }

      $originalFilename = pathinfo($newFile->getClientOriginalName(), PATHINFO_FILENAME);
      $safeFilename = $slugger->slug($originalFilename);
      $newFilename = $safeFilename . '-' . uniqid() . '.' . $newFile->guessExtension();

      $newFile->move($folder, $newFilename);
      $document->setRoute($folder . '/' . $newFilename);
    }

    $entityManager->flush();

    return new JsonResponse(['success' => true]);
  }
}