<?php

namespace App\Controller;

use App\Entity\Delivery;
use App\Entity\Container;
use App\Entity\ConsolidatorInstruction;
use App\Entity\ContainerYard;
use App\Entity\EmptyReturn;
use App\Entity\EmptyReturnYard;
use App\Entity\FreightHauler;
use App\Entity\ImportDocument;
use App\Entity\ImportRequest;
use App\Entity\InternInvoice;
use App\Entity\Operation;
use App\Entity\PrevioReport;
use App\Entity\RequiredDocument;
use App\Entity\User;
use App\Notification\DeliveryMailer;
use App\Security\CompanyAccess;
use App\Service\UploadPath;
use App\Soia\ModuladoConfirmer;
use App\Workflow\ContainerTypeCatalog;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\OperationCatalog;
use App\Workflow\RequiredDocumentType;
use App\Workflow\TransportCoordinator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * El expediente visto por el ejecutivo: alta del pedimento, avance de estados y
 * registro de maniobras.
 *
 * Todo lo que mueve el expediente exige ROLE_EXECUTIVE (ROLE_ADMIN lo hereda,
 * ver role_hierarchy en security.yaml), con dos excepciones: la consulta y los
 * documentos del aviso. El cliente puede abrir en solo lectura los expedientes
 * de sus propias empresas para seguir el avance y descargar su cuenta de
 * gastos, y puede seguir anexando documentos del aviso mientras el expediente
 * siga abierto, sin esperar a que el ejecutivo capture nada.
 */
