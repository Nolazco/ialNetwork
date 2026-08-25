<?php 

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Container;
use App\Entity\ContainerYard;
use App\Entity\ImportDocument;
use App\Entity\ImportRequest;
use App\Entity\Provider;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class DashboardImports extends AbstractController {
	#[Route('/dashboard/pedimentos/', methods: ['GET'])]
  public function getImportsGlobal(EntityManagerInterface $entityManager): Response {
  	/** @var User $user */
  	$user = $this->getUser();
    $imports = $entityManager->getRepository(ImportRequest::class)->findAll();

    return $this->render('/dashboard/imports.html.twig', [
    	'name' => $user->getName(),
    	'role' => $user->getRoles()[0],
    	'loged' => 'true',
    	'imports' => $imports
    ]);
  }

	#[Route('/dashboard/pedimentos/{rfc}', methods: ['GET'])]
  public function getImports(string $rfc, EntityManagerInterface $entityManager): Response {
  	/** @var User $user */
  	$user = $this->getUser();
    $company = $entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);
    $imports = $entityManager->getRepository(ImportRequest::class)->findBy(['idCompany' => $company]);

    return $this->render('/dashboard/companyImports.html.twig', [
    	'name' => $user->getName(),
    	'role' => $user->getRoles()[0],
    	'loged' => 'true',
    	'company' => $company,
    	'imports' => $imports
    ]);
  }

  #[Route('/dashboard/pedimentos/{rfc}/nuevo')]
  public function createImport(string $rfc, EntityManagerInterface $entityManager): Response {
  	/** @var User $user */
  	$user = $this->getUser();
  	$providers = $entityManager->getRepository(Provider::class)->findAll();
  	$yards = $entityManager->getRepository(ContainerYard::class)->findAll();

  	return $this->render("/dashboard/newimport.html.twig", [
  		'name' => $user->getName(),
  		'role' => $user->getRoles()[0],
  		'rfc' => $rfc,
  		'providers' => $providers,
  		'yards' => $yards,
  		'loged' => 'true'
  	]);
  }

  #[Route('/dashboard/pedimentos/{rfc}/new')]
  public function newImport(string $rfc, Request $r, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response {
  	if (!$this->isCsrfTokenValid('create_import', $r->request->get('_token'))) {
  		$this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');
  		return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  	}

  	$company = $entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);
  	$yard = $entityManager->getRepository(ContainerYard::class)->find($r->request->get('yard'));
  	$provider = $entityManager->getRepository(Provider::class)->find($r->request->get('provider'));

  	if (!$company || !$yard || !$provider) {
  		$this->addFlash('error', 'Empresa, recinto o proveedor no válido.');
  		return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  	}

  	$import = new ImportRequest();
  	$import->setClientReference($r->request->get('ref'));
  	$import->setAgencyReference('Pendiente');
  	$import->setIdCompany($company);
  	$import->setIdProvider($provider);
  	$import->setGoods($r->request->get('goods'));
  	$import->setImportNumber('Pendiente');
  	$import->setEta(new \DateTimeImmutable($r->request->get('eta')));
  	$import->setCr($yard);
  	$import->setType($r->request->get('type'));
  	$import->setStatus('Pendiente');

  	$entityManager->persist($import);

  	if($r->request->get('type') == 'container'){
  		$containers = $r->request->all('containers');
  		$contTypes = $r->request->all('contTypes');

  		foreach ($containers as $index => $number) {
  		  if (empty($number)) continue; // Evitar contenedores vacíos

  		  $container = new Container(); // Asegúrate de importar esta clase
  		  $container->setNum($number);
  		  $container->setType($contTypes[$index] ?? 'Desconocido');
  		  $container->addReference($import); // Relación ManyToOne hacia ImportRequest

  		  $entityManager->persist($container);
  		}
  	}

  	$files = $r->files->all();
  	$types = $r->request->all('documentTypes');

  	$route = 'uploads/empresas/' . $company->getRfc() . $r->request->get('ref'); // Ruta de carpeta
  	
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

  	      $documento = new ImportDocument();
  	      $documento->setReference($import);
  	      $documento->setType($types[$i] ?? 'Desconocido');
  	      $documento->setRoute($route . '/' . $newFilename);

  	      $entityManager->persist($documento);
  	    }
  	  }
  	}

  	$entityManager->flush();

  	$this->addFlash('success', 'Solicitud creada correctamente, en espera de captura.');
  	return $this->redirect('/dashboard/pedimentos/' . $rfc);
  }
}