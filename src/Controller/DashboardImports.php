<?php 

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Container;
use App\Entity\ContainerYard;
use App\Entity\ImportDocument;
use App\Entity\ImportRequest;
use App\Entity\Provider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class DashboardImports extends AbstractController {
	#[Route('/dashboard/pedimentos/', methods: ['GET'])]
  public function getImportsGlobal(Request $r, EntityManagerInterface $entityManager): Response {
  	$session = $r->getSession();
    $imports = $entityManager->getRepository(ImportRequest::class)->findAll();

    return $this->render('/dashboard/imports.html.twig', [
    	'name' => $session->get('name'),
    	'role' => $session->get('role'),
    	'loged' => 'true',
    	'imports' => $imports
    ]);
  }

	#[Route('/dashboard/pedimentos/{rfc}', methods: ['GET'])]
  public function getImports(string $rfc, Request $r, EntityManagerInterface $entityManager): Response {
  	$session = $r->getSession();
    $company = $entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);
    $imports = $entityManager->getRepository(ImportRequest::class)->findBy(['idCompany' => $company]);

    return $this->render('/dashboard/companyImports.html.twig', [
    	'name' => $session->get('name'),
    	'role' => $session->get('role'),
    	'loged' => 'true',
    	'company' => $company,
    	'imports' => $imports
    ]);
  }

  #[Route('/dashboard/pedimentos/{rfc}/nuevo')]
  public function createImport(string $rfc, Request $r, EntityManagerInterface $entityManager): Response {
  	$session = $r->getSession();
  	$providers = $entityManager->getRepository(Provider::class)->findAll();

  	return $this->render("/dashboard/newimport.html.twig", [
  		'name' => $session->get('name'),
  		'role' => $session->get('role'),
  		'rfc' => $rfc,
  		'providers' => $providers,
  		'loged' => 'true'
  	]);
  }

  #[Route('/dashboard/pedimentos/{rfc}/new')]
  public function newImport(string $rfc, Request $r, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response {
  	$session = $r->getSession();
  	$company = $entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);
  	$yard = $entityManager->getRepository(ContainerYard::class)->findOneBy(['cr' => '39']);
  	//dd($r->request->get('provider'));
  	$provider = $entityManager->getRepository(Provider::class)->find(['id' => $r->request->get('provider')]);

  	$import = new ImportRequest();
  	$import->setClientReference($r->request->get('ref'));
  	$import->setAgencyReference('Pendiente');
  	$import->setIdCompany($company);
  	$import->setIdProvider($provider);
  	$import->setGoods($r->request->get('goods'));
  	$import->setImportNumber('Pendiente');
  	$import->setEta($r->request->get('eta'));
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