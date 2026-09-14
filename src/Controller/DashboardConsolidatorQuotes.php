<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\ConsolidatorQuote;
use App\Entity\DeliveryPoint;
use App\Entity\ImportRequest;
use App\Entity\User;
use App\Notification\ConsolidatorQuoteMailer;
use App\Security\CompanyAccess;
use App\Workflow\MerchandiseTypeCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Solicitud de cotización al consolidador de carga (XCF): a diferencia de las
 * instrucciones de entrega (ver DashboardConsolidatorInstructions), esto se
 * manda ANTES de tener transporte asignado — solo para que XCF de un precio.
 * No genera ningun documento, es un correo directo.
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardConsolidatorQuotes extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyAccess $companyAccess,
        private readonly ConsolidatorQuoteMailer $mailer,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/consolidador/cotizacion/nueva', name: 'consolidator_quote_new', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function new(#[MapEntity(id: 'id')] ImportRequest $import): Response
    {
        if (!$this->companyAccess->canAccess($import->getIdCompany())) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/consolidatorQuoteForm.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'import' => $import,
            'deliveryPoints' => $this->entityManager->getRepository(DeliveryPoint::class)->findByCompany($import->getIdCompany()),
            'merchandiseTypes' => MerchandiseTypeCatalog::LABELS,
            'testRecipient' => ConsolidatorQuoteMailer::TEST_RECIPIENT,
        ]);
    }

    #[Route('/dashboard/pedimentos/expediente/{id}/consolidador/cotizacion', name: 'consolidator_quote_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->companyAccess->canAccess($import->getIdCompany())) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        if (!$this->isCsrfTokenValid('consolidator_quote_create', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$import->getTariffFraction()) {
            $this->addFlash('error', 'Captura antes la fracción arancelaria en "Alta del pedimento".');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $company = $import->getIdCompany();
        $destinatario = $this->resolveDeliveryPoint($r, $company);

        if ($destinatario === null) {
            $this->addFlash('error', 'Selecciona un destinatario (domicilio fiscal o punto de entrega) o captura uno nuevo completo (nombre, RFC, calle, colonia, municipio, estado y código postal).');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $descripcion = trim((string) $r->request->get('descripcion'));
        $claveSat = trim((string) $r->request->get('claveSat'));
        $unidad = trim((string) $r->request->get('unidad'));

        if ($descripcion === '' || $claveSat === '' || $unidad === '') {
            $this->addFlash('error', 'La mercancía, la clave SAT y la unidad son obligatorias.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $quantity = (int) $r->request->get('quantity');
        $weightKg = (float) str_replace(',', '.', (string) $r->request->get('weightKg'));
        $cubicaje = (float) str_replace(',', '.', (string) $r->request->get('cubicaje'));

        if ($quantity < 1 || $weightKg <= 0 || $cubicaje <= 0) {
            $this->addFlash('error', 'Los bultos, el peso y el cubicaje de la mercancía son obligatorios.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $merchandiseType = (string) $r->request->get('merchandiseType');

        if (!(new MerchandiseTypeCatalog())->isValid($merchandiseType)) {
            $this->addFlash('error', 'Selecciona qué tipo de mercancía es.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $quote = new ConsolidatorQuote();
        $quote->setReference($import);
        $quote->setDeliveryPoint($destinatario instanceof DeliveryPoint ? $destinatario : null);
        $quote->setDescripcion($descripcion);
        $quote->setClaveSat($claveSat);
        $quote->setUnidad($unidad);
        $quote->setQuantity($quantity);
        $quote->setWeightKg($weightKg);
        $quote->setCubicaje($cubicaje);
        $quote->setMerchandiseType($merchandiseType);
        $quote->setCreatedAt(new \DateTimeImmutable());
        $quote->setCreatedBy($user);

        // Botón de pruebas (ver template, oculto en producción): no crea
        // registro, solo manda el correo a ConsolidatorQuoteMailer::TEST_RECIPIENT
        // — mismo espíritu que el de instrucciones.
        $testMode = $r->request->get('testMode') === '1' && $this->environment !== 'prod';

        if (!$testMode) {
            $this->entityManager->persist($quote);
            $this->entityManager->flush();
        }

        $this->mailer->notify($quote, $testMode);

        if ($testMode) {
            $this->addFlash('success', sprintf('Prueba enviada a %s. No se guardó ningún registro ni se mandó a XCF.', ConsolidatorQuoteMailer::TEST_RECIPIENT));
        } else {
            $this->addFlash('success', 'Cotización enviada a XCF.');
        }

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Devuelve $company cuando el destinatario elegido es el domicilio
     * fiscal (ver ConsolidatorQuote::$deliveryPoint == null), un DeliveryPoint
     * del catálogo (existente o capturado al vuelo), o null si no se pudo
     * resolver ninguno de los dos. Mismo criterio que
     * DashboardConsolidatorInstructions::resolveDeliveryPoint().
     */
    private function resolveDeliveryPoint(Request $r, Company $company): Company|DeliveryPoint|null
    {
        $deliveryPointId = $r->request->get('deliveryPointId');

        if ($deliveryPointId === 'fiscal') {
            return $company;
        }

        if ($deliveryPointId) {
            $deliveryPoint = $this->entityManager->getRepository(DeliveryPoint::class)->find($deliveryPointId);

            return ($deliveryPoint && $deliveryPoint->belongsTo($company)) ? $deliveryPoint : null;
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
        $deliveryPoint->addCompany($company);
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
