<?php 

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Container;
use App\Entity\DeliveryPoint;
use App\Entity\Forwarder;
use App\Entity\ImportDocument;
use App\Entity\ImportRequest;
use App\Entity\Provider;
use App\Entity\User;
use App\Security\CompanyAccess;
use App\Workflow\ContainerTypeCatalog;
use App\Workflow\EmailListParser;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\InspectionAuthorityCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

class DashboardImports extends AbstractController {
	public function __construct(
		private readonly CompanyAccess $companyAccess,
		private readonly InspectionAuthorityCatalog $inspectionCatalog,
	) {
	}

	// El listado global cruza todas las empresas, asi que es solo de la agencia.
	// El cliente llega a los suyos desde /dashboard/empresas.
	#[IsGranted('ROLE_EXECUTIVE')]
	#[Route('/dashboard/pedimentos/', methods: ['GET'])]
  public function getImportsGlobal(EntityManagerInterface $entityManager): Response {
  	/** @var User $user */
  	$user = $this->getUser();
    $imports = $entityManager->getRepository(ImportRequest::class)->findAll();

    return $this->render('/dashboard/imports.html.twig', [
    	'name' => $user->getName(),
    	'role' => $user->getRoles()[0],
    	'loged' => 'true',
    	'directions' => ImportRequestWorkflow::DIRECTIONS,
    	'types' => ImportRequestWorkflow::TYPES,
    	'imports' => $imports
    ]);
  }

	#[Route('/dashboard/pedimentos/{rfc}', methods: ['GET'])]
  public function getImports(string $rfc, EntityManagerInterface $entityManager): Response {
  	/** @var User $user */
  	$user = $this->getUser();
    $company = $entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);

    // Sin esto bastaba con cambiar el RFC en la URL para leer los expedientes de
    // cualquier empresa.
    if (!$this->companyAccess->canAccess($company)) {
      throw $this->createAccessDeniedException('Esa empresa no está entre las tuyas.');
    }

    $imports = $entityManager->getRepository(ImportRequest::class)->findBy(['idCompany' => $company]);

