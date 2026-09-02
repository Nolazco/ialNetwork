<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\ConsolidatorInstruction;
use App\Entity\DeliveryPoint;
use App\Entity\FreightHauler;
use App\Entity\ImportRequest;
use App\Entity\MerchandiseProfile;
use App\Entity\User;
use App\Notification\ConsolidatorMailer;
use App\Security\CompanyAccess;
use App\Service\ConsolidatorInstructionSheetGenerator;
use App\Service\UploadPath;
use App\Workflow\RequiredDocumentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Instrucciones de entrega al consolidador de carga (XCF): una maniobra
 * aparte del despacho normal (el transporte sigue entregando en XCF con el
 * flujo de siempre) — esto solo genera y manda el papeleo que hoy se llena a
 * mano. Aplica igual a contenedor o carga suelta.
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardConsolidatorInstructions extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyAccess $companyAccess,
        private readonly UploadPath $uploadPath,
        private readonly ConsolidatorInstructionSheetGenerator $sheetGenerator,
        private readonly ConsolidatorMailer $mailer,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/consolidador/nuevo', name: 'consolidator_instruction_new', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function new(#[MapEntity(id: 'id')] ImportRequest $import): Response
    {
        if (!$this->companyAccess->canAccess($import->getIdCompany())) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/consolidatorInstructionForm.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'import' => $import,
            'deliveryPoints' => $this->entityManager->getRepository(DeliveryPoint::class)->findBy(['company' => $import->getIdCompany()], ['name' => 'ASC']),
            'merchandiseProfiles' => $this->entityManager->getRepository(MerchandiseProfile::class)->findBy(['company' => $import->getIdCompany()], ['descripcion' => 'ASC']),
            'haulers' => $this->entityManager->getRepository(FreightHauler::class)->findBy([], ['companyName' => 'ASC']),
            'testRecipient' => ConsolidatorMailer::TEST_RECIPIENT,
        ]);
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/consolidador', name: 'consolidator_instruction_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->companyAccess->canAccess($import->getIdCompany())) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        if (!$this->isCsrfTokenValid('consolidator_instruction_create', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$import->getTariffFraction()) {
            $this->addFlash('error', 'Captura antes la fracción arancelaria en "Alta del pedimento".');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$this->fullPedimentoRoute($import)) {
            $this->addFlash('error', 'Sube antes el "Pedimento completo" en Documentos del ejecutivo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $company = $import->getIdCompany();
        $destinatario = $this->resolveDeliveryPoint($r, $company);

        if ($destinatario === null) {
            $this->addFlash('error', 'Selecciona un destinatario (domicilio fiscal o punto de entrega) o captura uno nuevo completo (nombre, RFC, calle, colonia, municipio, estado y código postal).');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $transport = $this->entityManager->getRepository(FreightHauler::class)->find($r->request->get('transportId'));

        if (!$transport) {
            $this->addFlash('error', 'Selecciona el transportista que se presentará en XCF.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $merchandiseProfile = $this->resolveMerchandiseProfile($r, $company);
        $descripcion = $merchandiseProfile?->getDescripcion() ?? trim((string) $r->request->get('descripcion'));
        $claveSat = $merchandiseProfile?->getClaveSat() ?? trim((string) $r->request->get('claveSat'));
        $claveUnidad = $merchandiseProfile?->getClaveUnidad() ?? trim((string) $r->request->get('claveUnidad'));
        $unidad = $merchandiseProfile?->getUnidad() ?? trim((string) $r->request->get('unidad'));

        if ($descripcion === '' || $claveSat === '' || $claveUnidad === '' || $unidad === '') {
            $this->addFlash('error', 'Selecciona un perfil de mercancía o captura uno nuevo completo (descripción, clave SAT, clave de unidad y unidad).');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $quantity = (int) $r->request->get('quantity');
        $weightKg = (float) str_replace(',', '.', (string) $r->request->get('weightKg'));

        if ($quantity < 1 || $weightKg <= 0) {
            $this->addFlash('error', 'La cantidad y el peso de la mercancía son obligatorios.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $instruction = new ConsolidatorInstruction();
        $instruction->setReference($import);
        $instruction->setDeliveryPoint($destinatario instanceof DeliveryPoint ? $destinatario : null);
        $instruction->setTransport($transport);
        $instruction->setMerchandiseProfile($merchandiseProfile);
        $instruction->setDescripcion($descripcion);
        $instruction->setClaveSat($claveSat);
        $instruction->setClaveUnidad($claveUnidad);
        $instruction->setUnidad($unidad);
        $instruction->setEstibable($merchandiseProfile?->isEstibable() ?? ($r->request->get('estibable') === '1'));
        $instruction->setQuantity($quantity);
        $instruction->setWeightKg($weightKg);
        $instruction->setDeliveryDate($this->parseDeliveryDate($r));
        $instruction->setBilledToClient($r->request->get('billedToClient') === '1');
        $instruction->setCreatedAt(new \DateTimeImmutable());
        $instruction->setCreatedBy($user);

        // Botón de pruebas (ver template, oculto en producción): no crea
        // registro ni sube nada permanente, solo genera el Excel al vuelo
        // para adjuntarlo y lo manda unicamente a ConsolidatorMailer::TEST_RECIPIENT
        // — así se puede probar sin arriesgar mandarle nada a XCF ni tener
        // que ir a /admin a cambiar los correos configurados. Se quita junto
        // con el resto del botón antes de producción.
        $testMode = $r->request->get('testMode') === '1' && $this->environment !== 'prod';

        if (!$testMode) {
            $this->entityManager->persist($instruction);
            $this->entityManager->flush();
        }

        $xlsxBytes = $this->sheetGenerator->generate($instruction);
        $route = 'uploads/consolidador/'.($testMode ? 'pruebas' : $import->getId());
        $absoluteFolder = $this->uploadPath->resolve($route);

        if (!is_dir($absoluteFolder) && !mkdir($absoluteFolder, 0777, true) && !is_dir($absoluteFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta del archivo generado.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $fileName = ($testMode ? uniqid('prueba-') : $instruction->getId()).'.xlsx';
        $absolutePath = $absoluteFolder.'/'.$fileName;
        file_put_contents($absolutePath, $xlsxBytes);
        $instruction->setFileRoute($route.'/'.$fileName);

        if (!$testMode) {
            $this->entityManager->flush();
        }

        $this->mailer->notify($instruction, $testMode);

        if ($testMode) {
            unlink($absolutePath);
            $this->addFlash('success', sprintf('Prueba enviada a %s. No se guardó ningún registro ni se mandó a XCF.', ConsolidatorMailer::TEST_RECIPIENT));
        } else {
            $this->addFlash('success', 'Instrucciones generadas y enviadas a XCF.');
        }

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/consolidador/{instruction}/archivo', name: 'consolidator_instruction_download', requirements: ['id' => '\d+', 'instruction' => '\d+'], methods: ['GET'])]
    public function download(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'instruction')] ConsolidatorInstruction $instruction): BinaryFileResponse
    {
        if (!$this->companyAccess->canAccess($import->getIdCompany()) || $instruction->getReference() !== $import) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $instruction->getFileRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $instruction->suggestedFileName());

        return $response;
    }

    private function fullPedimentoRoute(ImportRequest $import): ?string
    {
        foreach ($import->getRequiredDocuments() as $document) {
            if ($document->getType() === RequiredDocumentType::FULL_PEDIMENTO && $document->getRoute()) {
                return $document->getRoute();
            }
        }

        return null;
    }

    /**
     * Devuelve $company cuando el destinatario elegido es el domicilio
     * fiscal (ver ConsolidatorInstruction::$deliveryPoint == null), un
     * DeliveryPoint del catálogo (existente o capturado al vuelo), o null si
     * no se pudo resolver ninguno de los dos.
     */
    private function resolveDeliveryPoint(Request $r, Company $company): Company|DeliveryPoint|null
    {
        $deliveryPointId = $r->request->get('deliveryPointId');

        if ($deliveryPointId === 'fiscal') {
            return $company;
        }

        if ($deliveryPointId) {
            $deliveryPoint = $this->entityManager->getRepository(DeliveryPoint::class)->find($deliveryPointId);

            return ($deliveryPoint && $deliveryPoint->getCompany() === $company) ? $deliveryPoint : null;
        }

        $name = trim((string) $r->request->get('newDeliveryPointName'));
        $rfc = trim((string) $r->request->get('newDeliveryPointRfc'));
        $street = trim((string) $r->request->get('newDeliveryPointStreet'));
        $neighborhood = trim((string) $r->request->get('newDeliveryPointNeighborhood'));
        $municipality = trim((string) $r->request->get('newDeliveryPointMunicipality'));
        $state = trim((string) $r->request->get('newDeliveryPointState'));
        $zipCode = trim((string) $r->request->get('newDeliveryPointZipCode'));

        if ($name === '' || $rfc === '' || $street === '' || $neighborhood === '' || $municipality === '' || $state === '' || $zipCode === '') {
            return null;
        }

        $extNumber = $this->nullableTrim($r->request->get('newDeliveryPointExtNumber'));
        $intNumber = $this->nullableTrim($r->request->get('newDeliveryPointIntNumber'));
        $locality = $this->nullableTrim($r->request->get('newDeliveryPointLocality'));
        $country = $this->nullableTrim($r->request->get('newDeliveryPointCountry')) ?? 'MEXICO';

        $deliveryPoint = new DeliveryPoint();
        $deliveryPoint->setCompany($company);
        $deliveryPoint->setName($name);
        $deliveryPoint->setAddress($this->composeAddress($street, $extNumber, $neighborhood, $municipality, $state, $zipCode));
        $deliveryPoint->setRfc($rfc);
        $deliveryPoint->setStreet($street);
        $deliveryPoint->setExtNumber($extNumber);
        $deliveryPoint->setIntNumber($intNumber);
        $deliveryPoint->setNeighborhood($neighborhood);
        $deliveryPoint->setLocality($locality);
        $deliveryPoint->setMunicipality($municipality);
        $deliveryPoint->setState($state);
        $deliveryPoint->setCountry($country);
        $deliveryPoint->setZipCode($zipCode);
        $deliveryPoint->setContactName($this->nullableTrim($r->request->get('newDeliveryPointContactName')));
        $deliveryPoint->setContactPhone($this->nullableTrim($r->request->get('newDeliveryPointContactPhone')));
        $deliveryPoint->setContactEmail($this->nullableTrim($r->request->get('newDeliveryPointContactEmail')));

        $this->entityManager->persist($deliveryPoint);

        return $deliveryPoint;
    }

    private function resolveMerchandiseProfile(Request $r, Company $company): ?MerchandiseProfile
    {
        $profileId = $r->request->get('merchandiseProfileId');

        if ($profileId) {
            $profile = $this->entityManager->getRepository(MerchandiseProfile::class)->find($profileId);

            return ($profile && $profile->getCompany() === $company) ? $profile : null;
        }

        $descripcion = trim((string) $r->request->get('descripcion'));
        $claveSat = trim((string) $r->request->get('claveSat'));
        $claveUnidad = trim((string) $r->request->get('claveUnidad'));
        $unidad = trim((string) $r->request->get('unidad'));

        // Captura al vuelo sin guardar en el catalogo: se deja como null y
        // create() usa los valores sueltos del formulario directamente.
        if ($r->request->get('saveMerchandiseProfile') !== '1' || $descripcion === '' || $claveSat === '' || $claveUnidad === '' || $unidad === '') {
            return null;
        }

        $profile = new MerchandiseProfile();
        $profile->setCompany($company);
        $profile->setDescripcion($descripcion);
        $profile->setClaveSat($claveSat);
        $profile->setClaveUnidad($claveUnidad);
        $profile->setUnidad($unidad);
        $profile->setEstibable($r->request->get('estibable') === '1');

        $this->entityManager->persist($profile);

        return $profile;
    }

    /**
     * Fecha estimada de entrega en XCF: opcional, se puede mandar la
     * instrucción sin tener todavía la cita agendada.
     */
    private function parseDeliveryDate(Request $r): ?\DateTimeImmutable
    {
        $value = trim((string) $r->request->get('deliveryDate'));

        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date ? $date->setTime(0, 0) : null;
    }

    private function composeAddress(string $street, ?string $extNumber, string $neighborhood, string $municipality, string $state, string $zipCode): string
    {
        $parts = array_filter([
            trim($street.' '.($extNumber ?? '')),
            $neighborhood,
            $municipality,
            $state,
            'CP '.$zipCode,
        ]);

        return implode(', ', $parts);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
