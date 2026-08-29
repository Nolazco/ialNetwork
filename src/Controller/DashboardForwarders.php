<?php

namespace App\Controller;

use App\Entity\Forwarder;
use App\Entity\User;
use App\Workflow\EmailListParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Catalogo interno de forwarders (agentes de carga): ni clientes ni
 * transportistas tienen nada que hacer aqui, y menos viendo o editando sus
 * cuentas bancarias — esa es informacion confidencial que jamas debe llegar
 * al propio forwarder ni a un cliente. Ver ForwarderMailer para la garantia
 * equivalente del lado de los correos automaticos.
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardForwarders extends AbstractController
{
    use AjaxCsrfTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/dashboard/forwarders')]
    public function forwarders(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $forwarders = $this->entityManager->getRepository(Forwarder::class)->findAll();

        return $this->render('/dashboard/forwarders.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'forwarders' => $forwarders,
        ]);
    }

    #[Route('/dashboard/forwarders/nuevo')]
    public function createForwarder(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/newforwarder.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
        ]);
    }

    #[Route('/dashboard/forwarders/new', methods: ['POST'])]
    public function newForwarder(Request $r): Response
    {
        if (!$this->isCsrfTokenValid('create_forwarder', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirect('/dashboard/forwarders/nuevo');
        }

        $name = trim((string) $r->request->get('name'));

        if ($name === '') {
            $this->addFlash('error', 'El nombre del forwarder es obligatorio.');

            return $this->redirect('/dashboard/forwarders/nuevo');
        }

        $emails = EmailListParser::parse((string) $r->request->get('contactEmails'));

        $forwarder = new Forwarder();
        $forwarder->setName($name);
        $forwarder->setContactEmails($emails);
        $forwarder->setBankAccounts([]);

        $this->entityManager->persist($forwarder);
        $this->entityManager->flush();

        $this->addFlash('success', 'Forwarder registrado correctamente.');

        return $this->redirect('/dashboard/forwarders');
    }

    #[Route('/dashboard/forwarders/{id}/editar', methods: ['POST'])]
    public function editForwarder(int $id, Request $r): JsonResponse
    {
        if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
            return $csrf;
        }

        $forwarder = $this->entityManager->getRepository(Forwarder::class)->find($id);

        if (!$forwarder) {
            return new JsonResponse(['success' => false, 'message' => 'Forwarder no encontrado.'], 404);
        }

        $data = json_decode($r->getContent(), true);

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return new JsonResponse(['success' => false, 'message' => 'El nombre es obligatorio.'], 400);
        }

        $forwarder->setName($name);
        $forwarder->setContactEmails(EmailListParser::parse((string) ($data['contactEmails'] ?? '')));

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'contactEmails' => $forwarder->getContactEmails(),
        ]);
    }

    /**
     * Pantalla dedicada de cuentas bancarias: separada del alta/edicion
     * rapida porque es informacion confidencial que solo unos pocos deberian
     * llegar a abrir, y porque el archivo adjunto no cabe en un modal.
     */
    #[Route('/dashboard/forwarders/{id}/cuentas', name: 'app_forwarder_bank_accounts', methods: ['GET'])]
    public function bankAccounts(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $forwarder = $this->entityManager->getRepository(Forwarder::class)->find($id);

        if (!$forwarder) {
            throw $this->createNotFoundException('Forwarder no encontrado.');
        }

        return $this->render('/dashboard/forwarderBankAccounts.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'forwarder' => $forwarder,
        ]);
    }

    #[Route('/dashboard/forwarders/{id}/cuentas', methods: ['POST'])]
    public function saveBankAccounts(int $id, Request $r, SluggerInterface $slugger): Response
    {
        $forwarder = $this->entityManager->getRepository(Forwarder::class)->find($id);

        if (!$forwarder) {
            throw $this->createNotFoundException('Forwarder no encontrado.');
        }

        if (!$this->isCsrfTokenValid('save_forwarder_bank_accounts', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('app_forwarder_bank_accounts', ['id' => $id]);
        }

        $banks = $r->request->all('bankName');
        $accounts = $r->request->all('accountNumber');
        $clabes = $r->request->all('clabe');
        $swifts = $r->request->all('swift');

        $bankAccounts = [];

        foreach ($banks as $index => $bank) {
            $bank = trim((string) $bank);
            $account = trim((string) ($accounts[$index] ?? ''));
            $clabe = trim((string) ($clabes[$index] ?? ''));
            $swift = trim((string) ($swifts[$index] ?? ''));

            // Fila totalmente vacia (renglon extra sin llenar): se ignora en
            // vez de guardar registros en blanco.
            if ($bank === '' && $account === '' && $clabe === '' && $swift === '') {
                continue;
            }

            $bankAccounts[] = [
                'bank' => $bank,
                'accountNumber' => $account,
                'clabe' => $clabe,
                'swift' => $swift,
            ];
        }

        $forwarder->setBankAccounts($bankAccounts);

        if ($route = $this->storeBankFile($r, $forwarder, $slugger)) {
            $forwarder->setBankAccountsFileRoute($route);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Cuentas bancarias actualizadas.');

        return $this->redirectToRoute('app_forwarder_bank_accounts', ['id' => $id]);
    }

    /**
     * Unica forma de descargar el archivo de cuentas bancarias: a diferencia
     * de los demas archivos de esta app (EIR, comprobantes, PDFs de previo),
     * este vive fuera de public/ y no tiene link estatico — solo se sirve
     * aqui, ya detras del guard ROLE_EXECUTIVE de la clase.
     */
    #[Route('/dashboard/forwarders/{id}/cuentas/archivo', name: 'app_forwarder_bank_file', methods: ['GET'])]
    public function downloadBankFile(int $id): BinaryFileResponse
    {
        $forwarder = $this->entityManager->getRepository(Forwarder::class)->find($id);
        $route = $forwarder?->getBankAccountsFileRoute();

        if (!$route || !is_file($route)) {
            throw $this->createNotFoundException('Ese forwarder no tiene un archivo de cuentas bancarias.');
        }

        $response = new BinaryFileResponse($route);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($route));

        return $response;
    }

    /**
     * Guarda el archivo de cuentas bancarias fuera de public/, para que no
     * quede alcanzable por una URL estatica adivinada. Devuelve null si no
     * venia archivo en esta peticion (se conserva el que ya hubiera).
     */
    private function storeBankFile(Request $r, Forwarder $forwarder, SluggerInterface $slugger): ?string
    {
        $file = $r->files->get('bankAccountsFile');

        if (!$file || !$file->isValid()) {
            return null;
        }

        $folder = $this->projectDir.'/var/forwarder_bank_files/'.$forwarder->getId();

        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            return null;
        }

        $name = $slugger->slug('cuentas-'.$forwarder->getName()).'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($folder, $name);
        } catch (FileException) {
            return null;
        }

        return $folder.'/'.$name;
    }
}