class DashboardCaseFiles extends AbstractController
{
    /**
     * Extensiones que se aceptan tanto en la cuenta de gastos como en los
     * documentos del aviso. Nada ejecutable.
     */
    public const EXPENSE_EXTENSIONS = ['pdf', 'xml', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'docx', 'csv'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportRequestWorkflow $workflow,
        private readonly OperationCatalog $catalog,
        private readonly TransportCoordinator $transport,
        private readonly CompanyAccess $companyAccess,
        private readonly ModuladoConfirmer $moduladoConfirmer,
        private readonly UploadPath $uploadPath,
        private readonly ContainerTypeCatalog $containerTypeCatalog,
        private readonly DeliveryMailer $deliveryMailer,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    /**
     * El ejecutivo ve cualquier expediente; el cliente solo los de las empresas
     * a las que esta afiliado.
     */
    private function canView(ImportRequest $import): bool
    {
        return $this->companyAccess->canAccess($import->getIdCompany());
    }

    /**
     * Para la prueba de entrega y el EIR: ademas del cliente/ejecutivo que ya
     * ve el expediente (canView), el transportista dueño de ESE despacho en
     * concreto tambien debe poder verlos, aunque no tenga acceso al resto del
     * expediente (proforma, facturas, etc.).
     */
    /**
     * @return list<FreightHauler>
     */
    private function haulersFor(User $user): array
    {
        return $this->entityManager->getRepository(FreightHauler::class)->findBy(['id_user' => $user]);
    }

    #[Route('/dashboard/pedimentos/expediente/{id}', name: 'case_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] ImportRequest $import): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->canView($import)) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        $nextStatuses = $this->workflow->nextStatuses($import);
        $missingByStatus = [];

        foreach ($nextStatuses as $status) {
            $missingByStatus[$status] = $this->workflow->missingRequirements($import, $status);
        }

        $singleSlotDocuments = [];
        $advanceRequests = [];

        foreach ($import->getRequiredDocuments() as $document) {
            if ($document->getType() === RequiredDocumentType::ADVANCE_REQUEST) {
                $advanceRequests[] = $document;
            } else {
                // Slot unico: si por algun motivo hubiera mas de una fila del
                // mismo tipo, se queda con la mas reciente.
                $singleSlotDocuments[$document->getType()] = $document;
            }
        }

        // Otros expedientes con los que se podria compartir una misma unidad
        // (mismo cliente o no, sin restriccion): cualquiera que hoy admita
        // aviso al transporte, salvo este mismo — pero solo del mismo tipo de
        // carga: un camion de contenedor no lleva bultos sueltos, y viceversa.
        // Se precalculan sus propios contenedores sin asignar para que el
        // formulario los muestre/oculte por JS sin ir de vuelta al servidor.
        $shareableImports = [];
        $shareableContainers = [];

        foreach ($this->entityManager->getRepository(ImportRequest::class)->findBy([], ['clientReference' => 'ASC']) as $candidate) {
            if ($candidate === $import || $candidate->getType() !== $import->getType() || !$this->workflow->canAssignTransport($candidate)) {
                continue;
            }

            if ($candidate->getType() === ImportRequestWorkflow::TYPE_CONTAINER) {
                $candidateContainers = $this->transport->unassignedContainers($candidate);

                // Sin contenedores libres no aporta nada a la unidad: listarlo
                // de todos modos solo confunde (parece un tercer contenedor
                // seleccionable cuando en realidad no hay nada que agregar).
                if ($candidateContainers === []) {
                    continue;
                }

                $shareableContainers[$candidate->getId()] = $candidateContainers;
            }

            $shareableImports[] = $candidate;
        }

        return $this->render('/dashboard/caseFile.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'import' => $import,
            'sequence' => $this->workflow->roadmapSequence($import),
            'offsiteInspectionExpected' => $this->workflow->offsiteInspectionExpected($import),
            'completed' => $this->workflow->completedStatuses($import),
            'skipped' => $this->workflow->skippedStatuses($import),
            'nextStatuses' => $nextStatuses,
            'missingByStatus' => $missingByStatus,
            'progress' => $this->workflow->progress($import),
            'directions' => ImportRequestWorkflow::DIRECTIONS,
            'types' => ImportRequestWorkflow::TYPES,
            'maniobras' => OperationCatalog::COMMON,
            'maniobraOther' => OperationCatalog::OTHER,
            'operations' => $this->entityManager->getRepository(Operation::class)
                ->findBy(['reference' => $import], ['date' => 'ASC', 'id' => 'ASC']),
            'canAssignTransport' => $this->workflow->canAssignTransport($import),
            'awaitsTransport' => $this->workflow->awaitsTransport($import),
            'haulers' => $this->entityManager->getRepository(FreightHauler::class)->findBy([], ['companyName' => 'ASC']),
            'yards' => $this->entityManager->getRepository(ContainerYard::class)->findBy([], ['name' => 'ASC']),
            'emptyReturnYards' => $this->entityManager->getRepository(EmptyReturnYard::class)->findBy([], ['name' => 'ASC']),
            'containerTypes' => ContainerTypeCatalog::LABELS,
            'unassignedContainers' => $this->transport->unassignedContainers($import),
            'maxContainers' => Delivery::MAX_CONTAINERS,
            'shareableImports' => $shareableImports,
            'shareableContainers' => $shareableContainers,
            'requiresEmptyReturn' => $this->workflow->requiresEmptyReturn($import),
            'containersPendingReturn' => $this->transport->containersPendingReturn($import),
            'containersPendingSchedule' => $this->transport->containersPendingSchedule($import),
            'scheduledReturns' => array_filter($import->getEmptyReturns()->toArray(), static fn (EmptyReturn $return): bool => !$return->isExecuted()),
            'executedReturns' => array_filter($import->getEmptyReturns()->toArray(), static fn (EmptyReturn $return): bool => $return->isExecuted()),
            'previoReports' => $this->entityManager->getRepository(PrevioReport::class)
                ->findBy(['reference' => $import], ['date' => 'DESC', 'id' => 'DESC']),
            'consolidatorInstructions' => $this->entityManager->getRepository(ConsolidatorInstruction::class)
                ->findBy(['reference' => $import], ['createdAt' => 'DESC']),
            'expenses' => $import->getInternInvoices(),
            'allowedExpenseTypes' => self::EXPENSE_EXTENSIONS,
            'allowedDocumentTypes' => self::EXPENSE_EXTENSIONS,
            'requiredDocumentTypes' => RequiredDocumentType::SINGLE_SLOT,
            'requiredDocuments' => $singleSlotDocuments,
            'advanceRequests' => $advanceRequests,
        ]);
    }

    /**
     * Documentos del aviso: a diferencia del resto del expediente, el cliente
     * tambien puede anexar aqui, ya que son sus propios documentos (factura,
     * lista de empaque, certificados...) y no tiene sentido hacerlo esperar al
     * ejecutivo para subir uno que le hizo falta al dar de alta el pedimento.
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/documentos', name: 'case_file_document', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addDocuments(#[MapEntity(id: 'id')] ImportRequest $import, Request $r, SluggerInterface $slugger): Response
    {
        if (!$this->canView($import)) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        if (!$this->isCsrfTokenValid('case_file_document', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($import->getStatus() === ImportRequestWorkflow::FINISHED) {
            $this->addFlash('error', 'El expediente ya está finalizado, no admite más documentos.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $types = $r->request->all('documentTypes');
        $files = $r->files->all('documents');

        $folder = 'uploads/empresas/'.$import->getIdCompany()->getRfc().$import->getClientReference();
        $absoluteFolder = $this->uploadPath->resolve($folder);

        if (!is_dir($absoluteFolder) && !mkdir($absoluteFolder, 0777, true) && !is_dir($absoluteFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta de documentos.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $stored = 0;
        $rejected = [];

        foreach ($files as $index => $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            // Lista blanca: aunque ya no caen bajo public/, sigue sin haber
            // motivo para aceptar nada ejecutable.
            if (!in_array($extension, self::EXPENSE_EXTENSIONS, true)) {
                $rejected[] = $original;

                continue;
            }

            $type = trim((string) ($types[$index] ?? ''));

            if ($type === '') {
                $type = pathinfo($original, PATHINFO_FILENAME);
            }

            $name = $slugger->slug(pathinfo($original, PATHINFO_FILENAME)).'-'.uniqid().'.'.$extension;

            try {
                $file->move($absoluteFolder, $name);
            } catch (FileException) {
                $rejected[] = $original;

                continue;
            }

            $document = new ImportDocument();
            $document->setType($type);
            $document->setRoute($folder.'/'.$name);
            $document->setUploadedAt(new \DateTimeImmutable());
            $import->addImportDocument($document);

            $this->entityManager->persist($document);
            ++$stored;
        }

        if ($stored === 0 && $rejected === []) {
            $this->addFlash('error', 'Selecciona al menos un documento.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $this->entityManager->flush();

        if ($stored > 0) {
            $this->addFlash('success', sprintf('%d documento(s) anexados al aviso.', $stored));
        }

        if ($rejected !== []) {
            $this->addFlash('error', sprintf(
                'No se aceptaron: %s. Formatos permitidos: %s.',
                implode(', ', $rejected),
                implode(', ', self::EXPENSE_EXTENSIONS)
            ));
        }

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Descarga un documento del aviso. Antes era un link estatico bajo
     * public/, descargable por cualquiera con la URL sin necesidad de
     * sesion — ahora exige la misma regla de acceso que ya usa el
     * expediente (canView).
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/documentos/{document}/archivo', name: 'case_file_document_download', requirements: ['id' => '\d+', 'document' => '\d+'], methods: ['GET'])]
    public function downloadDocument(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'document')] ImportDocument $document): BinaryFileResponse
    {
        if (!$this->canView($import) || $document->getReference() !== $import) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $document->getRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

        return $response;
    }

    /**
     * La fecha de arribo que captura el cliente al dar de alta suele ser un
     * estimado, no la definitiva: por eso cliente y ejecutivo pueden
     * corregirla despues. Una vez marcada "confirmada", solo el ejecutivo
     * puede seguir editandola (el cliente ni siquiera ve el formulario, ver
     * caseFile.html.twig, pero se blinda tambien aqui).
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/eta', name: 'case_file_eta', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateEta(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->canView($import)) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        if (!$this->isCsrfTokenValid('case_file_eta', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($import->isEtaConfirmed() && !$this->isGranted('ROLE_EXECUTIVE')) {
            $this->addFlash('error', 'La fecha de arribo ya está confirmada; solo la agencia puede corregirla.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $eta = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('eta'));

        if (!$eta) {
            $this->addFlash('error', 'La fecha de arribo es obligatoria.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $import->setEta($eta);

        $confirmed = (bool) $r->request->get('etaConfirmed');

        // Un cliente puede confirmarla, pero no quitarle la confirmacion una
        // vez puesta: eso queda solo para el ejecutivo, por si hay que
        // corregir un error.
        if ($this->isGranted('ROLE_EXECUTIVE') || $confirmed) {
            $import->setEtaConfirmed($confirmed);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Fecha de arribo actualizada.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * El cliente muchas veces no sabe todavia que tipo de contenedor le va a
     * tocar cuando da de alta la solicitud (ver DashboardImports::newImport(),
     * que lo deja en "Desconocido" si no se elige nada) — se corrige aqui
     * despues, sin restriccion de estatus del expediente: es un dato
     * descriptivo, no algo que el flujo de despacho dependa de que sea exacto.
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/contenedores/{container}/editar', name: 'case_file_container_edit', requirements: ['id' => '\d+', 'container' => '\d+'], methods: ['POST'])]
    public function editContainer(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'container')] Container $container, Request $r): Response
    {
        if (!$this->canView($import)) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        if (!$container->getReference()->contains($import)) {
            $this->addFlash('error', 'Ese contenedor no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$this->isCsrfTokenValid('case_file_container_edit', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $type = (string) $r->request->get('type');

        if (!$this->containerTypeCatalog->isValid($type)) {
            $this->addFlash('error', 'Selecciona un tipo de contenedor válido.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $container->setType($type);
        $this->entityManager->flush();

        $this->addFlash('success', 'Tipo de contenedor actualizado.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Si la mercancia viaja o no con el consolidador de carga (XCF). Lo suele
     * avisar el cliente desde la alta de la solicitud, pero los planes pueden
     * cambiar, asi que sigue siendo editable despues (ver
     * ImportRequestWorkflow::canAssignTransport()).
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/consolidador-flag', name: 'case_file_consolidator_flag', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateTravelsWithConsolidator(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->canView($import)) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        if (!$this->isCsrfTokenValid('case_file_consolidator_flag', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $import->setTravelsWithConsolidator($r->request->get('travelsWithConsolidator') === '1');
        $this->entityManager->flush();

        $this->addFlash('success', 'Actualizado.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Documentos del ejecutivo, de slot unico (proforma, BL revalidado,
     * pedimentos...): subir uno nuevo reemplaza al que hubiera, para que se
     * puedan corregir sin dejar basura de versiones viejas.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/requisitos', name: 'case_file_required_document', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addRequiredDocument(#[MapEntity(id: 'id')] ImportRequest $import, Request $r, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('case_file_required_document', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $type = (string) $r->request->get('type');

        if (!in_array($type, RequiredDocumentType::SINGLE_SLOT, true)) {
            $this->addFlash('error', 'Tipo de documento no válido.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $file = $r->files->get('document');

        if (!$file || !$file->isValid()) {
            $this->addFlash('error', 'Selecciona un archivo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $original = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($extension, self::EXPENSE_EXTENSIONS, true)) {
            $this->addFlash('error', sprintf('No se aceptó "%s". Formatos permitidos: %s.', $original, implode(', ', self::EXPENSE_EXTENSIONS)));

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $folder = 'uploads/requisitos/'.$import->getId();
        $absoluteFolder = $this->uploadPath->resolve($folder);

        if (!is_dir($absoluteFolder) && !mkdir($absoluteFolder, 0777, true) && !is_dir($absoluteFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta de documentos.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $name = $slugger->slug(pathinfo($original, PATHINFO_FILENAME)).'-'.uniqid().'.'.$extension;

        try {
            $file->move($absoluteFolder, $name);
        } catch (FileException) {
            $this->addFlash('error', 'No se pudo guardar el archivo, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $document = null;

        foreach ($import->getRequiredDocuments() as $existing) {
            if ($existing->getType() === $type) {
                $document = $existing;

                break;
            }
        }

        if ($document === null) {
            $document = new RequiredDocument();
            $document->setType($type);
            $import->addRequiredDocument($document);
            $this->entityManager->persist($document);
        } elseif ($document->getRoute() && is_file($this->uploadPath->resolve($document->getRoute()))) {
            unlink($this->uploadPath->resolve($document->getRoute()));
        }

        $document->setRoute($folder.'/'.$name);
        $document->setUploadedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $advancedTo = $this->workflow->tryAutoAdvance($import);

        if ($advancedTo !== null) {
            $this->entityManager->flush();
        }

        $this->addFlash('success', $advancedTo !== null
            ? sprintf('"%s" anexado. El expediente pasó a "%s".', $type, $advancedTo)
            : sprintf('"%s" anexado.', $type));

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Solicitudes de anticipo: a diferencia del resto de los documentos del
     * ejecutivo, admite varias (el anticipo inicial suele llevar
     * complementos), asi que aqui siempre se agrega una fila nueva.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/requisitos/anticipo', name: 'case_file_advance_request', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addAdvanceRequest(#[MapEntity(id: 'id')] ImportRequest $import, Request $r, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('case_file_advance_request', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $file = $r->files->get('document');

        if (!$file || !$file->isValid()) {
            $this->addFlash('error', 'Selecciona un archivo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $original = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($extension, self::EXPENSE_EXTENSIONS, true)) {
            $this->addFlash('error', sprintf('No se aceptó "%s". Formatos permitidos: %s.', $original, implode(', ', self::EXPENSE_EXTENSIONS)));

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $folder = 'uploads/requisitos/'.$import->getId();
        $absoluteFolder = $this->uploadPath->resolve($folder);

        if (!is_dir($absoluteFolder) && !mkdir($absoluteFolder, 0777, true) && !is_dir($absoluteFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta de documentos.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $name = $slugger->slug(pathinfo($original, PATHINFO_FILENAME)).'-'.uniqid().'.'.$extension;

        try {
            $file->move($absoluteFolder, $name);
        } catch (FileException) {
            $this->addFlash('error', 'No se pudo guardar el archivo, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $document = new RequiredDocument();
        $document->setType(RequiredDocumentType::ADVANCE_REQUEST);
        $document->setRoute($folder.'/'.$name);
        $document->setUploadedAt(new \DateTimeImmutable());
        $import->addRequiredDocument($document);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Solicitud de anticipo anexada.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/requisitos/anticipo/{document}/eliminar', name: 'case_file_advance_request_delete', requirements: ['id' => '\d+', 'document' => '\d+'], methods: ['POST'])]
    public function deleteAdvanceRequest(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'document')] RequiredDocument $document, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_advance_request_delete', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($document->getReference() !== $import || $document->getType() !== RequiredDocumentType::ADVANCE_REQUEST) {
            $this->addFlash('error', 'Ese documento no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($document->getRoute() && is_file($this->uploadPath->resolve($document->getRoute()))) {
            unlink($this->uploadPath->resolve($document->getRoute()));
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Solicitud de anticipo eliminada.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Descarga un documento del ejecutivo (proforma, BL, pedimentos,
     * comprobante de cita, certificado de inspección o solicitud de
     * anticipo). Antes era un link estatico bajo public/, descargable por
     * cualquiera con la URL sin sesion — ahora exige la misma regla de
     * acceso que ya usa el expediente (canView).
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/requisitos/{document}/archivo', name: 'case_file_required_document_download', requirements: ['id' => '\d+', 'document' => '\d+'], methods: ['GET'])]
    public function downloadRequiredDocument(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'document')] RequiredDocument $document): BinaryFileResponse
    {
        if (!$this->canView($import) || $document->getReference() !== $import) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $document->getRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

        return $response;
    }

    /**
     * Aviso al transporte: asigna un camion al expediente.
     *
     * En importacion el camion recoge en el recinto y lleva la mercancia al
     * cliente; en exportacion es al reves. En ambos casos un camion carga como
     * mucho dos contenedores, asi que un expediente contenerizado necesita
     * varios avisos.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/transporte', name: 'case_file_transport', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assignTransport(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_transport', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($import->travelsWithConsolidator() && $import->getConsolidatorInstructions()->isEmpty()) {
            $this->addFlash('error', 'Esta mercancía viaja con XCF — manda antes las instrucciones al consolidador para poder avisar al transporte.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$this->workflow->canAssignTransport($import)) {
            $this->addFlash('error', sprintf('El expediente no admite aviso al transporte estando en "%s".', $import->getStatus()));

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Vacio es a proposito: "transporte pendiente" cuando ya se tiene la
        // cita pero todavia no se sabe que transportista la cubrira.
        $transportId = (string) $r->request->get('transport');
        $hauler = null;

        if ($transportId !== '') {
            $hauler = $this->entityManager->getRepository(FreightHauler::class)->find($transportId);

            if (!$hauler) {
                $this->addFlash('error', 'Selecciona un transportista válido.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('date'));
        $hour = \DateTimeImmutable::createFromFormat('H:i', (string) $r->request->get('hour'));

        if (!$date || !$hour) {
            $this->addFlash('error', 'La fecha y la hora del despacho son obligatorias.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Otros expedientes que comparten esta misma unidad (mismo cliente o
        // no: no hay restriccion, el ejecutivo decide). Cada uno se revalida
        // por su cuenta: lo que venga del formulario no es de fiar.
        $allImports = [$import];

        foreach ($r->request->all('additionalImports') as $additionalId) {
            $candidate = $this->entityManager->getRepository(ImportRequest::class)->find($additionalId);

            if (!$candidate || $candidate === $import || !$this->workflow->canAssignTransport($candidate)) {
                $this->addFlash('error', 'Alguno de los expedientes adicionales ya no admite aviso al transporte.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            // Una unidad hace un solo trayecto: no se puede recoger en el
            // recinto y en planta a la vez.
            if ($candidate->getDirection() !== $import->getDirection()) {
                $this->addFlash('error', 'No se puede compartir una unidad entre una importación y una exportación.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            // Contenedor y carga suelta no se pueden compartir: un camion de
            // contenedor no lleva bultos sueltos, y viceversa.
            if ($candidate->getType() !== $import->getType()) {
                $this->addFlash('error', 'No se puede compartir una unidad entre carga contenerizada y carga suelta.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            $allImports[] = $candidate;
        }

        // Ficha de la mercancia que le toca al transportista, ademas de la
        // cita — se le manda por correo al confirmar (ver DeliveryMailer).
        $claveSat = trim((string) $r->request->get('claveSat'));
        $descripcion = trim((string) $r->request->get('descripcion'));
        $embalaje = trim((string) $r->request->get('embalaje'));
        $bultos = (int) $r->request->get('bultos');
        $weightKg = (float) str_replace(',', '.', (string) $r->request->get('weightKg'));
        $cubicaje = (float) str_replace(',', '.', (string) $r->request->get('cubicaje'));
        $pedimentoFile = $r->files->get('pedimentoSimplificado');

        if ($claveSat === '' || $descripcion === '' || $embalaje === '' || $bultos < 1 || $weightKg <= 0 || $cubicaje <= 0) {
            $this->addFlash('error', 'Clave SAT, mercancía, embalaje, bultos, peso y cubicaje son obligatorios.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$pedimentoFile || !$pedimentoFile->isValid()) {
            $this->addFlash('error', 'Adjunta el pedimento simplificado.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Solo aplica si el expediente viaja con XCF: es el folio que ellos
        // generan al recibir las instrucciones (ver ConsolidatorMailer).
        $xcfFolio = null;

        if ($import->travelsWithConsolidator()) {
            $xcfFolio = trim((string) $r->request->get('xcfFolio'));

            if ($xcfFolio === '') {
                $this->addFlash('error', 'Captura el folio que XCF generó al recibir las instrucciones.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        $delivery = new Delivery();

        // addDelivery (no addReference directo) para que tambien quede
        // sincronizado el lado inverso en memoria: tryAutoAdvance(), mas
        // abajo, necesita ver ya reflejado este despacho en
        // $imp->getDeliveries() dentro de esta misma peticion.
        foreach ($allImports as $imp) {
            $imp->addDelivery($delivery);
        }

        $delivery->setTransport($hauler);
        $delivery->setDate($date->setTime(0, 0));
        $delivery->setHour($hour);
        $delivery->setClaveSat($claveSat);
        $delivery->setDescripcion($descripcion);
        $delivery->setEmbalaje($embalaje);
        $delivery->setBultos($bultos);
        $delivery->setWeightKg($weightKg);
        $delivery->setCubicaje($cubicaje);
        $delivery->setXcfFolio($xcfFolio);

        $containerizedImports = array_filter($allImports, static fn (ImportRequest $imp) => $imp->getType() === ImportRequestWorkflow::TYPE_CONTAINER);

        if ($containerizedImports !== []) {
            $available = [];

            foreach ($containerizedImports as $imp) {
                foreach ($this->transport->unassignedContainers($imp) as $container) {
                    $available[$container->getId()] = $container;
                }
            }

            $chosen = $r->request->all('containers');

            if (count($chosen) === 0) {
                $this->addFlash('error', 'Selecciona al menos un contenedor para este camión.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            if (count($chosen) > Delivery::MAX_CONTAINERS) {
                $this->addFlash('error', sprintf('Un camión no puede cargar más de %d contenedores.', Delivery::MAX_CONTAINERS));

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            foreach ($chosen as $containerId) {
                // Solo contenedores de los expedientes elegidos que no viajen
                // ya en otro camion: la lista llega del formulario y no es de fiar.
                if (!isset($available[(int) $containerId])) {
                    $this->addFlash('error', 'Alguno de los contenedores ya está asignado o no pertenece a los expedientes elegidos.');

                    return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
                }

                $delivery->addContainer($available[(int) $containerId]);
            }
        }

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        // Hasta aqui ya tiene id: la carpeta del pedimento simplificado se
        // nombra con el, igual que uploads/entregas/{id} para la prueba de
        // entrega (ver DashboardDeliveries::storeProof()).
        $route = 'uploads/despachos/'.$delivery->getId();
        $folder = $this->uploadPath->resolve($route);

        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta del pedimento simplificado.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $name = 'pedimento-simplificado-'.uniqid().'.'.$pedimentoFile->guessExtension();

        try {
            $pedimentoFile->move($folder, $name);
        } catch (FileException) {
            $this->addFlash('error', 'No se pudo guardar el pedimento simplificado.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $delivery->setPedimentoSimplificadoRoute($route.'/'.$name);
        $this->entityManager->flush();

        $advanced = [];

        foreach ($allImports as $imp) {
            if ($status = $this->workflow->tryAutoAdvance($imp)) {
                $advanced[] = sprintf('%s pasó a "%s"', $imp->getClientReference(), $status);
            }
        }

        if ($advanced !== []) {
            $this->entityManager->flush();
        }

        $this->deliveryMailer->notify($delivery);

        $this->addFlash('success', sprintf(
            'Cita registrada para el %s con %s%s.%s',
            $date->format('d/m/Y'),
            $hauler ? $hauler->getCompanyName() : 'transporte pendiente por asignar',
            count($allImports) > 1 ? sprintf(' (compartida con %d expediente(s) más)', count($allImports) - 1) : '',
            $advanced !== [] ? ' '.implode(', ', $advanced).'.' : ''
        ));

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Corrige un despacho ya creado (por ejemplo, asignar el transportista
     * real a una cita que se agendó como "transporte pendiente"). Mismo
     * candado que cancelarlo: una vez que el camión salió, ya no se toca.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/transporte/{delivery}/editar', name: 'case_file_transport_edit', requirements: ['id' => '\d+', 'delivery' => '\d+'], methods: ['POST'])]
    public function editTransport(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'delivery')] Delivery $delivery, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_transport_edit', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$delivery->getReferences()->contains($import)) {
            $this->addFlash('error', 'Ese despacho no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($delivery->isDeparted()) {
            $this->addFlash('error', 'No se puede editar un despacho que ya salió.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($delivery->isFailed()) {
            $this->addFlash('error', 'Ese despacho ya está marcado como fallido, no se puede editar. Agenda uno nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $transportId = (string) $r->request->get('transport');
        $hauler = null;

        if ($transportId !== '') {
            $hauler = $this->entityManager->getRepository(FreightHauler::class)->find($transportId);

            if (!$hauler) {
                $this->addFlash('error', 'Selecciona un transportista válido.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('date'));
        $hour = \DateTimeImmutable::createFromFormat('H:i', (string) $r->request->get('hour'));

        if (!$date || !$hour) {
            $this->addFlash('error', 'La fecha y la hora del despacho son obligatorias.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Ficha de la mercancia: se vuelve a exigir completa, igual que la
        // fecha y la hora (ver assignTransport()).
        $claveSat = trim((string) $r->request->get('claveSat'));
        $descripcion = trim((string) $r->request->get('descripcion'));
        $embalaje = trim((string) $r->request->get('embalaje'));
        $bultos = (int) $r->request->get('bultos');
        $weightKg = (float) str_replace(',', '.', (string) $r->request->get('weightKg'));
        $cubicaje = (float) str_replace(',', '.', (string) $r->request->get('cubicaje'));

        if ($claveSat === '' || $descripcion === '' || $embalaje === '' || $bultos < 1 || $weightKg <= 0 || $cubicaje <= 0) {
            $this->addFlash('error', 'Clave SAT, mercancía, embalaje, bultos, peso y cubicaje son obligatorios.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $xcfFolio = $delivery->getXcfFolio();

        if ($import->travelsWithConsolidator()) {
            $xcfFolio = trim((string) $r->request->get('xcfFolio'));

            if ($xcfFolio === '') {
                $this->addFlash('error', 'Captura el folio que XCF generó al recibir las instrucciones.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        // Si cambia el transportista, la unidad/chofer/CFDI que hubiera
        // quedan invalidos: son de la flota del transportista anterior. Le
        // toca al nuevo transportista volver a mandarlos (ver
        // DashboardDeliveries::assignVehicle()).
        if ($delivery->getTransport() !== $hauler) {
            $delivery->setVehicle(null);
            $delivery->setDriver(null);
            $delivery->setCfdiFolio(null);
        }

        $delivery->setTransport($hauler);
        $delivery->setDate($date->setTime(0, 0));
        $delivery->setHour($hour);
        $delivery->setClaveSat($claveSat);
        $delivery->setDescripcion($descripcion);
        $delivery->setEmbalaje($embalaje);
        $delivery->setBultos($bultos);
        $delivery->setWeightKg($weightKg);
        $delivery->setCubicaje($cubicaje);
        $delivery->setXcfFolio($xcfFolio);

        // El pedimento simplificado es opcional aqui: si no se adjunta uno
        // nuevo, se queda el que ya tenia (ver el mismo patron en
        // uploadEmptyReturnEir()).
        $pedimentoFile = $r->files->get('pedimentoSimplificado');

        if ($pedimentoFile && $pedimentoFile->isValid()) {
            $route = 'uploads/despachos/'.$delivery->getId();
            $folder = $this->uploadPath->resolve($route);

            if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
                $this->addFlash('error', 'No se pudo preparar la carpeta del pedimento simplificado.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            $name = 'pedimento-simplificado-'.uniqid().'.'.$pedimentoFile->guessExtension();

            try {
                $pedimentoFile->move($folder, $name);
            } catch (FileException) {
                $this->addFlash('error', 'No se pudo guardar el pedimento simplificado.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            $oldPath = $delivery->getPedimentoSimplificadoRoute() ? $this->uploadPath->resolve($delivery->getPedimentoSimplificadoRoute()) : null;

            if ($oldPath && is_file($oldPath)) {
                unlink($oldPath);
            }

            $delivery->setPedimentoSimplificadoRoute($route.'/'.$name);
        }

        $this->entityManager->flush();

        $this->deliveryMailer->notify($delivery);

        $this->addFlash('success', 'Despacho actualizado.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Asigna un transportista distinto para la devolucion de vacios (caso
     * real: A entrega la mercancía, B devuelve el contenedor vacío). A
     * diferencia de editTransport(), esto se puede hacer aunque el despacho
     * ya haya salido o hasta ya entregado — la necesidad de un transportista
     * distinto para la devolución normalmente se detecta hasta entonces.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/transporte/{delivery}/devolucion', name: 'case_file_transport_return', requirements: ['id' => '\d+', 'delivery' => '\d+'], methods: ['POST'])]
    public function assignReturnTransport(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'delivery')] Delivery $delivery, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_transport_return', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$delivery->getReferences()->contains($import)) {
            $this->addFlash('error', 'Ese despacho no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $transportId = (string) $r->request->get('returnTransport');
        $hauler = null;

        if ($transportId !== '') {
            $hauler = $this->entityManager->getRepository(FreightHauler::class)->find($transportId);

            if (!$hauler) {
                $this->addFlash('error', 'Selecciona un transportista válido.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        $delivery->setReturnTransport($hauler);
        $this->entityManager->flush();

        $this->addFlash('success', $hauler
            ? sprintf('%s hará la devolución de vacíos de este despacho.', $hauler->getCompanyName())
            : 'La devolución de vacíos vuelve a quedar a cargo de quien entregó.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/transporte/{delivery}/cancelar', name: 'case_file_transport_cancel', requirements: ['id' => '\d+', 'delivery' => '\d+'], methods: ['POST'])]
    public function cancelTransport(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'delivery')] Delivery $delivery, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_transport_cancel', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$delivery->getReferences()->contains($import)) {
            $this->addFlash('error', 'Ese despacho no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($delivery->isDeparted()) {
            $this->addFlash('error', 'No se puede cancelar un despacho que ya salió.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($delivery->isFailed()) {
            $this->addFlash('error', 'Ese despacho ya está marcado como fallido: queda como registro, no se puede cancelar.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Compartido con otro(s) expediente(s): cancelar aqui solo desvincula
        // a este, la unidad se mantiene para los demas. Solo se borra del
        // todo cuando este expediente es el unico que le queda.
        if ($delivery->getReferences()->count() > 1) {
            $delivery->removeReference($import);

            foreach ($import->getContainers() as $container) {
                $delivery->removeContainer($container);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Expediente retirado de esa unidad. La cita se mantiene para los demás expedientes que la comparten.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $this->entityManager->remove($delivery);
        $this->entityManager->flush();

        $this->addFlash('success', 'Aviso de transporte cancelado.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Alta del pedimento: captura la referencia interna y el numero de pedimento
     * que el cliente no puede conocer.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/captura', name: 'case_file_capture', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function capture(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_capture', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Dar de alta el pedimento es lo que saca al expediente de "Pendiente",
        // y eso exige tener ya la proforma: sin ella no hay con que respaldar
        // el estatus "Capturado".
        if ($import->getStatus() === ImportRequestWorkflow::PENDING) {
            $missing = $this->workflow->missingRequirements($import, ImportRequestWorkflow::CAPTURED);

            if ($missing !== []) {
                $this->addFlash('error', sprintf('Sube antes: %s.', implode(', ', $missing)));

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        $agencyReference = trim((string) $r->request->get('agencyReference'));
        $importNumber = trim((string) $r->request->get('importNumber'));
        $yard = $this->entityManager->getRepository(ContainerYard::class)->find($r->request->get('yard'));

        if ($agencyReference === '' || $importNumber === '' || !$yard) {
            $this->addFlash('error', 'La referencia de la agencia, el número de pedimento y el recinto son obligatorios.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Opcional a proposito: el cliente no la conoce, y no toda mercancia
        // pasa por el consolidador de carga (unico lugar donde hace falta,
        // ver ConsolidatorInstruction).
        $tariffFraction = trim((string) $r->request->get('tariffFraction'));

        $import->setAgencyReference($agencyReference);
        $import->setImportNumber($importNumber);
        $import->setCr($yard);
        $import->setTariffFraction($tariffFraction !== '' ? $tariffFraction : null);

        // Dar de alta el pedimento es lo que saca al expediente de "Pendiente".
        // Si ya avanzo mas alla, esto es una correccion y no debe retroceder.
        if ($import->getStatus() === ImportRequestWorkflow::PENDING) {
            $import->setStatus(ImportRequestWorkflow::CAPTURED);
            $this->addFlash('success', 'Pedimento dado de alta. El expediente pasó a Capturado.');
        } else {
            $this->addFlash('success', 'Datos del pedimento actualizados.');
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Avanza el expediente al siguiente estado de su secuencia.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/estatus', name: 'case_file_advance', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function advance(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_advance', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $status = (string) $r->request->get('status');

        // Solo se acepta un salto contemplado por la secuencia: nada de mandar
        // el expediente a un estado arbitrario desde el formulario.
        if (!$this->workflow->canTransitionTo($import, $status)) {
            $this->addFlash('error', sprintf('No se puede pasar de "%s" a "%s".', $import->getStatus(), $status));

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $missing = $this->workflow->missingRequirements($import, $status);

        if ($missing !== []) {
            $this->addFlash('error', sprintf('Falta: %s.', implode(', ', $missing)));

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // "Vacío devuelto" lo alcanza el expediente cuando el transportista
        // registra el EIR de cada contenedor, no marcandolo a mano: de otro modo
        // el expediente cerraria sin el respaldo de los patios.
        if ($status === ImportRequestWorkflow::EMPTY_RETURNED) {
            $pending = $this->transport->containersPendingReturn($import);

            if ($pending !== []) {
                $this->addFlash('error', sprintf(
                    'Faltan %d contenedor(es) por devolver. El transportista debe registrar su EIR.',
                    count($pending)
                ));

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        // Un expediente no se cierra sin cuenta de gastos: es lo que el cliente
        // recibe al final y lo que respalda lo cobrado.
        if ($status === ImportRequestWorkflow::FINISHED && $import->getInternInvoices()->isEmpty()) {
            $this->addFlash('error', 'No se puede finalizar el expediente sin su cuenta de gastos.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Deja constancia de que el paso opcional si se realizo: mas adelante el
        // estatus por si solo ya no permitiria distinguirlo de uno omitido.
        if ($this->workflow->isOptional($status)) {
            $import->markOptionalStepTaken($status);
        }

        $import->setStatus($status);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('El expediente pasó a "%s".', $status));

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Consulta el SOIA a mano. Un poller automático (app:soia:poll) hace lo
     * mismo empezando 30 minutos después de la cita, pero el ejecutivo puede
     * forzar una consulta antes de que le toque al automático.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/soia', name: 'case_file_soia_query', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function querySoia(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_soia_query', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // SoiaClient reintenta hasta 5 veces con 30s de timeout por cada una
        // de sus 2 peticiones (hasta 300s en el peor caso, cuando el portal
        // no responde): eso solo, sin contar el resto del request, ya rebasa
        // el max_execution_time por defecto (120s) de php.ini. Si PHP mata la
        // ejecución a medio camino la sesión se pierde y la vista siguiente
        // ve al ejecutivo como no autenticado. Se amplía el límite solo para
        // esta acción en vez de subirlo globalmente.
        set_time_limit(360);

        if (!$import->getImportNumber() || $import->getImportNumber() === ImportRequestWorkflow::PENDING) {
            $this->addFlash('error', 'El expediente todavía no tiene número de pedimento.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $wasModulated = $import->getStatus() === ImportRequestWorkflow::MODULATED;
        $result = $this->moduladoConfirmer->attemptConfirm($import);

        if (!$result->found) {
            $this->addFlash('error', $result->error ?? 'El portal del SOIA no respondió, intenta de nuevo en unos minutos.');
        } elseif ($import->getStatus() === ImportRequestWorkflow::MODULATED && !$wasModulated) {
            $this->addFlash('success', sprintf(
                'Modulación confirmada (%s, %s). El expediente pasó a "Modulado".',
                $result->estado,
                $import->getModuladoAt()?->format('d/m/Y H:i') ?? 'sin fecha'
            ));
        } else {
            $this->addFlash('info', sprintf('Estatus actual en el SOIA: %s.', $result->estado));
        }

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * TEMPORAL — SOLO PARA PRUEBAS. Salta la consulta real al SOIA y marca
     * "Modulado" directamente, para no depender del portal mientras se
     * prueba el resto del flujo. Quitar este metodo, su ruta y el boton en
     * caseFile.html.twig antes de pasar a producción — por eso esta
     * bloqueado fuera de %kernel.environment% dev/test como segundo candado,
     * ademas de recordarlo aqui.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/soia-bypass', name: 'case_file_soia_bypass', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function bypassSoia(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if ($this->environment === 'prod') {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('case_file_soia_bypass', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$this->workflow->canTransitionTo($import, ImportRequestWorkflow::MODULATED)) {
            $this->addFlash('error', sprintf('El expediente no puede pasar a "Modulado" estando en "%s".', $import->getStatus()));

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $import->setModuladoAt(new \DateTimeImmutable());
        $import->setStatus(ImportRequestWorkflow::MODULATED);
        $this->entityManager->flush();

        $this->addFlash('success', 'Modulación simulada (bypass de pruebas, sin consultar el SOIA real). El expediente pasó a "Modulado".');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Registra una maniobra sobre el expediente.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/maniobra', name: 'case_file_operation', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addOperation(#[MapEntity(id: 'id')] ImportRequest $import, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_operation', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $type = $this->catalog->resolve($r->request->get('type'), $r->request->get('customType'));

        if ($type === null) {
            $this->addFlash('error', 'Selecciona una maniobra del catálogo o escribe una.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $date = $r->request->get('date');

        if (!$date || !($parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date))) {
            $this->addFlash('error', 'La fecha de la maniobra no es válida.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $operation = new Operation();
        $operation->setReference($import);
        $operation->setType($type);
        $operation->setDate($parsed->setTime(0, 0));

        $this->entityManager->persist($operation);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Maniobra "%s" registrada.', $type));

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/maniobra/{operation}/eliminar', name: 'case_file_operation_delete', requirements: ['id' => '\d+', 'operation' => '\d+'], methods: ['POST'])]
    public function deleteOperation(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'operation')] Operation $operation, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_operation_delete', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        // Evita borrar una maniobra de otro expediente manipulando la URL.
        if ($operation->getReference() !== $import) {
            $this->addFlash('error', 'Esa maniobra no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $this->entityManager->remove($operation);
        $this->entityManager->flush();

        $this->addFlash('success', 'Maniobra eliminada.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Cuenta de gastos: anexa los documentos que manda el contador.
     *
     * Suele ser un ZIP con el PDF de la cuenta y su XML, mas los comprobantes y
     * facturas que correspondan, asi que el formulario acepta tantas lineas como
     * haga falta y cada una lleva su propio concepto.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/gastos', name: 'case_file_expense', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addExpenses(#[MapEntity(id: 'id')] ImportRequest $import, Request $r, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('case_file_expense', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $concepts = $r->request->all('concepts');
        $files = $r->files->all('documents');

        $folder = 'uploads/gastos/'.$import->getId();
        $absoluteFolder = $this->uploadPath->resolve($folder);

        if (!is_dir($absoluteFolder) && !mkdir($absoluteFolder, 0777, true) && !is_dir($absoluteFolder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta de la cuenta de gastos.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $stored = 0;
        $rejected = [];

        foreach ($files as $index => $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            // Lista blanca: aunque ya no caen bajo public/, sigue sin haber
            // motivo para aceptar nada ejecutable.
            if (!in_array($extension, self::EXPENSE_EXTENSIONS, true)) {
                $rejected[] = $original;

                continue;
            }

            $concept = trim((string) ($concepts[$index] ?? ''));

            if ($concept === '') {
                $concept = pathinfo($original, PATHINFO_FILENAME);
            }

            $name = $slugger->slug(pathinfo($original, PATHINFO_FILENAME)).'-'.uniqid().'.'.$extension;

            try {
                $file->move($absoluteFolder, $name);
            } catch (FileException) {
                $rejected[] = $original;

                continue;
            }

            $invoice = new InternInvoice();
            $invoice->setConcept($concept);
            $invoice->setRoute($folder.'/'.$name);
            $import->addInternInvoice($invoice);

            $this->entityManager->persist($invoice);
            ++$stored;
        }

        if ($stored === 0 && $rejected === []) {
            $this->addFlash('error', 'Selecciona al menos un documento.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $this->entityManager->flush();

        if ($stored > 0) {
            $this->addFlash('success', sprintf('%d documento(s) anexados a la cuenta de gastos.', $stored));
        }

        if ($rejected !== []) {
            $this->addFlash('error', sprintf(
                'No se aceptaron: %s. Formatos permitidos: %s.',
                implode(', ', $rejected),
                implode(', ', self::EXPENSE_EXTENSIONS)
            ));
        }

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/gastos/{invoice}/eliminar', name: 'case_file_expense_delete', requirements: ['id' => '\d+', 'invoice' => '\d+'], methods: ['POST'])]
    public function deleteExpense(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'invoice')] InternInvoice $invoice, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('case_file_expense_delete', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($invoice->getReference() !== $import) {
            $this->addFlash('error', 'Ese documento no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($import->getStatus() === ImportRequestWorkflow::FINISHED) {
            $this->addFlash('error', 'El expediente ya está finalizado: su cuenta de gastos no se toca.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($invoice->getRoute() && is_file($this->uploadPath->resolve($invoice->getRoute()))) {
            unlink($this->uploadPath->resolve($invoice->getRoute()));
        }

        $this->entityManager->remove($invoice);
        $this->entityManager->flush();

        $this->addFlash('success', 'Documento eliminado de la cuenta de gastos.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * Descarga un documento de la cuenta de gastos. Antes era un link
     * estatico bajo public/, descargable por cualquiera con la URL sin
     * sesion — ahora exige la misma regla de acceso que ya usa el
     * expediente (canView).
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/gastos/{invoice}/archivo', name: 'case_file_expense_download', requirements: ['id' => '\d+', 'invoice' => '\d+'], methods: ['GET'])]
    public function downloadExpense(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'invoice')] InternInvoice $invoice): BinaryFileResponse
    {
        if (!$this->canView($import) || $invoice->getReference() !== $import) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $invoice->getRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($path));

        return $response;
    }

    /**
     * Descarga el PDF de un reporte de previo. El ZIP de fotos (photosZipRoute)
     * a propósito NO se protege: debe poder descargarse en cualquier momento
     * sin sesión, así que se queda como link estático bajo public/ (ver
     * DashboardPrevios::create()). El PDF sí queda protegido igual que el
     * resto de documentos del expediente.
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/previos/{previo}/pdf', name: 'case_file_previo_pdf_download', requirements: ['id' => '\d+', 'previo' => '\d+'], methods: ['GET'])]
    public function downloadPrevioPdf(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'previo')] PrevioReport $previo): BinaryFileResponse
    {
        if (!$this->canView($import) || $previo->getReference() !== $import) {
            throw $this->createAccessDeniedException('Ese expediente no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $previo->getPdfRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($path));

        return $response;
    }

    /**
     * Descarga la prueba de entrega de un despacho. La ve quien ya ve el
     * expediente (cliente/ejecutivo) o el transportista dueño de ese despacho
     * en concreto — este ultimo no tiene canView() sobre el resto del
     * expediente, y no debe tenerlo (no debe ver proforma, facturas, etc.).
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/despachos/{delivery}/prueba-entrega', name: 'case_file_delivery_proof_download', requirements: ['id' => '\d+', 'delivery' => '\d+'], methods: ['GET'])]
    public function downloadDeliveryProof(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'delivery')] Delivery $delivery): BinaryFileResponse
    {
        if (!$delivery->getReferences()->contains($import)) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $ownsDelivery = in_array($delivery->getTransport(), $this->haulersFor($user), true);

        if (!$this->canView($import) && !$ownsDelivery) {
            throw $this->createAccessDeniedException('Ese despacho no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $delivery->getProofRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

        return $response;
    }

    /**
     * Descarga el pedimento simplificado de un despacho. Mismo criterio de
     * acceso que la prueba de entrega: cliente/ejecutivo, o el transportista
     * dueño de ese despacho en concreto.
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/despachos/{delivery}/pedimento-simplificado', name: 'case_file_delivery_pedimento_download', requirements: ['id' => '\d+', 'delivery' => '\d+'], methods: ['GET'])]
    public function downloadDeliveryPedimento(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'delivery')] Delivery $delivery): BinaryFileResponse
    {
        if (!$delivery->getReferences()->contains($import)) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $ownsDelivery = in_array($delivery->getTransport(), $this->haulersFor($user), true);

        if (!$this->canView($import) && !$ownsDelivery) {
            throw $this->createAccessDeniedException('Ese despacho no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $delivery->getPedimentoSimplificadoRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

        return $response;
    }

    /**
     * Descarga el EIR escaneado de una devolucion de vacio. Mismo criterio
     * que la prueba de entrega: cliente/ejecutivo via canView(), o el
     * transportista al que se le asigno esa devolucion en concreto (ver
     * Delivery::$returnTransport, un despacho puede devolverlo alguien
     * distinto a quien entrego).
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/vacios/{return}/eir', name: 'case_file_empty_return_eir_download', requirements: ['id' => '\d+', 'return' => '\d+'], methods: ['GET'])]
    public function downloadEmptyReturnEir(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'return')] EmptyReturn $return): BinaryFileResponse
    {
        if ($return->getReference() !== $import) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->canView($import) && !$this->haulerOwnsReturn($return, $user)) {
            throw $this->createAccessDeniedException('Esa devolución no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $return->getEirRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

        return $response;
    }

    /**
     * Descarga la papeleta del patio que el ejecutivo adjunta al programar
     * la cita. Mismo criterio de acceso que el EIR: cliente/ejecutivo, o el
     * transportista al que le toca esa devolucion (antes de que exista un
     * EIR, se resuelve via el despacho, no via EmptyReturn::transport, que
     * todavia no se fija).
     */
    #[Route('/dashboard/pedimentos/expediente/{id}/vacios/{return}/papeleta', name: 'case_file_empty_return_slip_download', requirements: ['id' => '\d+', 'return' => '\d+'], methods: ['GET'])]
    public function downloadEmptyReturnSlip(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'return')] EmptyReturn $return): BinaryFileResponse
    {
        if ($return->getReference() !== $import) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->canView($import) && !$this->haulerOwnsReturn($return, $user)) {
            throw $this->createAccessDeniedException('Esa devolución no pertenece a ninguna de tus empresas.');
        }

        $path = $this->uploadPath->resolve((string) $return->getSlipRoute());

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

        return $response;
    }

    /**
     * ¿El transportista logeado es a quien le toca esta devolucion? Antes de
     * que se ejecute (EmptyReturn::transport todavia null), se resuelve via
     * el despacho vigente del contenedor, igual que registerEmptyReturn() en
     * DashboardDeliveries; ya ejecutada, se compara directo contra quien
     * quedo registrado (puede ya no coincidir con el despacho si este se
     * reasigno despues).
     */
    private function haulerOwnsReturn(EmptyReturn $return, User $user): bool
    {
        $haulers = $this->haulersFor($user);

        if ($haulers === []) {
            return false;
        }

        if ($return->getTransport() !== null) {
            return in_array($return->getTransport(), $haulers, true);
        }

        $delivery = $this->transport->deliveryFor($return->getContainer());

        return $delivery !== null && in_array($delivery->getReturnTransport() ?? $delivery->getTransport(), $haulers, true);
    }

    /**
     * El ejecutivo programa (o corrige, mientras no se haya ejecutado) la
     * devolucion de vacio de un contenedor: patio, fecha de la cita y la
     * papeleta que autoriza recibirlo ahi. Ya no lo elige el transportista —
     * depende de las instrucciones de la naviera, que solo tiene el
     * ejecutivo. Requiere que el contenedor ya tenga transporte asignado
     * (ver assignTransport()): sin eso no hay a quien avisarle la cita.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/vacios/{container}/programar', name: 'case_file_empty_return_schedule', requirements: ['id' => '\d+', 'container' => '\d+'], methods: ['POST'])]
    public function scheduleEmptyReturn(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'container')] Container $container, Request $r, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('case_file_empty_return_schedule', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if (!$container->getReference()->contains($import)) {
            $this->addFlash('error', 'Ese contenedor no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $return = $this->transport->emptyReturnFor($container);

        if ($return !== null && $return->isExecuted()) {
            $this->addFlash('error', 'Ese contenedor ya tiene registrada su devolución, no se puede reprogramar.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $delivery = $this->transport->deliveryFor($container);

        if ($delivery === null || $delivery->getTransport() === null) {
            $this->addFlash('error', 'Asigna primero el transporte de este contenedor (Avisar al transporte) antes de programar la devolución de vacío.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $yard = $this->entityManager->getRepository(EmptyReturnYard::class)->find($r->request->get('yard'));
        $appointmentDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $r->request->get('appointmentDate'));
        $slipFile = $r->files->get('slip');

        if (!$yard || !$appointmentDate) {
            $this->addFlash('error', 'El patio y la fecha de la cita son obligatorios.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($return === null && !$slipFile) {
            $this->addFlash('error', 'Adjunta la papeleta del patio.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($return === null) {
            $return = new EmptyReturn();
            $return->setContainer($container);
            $import->addEmptyReturn($return);
            $this->entityManager->persist($return);
        }

        $return->setYard($yard);
        $return->setAppointmentDate($appointmentDate->setTime(0, 0));

        if ($slipFile && $slipFile->isValid()) {
            $route = 'uploads/papeletas/'.$import->getId();
            $folder = $this->uploadPath->resolve($route);

            if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
                $this->addFlash('error', 'No se pudo preparar la carpeta de la papeleta.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }

            $name = $slugger->slug($container->getNum()).'-'.uniqid().'.'.$slipFile->guessExtension();

            try {
                $slipFile->move($folder, $name);
                $return->setSlipRoute($route.'/'.$name);
            } catch (FileException) {
                $this->addFlash('error', 'No se pudo guardar la papeleta.');

                return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Devolución de %s programada para el %s en %s.', $container->getNum(), $appointmentDate->format('d/m/Y'), $yard->getName()));

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }

    /**
     * El EIR normalmente lo sube el transportista al registrar la
     * devolucion (ver DashboardDeliveries::registerEmptyReturn()), pero el
     * patio a veces lo emite despues: esto deja que el ejecutivo lo adjunte
     * o reemplace en cualquier momento, ya programada la devolucion.
     */
    #[IsGranted('ROLE_EXECUTIVE')]
    #[Route('/dashboard/pedimentos/expediente/{id}/vacios/{return}/eir/adjuntar', name: 'case_file_empty_return_eir_upload', requirements: ['id' => '\d+', 'return' => '\d+'], methods: ['POST'])]
    public function uploadEmptyReturnEir(#[MapEntity(id: 'id')] ImportRequest $import, #[MapEntity(id: 'return')] EmptyReturn $return, Request $r, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('case_file_empty_return_eir_upload', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        if ($return->getReference() !== $import) {
            $this->addFlash('error', 'Esa devolución no pertenece a este expediente.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $file = $r->files->get('eirFile');

        if (!$file || !$file->isValid()) {
            $this->addFlash('error', 'Selecciona el EIR escaneado.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $route = 'uploads/eir/'.$import->getId();
        $folder = $this->uploadPath->resolve($route);

        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            $this->addFlash('error', 'No se pudo preparar la carpeta del EIR.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $name = $slugger->slug($return->getContainer()->getNum()).'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($folder, $name);
        } catch (FileException) {
            $this->addFlash('error', 'No se pudo guardar el EIR.');

            return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
        }

        $oldPath = $return->getEirRoute() ? $this->uploadPath->resolve($return->getEirRoute()) : null;

        if ($oldPath && is_file($oldPath)) {
            unlink($oldPath);
        }

        $return->setEirRoute($route.'/'.$name);
        $this->entityManager->flush();

        $this->addFlash('success', 'EIR adjuntado.');

        return $this->redirectToRoute('case_file', ['id' => $import->getId()]);
    }
}