    return $this->render('/dashboard/companyImports.html.twig', [
    	'name' => $user->getName(),
    	'role' => $user->getRoles()[0],
    	'loged' => 'true',
    	'directions' => ImportRequestWorkflow::DIRECTIONS,
    	'types' => ImportRequestWorkflow::TYPES,
    	'company' => $company,
    	'imports' => $imports
    ]);
  }

  #[Route('/dashboard/pedimentos/{rfc}/nuevo')]
  public function createImport(string $rfc, EntityManagerInterface $entityManager): Response {
  	/** @var User $user */
  	$user = $this->getUser();
  	if (!$this->companyAccess->canAccess($entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]))) {
  		throw $this->createAccessDeniedException('Esa empresa no está entre las tuyas.');
  	}

  	$providers = $entityManager->getRepository(Provider::class)->findAll();
  	// Solo id/nombre llegan a esta plantilla: los forwarders pueden tener
  	// cuentas bancarias, pero eso nunca debe ser visible para el cliente.
  	$forwarders = $entityManager->getRepository(Forwarder::class)->findAll();
  	$company = $entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);
  	$deliveryPoints = $entityManager->getRepository(DeliveryPoint::class)->findBy(['company' => $company]);

  	return $this->render("/dashboard/newimport.html.twig", [
  		'name' => $user->getName(),
  		'role' => $user->getRoles()[0],
  		'rfc' => $rfc,
  		'providers' => $providers,
  		'forwarders' => $forwarders,
  		'deliveryPoints' => $deliveryPoints,
  		'directions' => ImportRequestWorkflow::DIRECTIONS,
  		'types' => ImportRequestWorkflow::TYPES,
  		'loged' => 'true',
  		'inspectionAuthorities' => InspectionAuthorityCatalog::COMMON,
  		'inspectionNone' => InspectionAuthorityCatalog::NONE,
  		'inspectionUnsure' => InspectionAuthorityCatalog::UNSURE,
  		'inspectionOther' => InspectionAuthorityCatalog::OTHER,
  		'containerTypes' => ContainerTypeCatalog::LABELS,
  	]);
  }

  #[Route('/dashboard/pedimentos/{rfc}/new')]
  public function newImport(string $rfc, Request $r, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response {
  	if (!$this->isCsrfTokenValid('create_import', $r->request->get('_token'))) {
  		$this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');
  		return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  	}

  	$company = $entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);

  	$direction = $r->request->get('direction');
  	$type = $r->request->get('type');

  	if (!$this->companyAccess->canAccess($company)) {
  		throw $this->createAccessDeniedException('Esa empresa no está entre las tuyas.');
  	}

  	if (!$company) {
  		$this->addFlash('error', 'Empresa no válida.');
  		return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  	}

  	// El cliente elige un proveedor del catalogo, o da de alta uno nuevo ahi
  	// mismo si el suyo todavia no existe: el catalogo es de toda la agencia,
  	// no hay que esperar a que el ejecutivo lo capture aparte.
  	$providerId = $r->request->get('provider');

  	if ($providerId) {
  		$provider = $entityManager->getRepository(Provider::class)->find($providerId);

  		if (!$provider) {
  			$this->addFlash('error', 'Selecciona un proveedor válido.');
  			return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  		}
  	} else {
  		$providerName = trim((string) $r->request->get('newProviderName'));
  		$providerTaxId = trim((string) $r->request->get('newProviderTaxId'));
  		$providerAddress = trim((string) $r->request->get('newProviderAddress'));

  		if ($providerName === '' || $providerTaxId === '' || $providerAddress === '') {
  			$this->addFlash('error', 'Selecciona un proveedor del catálogo o captura uno nuevo completo.');
  			return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  		}

  		$provider = new Provider();
  		$provider->setName($providerName);
  		$provider->setTaxId($providerTaxId);
  		$provider->setAddress($providerAddress);

  		$entityManager->persist($provider);
  	}

  	// La mercancia no siempre viene consignada al cliente: a veces viene
  	// consignada a un forwarder, que el cliente elige del catalogo o da de
  	// alta aqui mismo (solo nombre + correos de contacto — sus cuentas
  	// bancarias se capturan aparte, desde el catalogo interno, nunca desde
  	// este formulario).
  	$consignedTo = $r->request->get('consignedTo', 'cliente');
  	$forwarder = null;

  	if ($consignedTo === 'forwarder') {
  		$forwarderId = $r->request->get('forwarderId');

  		if ($forwarderId) {
  			$forwarder = $entityManager->getRepository(Forwarder::class)->find($forwarderId);

  			if (!$forwarder) {
  				$this->addFlash('error', 'Selecciona un forwarder válido.');
  				return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  			}
  		} else {
  			$forwarderName = trim((string) $r->request->get('newForwarderName'));
  			$forwarderEmails = EmailListParser::parse((string) $r->request->get('newForwarderEmails'));

  			if ($forwarderName === '' || $forwarderEmails === []) {
  				$this->addFlash('error', 'Selecciona un forwarder del catálogo o captura uno nuevo completo, con al menos un correo válido.');
  				return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  			}

  			$forwarder = new Forwarder();
  			$forwarder->setName($forwarderName);
  			$forwarder->setContactEmails($forwarderEmails);
  			$forwarder->setBankAccounts([]);

  			$entityManager->persist($forwarder);
  		}
  	}

  	// A donde entregar: domicilio fiscal (default) o un punto del catalogo
  	// propio de esta empresa, o uno nuevo dado de alta ahi mismo.
  	$deliverTo = $r->request->get('deliverTo', 'fiscal');
  	$deliveryPoint = null;

  	if ($deliverTo === 'point') {
  		$deliveryPointId = $r->request->get('deliveryPointId');

  		if ($deliveryPointId) {
  			$deliveryPoint = $entityManager->getRepository(DeliveryPoint::class)->find($deliveryPointId);

  			if (!$deliveryPoint || $deliveryPoint->getCompany() !== $company) {
  				$this->addFlash('error', 'Selecciona un punto de entrega válido.');
  				return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  			}
  		} else {
  			$newPointName = trim((string) $r->request->get('newDeliveryPointName'));
  			$newPointAddress = trim((string) $r->request->get('newDeliveryPointAddress'));

  			if ($newPointName === '' || $newPointAddress === '') {
  				$this->addFlash('error', 'Selecciona un punto de entrega del catálogo o captura uno nuevo completo.');
  				return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  			}

  			$deliveryPoint = new DeliveryPoint();
  			$deliveryPoint->setCompany($company);
  			$deliveryPoint->setName($newPointName);
  			$deliveryPoint->setAddress($newPointAddress);

  			// Opcionales: no hacen falta para el uso normal de "Entregar en",
  			// solo si este punto se usa despues como destinatario de las
  			// instrucciones al consolidador de carga (ver ConsolidatorInstruction).
  			$deliveryPoint->setRfc($this->nullableTrim($r->request->get('newDeliveryPointRfc')));
  			$deliveryPoint->setStreet($this->nullableTrim($r->request->get('newDeliveryPointStreet')));
  			$deliveryPoint->setExtNumber($this->nullableTrim($r->request->get('newDeliveryPointExtNumber')));
  			$deliveryPoint->setIntNumber($this->nullableTrim($r->request->get('newDeliveryPointIntNumber')));
  			$deliveryPoint->setNeighborhood($this->nullableTrim($r->request->get('newDeliveryPointNeighborhood')));
  			$deliveryPoint->setLocality($this->nullableTrim($r->request->get('newDeliveryPointLocality')));
  			$deliveryPoint->setMunicipality($this->nullableTrim($r->request->get('newDeliveryPointMunicipality')));
  			$deliveryPoint->setState($this->nullableTrim($r->request->get('newDeliveryPointState')));
  			$deliveryPoint->setCountry($this->nullableTrim($r->request->get('newDeliveryPointCountry')) ?? 'MEXICO');
  			$deliveryPoint->setZipCode($this->nullableTrim($r->request->get('newDeliveryPointZipCode')));
  			$deliveryPoint->setContactName($this->nullableTrim($r->request->get('newDeliveryPointContactName')));
  			$deliveryPoint->setContactPhone($this->nullableTrim($r->request->get('newDeliveryPointContactPhone')));
  			$deliveryPoint->setContactEmail($this->nullableTrim($r->request->get('newDeliveryPointContactEmail')));

  			$entityManager->persist($deliveryPoint);
  		}
  	}

  	$deliveryInstructions = trim((string) $r->request->get('deliveryInstructions'));

  	// El par direccion + tipo decide la secuencia de estados del expediente, asi
  	// que no puede quedar en cualquier valor.
  	if (!isset(ImportRequestWorkflow::DIRECTIONS[$direction]) || !isset(ImportRequestWorkflow::TYPES[$type])) {
  		$this->addFlash('error', 'Operación o tipo de carga no válido.');
  		return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  	}

  	// Lo que el cliente anticipa sobre la inspeccion: no gatea nada por si
  	// solo (eso lo sigue decidiendo el certificado real mas adelante), es
  	// solo un aviso temprano para el ejecutivo.
  	$inspectionAuthority = $this->inspectionCatalog->resolve(
  		$r->request->get('inspectionAuthority'),
  		$r->request->get('customInspectionAuthority')
  	);

  	if ($inspectionAuthority === null) {
  		$this->addFlash('error', 'Selecciona si la mercancía requiere inspección.');
  		return $this->redirect('/dashboard/pedimentos/' . $rfc . '/nuevo');
  	}

  	$import = new ImportRequest();
  	$import->setClientReference($r->request->get('ref'));
  	$import->setAgencyReference('Pendiente');
  	$import->setIdCompany($company);
  	$import->setIdProvider($provider);
  	$import->setForwarder($forwarder);
  	$import->setDeliveryPoint($deliveryPoint);
  	$import->setDeliveryInstructions($deliveryInstructions !== '' ? $deliveryInstructions : null);
  	$import->setGoods($r->request->get('goods'));
  	$import->setImportNumber('Pendiente');
  	$import->setEta(new \DateTimeImmutable($r->request->get('eta')));
  	// Sin recinto: el cliente rara vez sabe a cual va a llegar su mercancia.
  	// Lo asigna el ejecutivo al dar de alta el pedimento.
  	$import->setExpectedInspectionAuthority($inspectionAuthority);
  	$import->setDirection($direction);
  	$import->setType($type);
  	$import->setStatus(ImportRequestWorkflow::PENDING);
  	$import->setTravelsWithConsolidator($r->request->get('travelsWithConsolidator') === '1');

  	$entityManager->persist($import);

  	if ($type === ImportRequestWorkflow::TYPE_CONTAINER) {
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
  	      $documento->setUploadedAt(new \DateTimeImmutable());

  	      $entityManager->persist($documento);
  	    }
  	  }
  	}

  	$entityManager->flush();

  	$this->addFlash('success', 'Solicitud creada correctamente, en espera de captura.');
  	return $this->redirect('/dashboard/pedimentos/' . $rfc);
  }

  private function nullableTrim(mixed $value): ?string
  {
  	$value = trim((string) $value);

  	return $value === '' ? null : $value;
  }
}