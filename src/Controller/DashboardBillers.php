<?php

namespace App\Controller;

use App\Entity\Biller;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalogo interno de razones sociales a las que se puede facturar un
 * movimiento cuando no se factura al cliente directo: ni clientes ni
 * transportistas tienen nada que hacer aqui. Ver DeliveryMailer para donde
 * se usa (aparece en el aviso al transporte).
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardBillers extends AbstractController
{
    use AjaxCsrfTrait;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/dashboard/facturadores')]
    public function billers(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $billers = $this->entityManager->getRepository(Biller::class)->findAll();

        return $this->render('/dashboard/billers.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'billers' => $billers,
        ]);
    }

    #[Route('/dashboard/facturadores/nuevo')]
    public function createBiller(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('/dashboard/newbiller.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
        ]);
    }

    #[Route('/dashboard/facturadores/new', methods: ['POST'])]
    public function newBiller(Request $r): Response
    {
        if (!$this->isCsrfTokenValid('create_biller', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirect('/dashboard/facturadores/nuevo');
        }

        $name = trim((string) $r->request->get('name'));
        $address = trim((string) $r->request->get('address'));
        $rfc = trim((string) $r->request->get('rfc'));

        if ($name === '' || $address === '' || $rfc === '') {
            $this->addFlash('error', 'Razón social, domicilio y RFC son obligatorios.');

            return $this->redirect('/dashboard/facturadores/nuevo');
        }

        $biller = new Biller();
        $biller->setName($name);
        $biller->setAddress($address);
        $biller->setRfc($rfc);

        $this->entityManager->persist($biller);
        $this->entityManager->flush();

        $this->addFlash('success', 'Facturador registrado correctamente.');

        return $this->redirect('/dashboard/facturadores');
    }

    #[Route('/dashboard/facturadores/{id}/editar', methods: ['POST'])]
    public function editBiller(int $id, Request $r): JsonResponse
    {
        if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
            return $csrf;
        }

        $biller = $this->entityManager->getRepository(Biller::class)->find($id);

        if (!$biller) {
            return new JsonResponse(['success' => false, 'message' => 'Facturador no encontrado.'], 404);
        }

        $data = json_decode($r->getContent(), true);

        $name = trim((string) ($data['name'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $rfc = trim((string) ($data['rfc'] ?? ''));

        if ($name === '' || $address === '' || $rfc === '') {
            return new JsonResponse(['success' => false, 'message' => 'Razón social, domicilio y RFC son obligatorios.'], 400);
        }

        $biller->setName($name);
        $biller->setAddress($address);
        $biller->setRfc($rfc);

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'name' => $biller->getName(),
            'address' => $biller->getAddress(),
            'rfc' => $biller->getRfc(),
        ]);
    }
}
