<?php

namespace App\Controller;

use App\Entity\Custodia;
use App\Entity\User;
use App\Workflow\EmailListParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalogo interno de empresas de custodia armada: ni clientes ni
 * transportistas tienen nada que hacer aqui. Ver DeliveryMailer para donde
 * se usa (se agrega en copia al avisar al transporte).
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardCustodias extends AbstractController
{
    use AjaxCsrfTrait;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/dashboard/custodias')]
    public function custodias(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $custodias = $this->entityManager->getRepository(Custodia::class)->findAll();

        return $this->render('/dashboard/custodias.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'custodias' => $custodias,
        ]);
    }

    #[Route('/dashboard/custodias/nueva')]
    public function createCustodia(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/newcustodia.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
        ]);
    }

    #[Route('/dashboard/custodias/new', methods: ['POST'])]
    public function newCustodia(Request $r): Response
    {
        if (!$this->isCsrfTokenValid('create_custodia', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirect('/dashboard/custodias/nueva');
        }

        $name = trim((string) $r->request->get('name'));

        if ($name === '') {
            $this->addFlash('error', 'El nombre de la custodia es obligatorio.');

            return $this->redirect('/dashboard/custodias/nueva');
        }

        $custodia = new Custodia();
        $custodia->setName($name);
        $custodia->setContactEmails(EmailListParser::parse((string) $r->request->get('contactEmails')));

        $this->entityManager->persist($custodia);
        $this->entityManager->flush();

        $this->addFlash('success', 'Custodia registrada correctamente.');

        return $this->redirect('/dashboard/custodias');
    }

    #[Route('/dashboard/custodias/{id}/editar', methods: ['POST'])]
    public function editCustodia(int $id, Request $r): JsonResponse
    {
        if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
            return $csrf;
        }

        $custodia = $this->entityManager->getRepository(Custodia::class)->find($id);

        if (!$custodia) {
            return new JsonResponse(['success' => false, 'message' => 'Custodia no encontrada.'], 404);
        }

        $data = json_decode($r->getContent(), true);

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return new JsonResponse(['success' => false, 'message' => 'El nombre es obligatorio.'], 400);
        }

        $custodia->setName($name);
        $custodia->setContactEmails(EmailListParser::parse((string) ($data['contactEmails'] ?? '')));

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'contactEmails' => $custodia->getContactEmails(),
        ]);
    }
}
