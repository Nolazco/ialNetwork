<?php

namespace App\Controller;

use App\Entity\Associated;
use App\Entity\User;
use App\Notification\AffiliationStatusMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Autorizacion de las afiliaciones que solicitan los clientes.
 *
 * Afiliarse a una empresa da acceso a sus expedientes, sus documentos y sus
 * cuentas de gastos, asi que la decision es de la agencia, no del solicitante.
 */
#[IsGranted('ROLE_EXECUTIVE')]
class DashboardAffiliations extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/dashboard/afiliaciones', name: 'affiliations', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $repository = $this->entityManager->getRepository(Associated::class);

        return $this->render('/dashboard/affiliations.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'pending' => $repository->findBy(['status' => Associated::PENDING], ['id' => 'DESC']),
            'resolved' => $repository->findBy(
                ['status' => [Associated::APPROVED, Associated::REJECTED]],
                ['id' => 'DESC'],
                50
            ),
        ]);
    }

    #[Route('/dashboard/afiliaciones/{id}/{decision}', name: 'affiliation_decide', requirements: ['id' => '\d+', 'decision' => 'aprobar|rechazar'], methods: ['POST'])]
    public function decide(#[MapEntity(id: 'id')] Associated $association, string $decision, Request $r, AffiliationStatusMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('affiliation_decide', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('affiliations');
        }

        $association->setStatus($decision === 'aprobar' ? Associated::APPROVED : Associated::REJECTED);
        $this->entityManager->flush();
        $mailer->notify($association);

        $this->addFlash('success', sprintf(
            'Afiliación de %s a %s %s.',
            $association->getIdClient()->getEmail(),
            $association->getIdCompany()->getName(),
            $decision === 'aprobar' ? 'aprobada' : 'rechazada'
        ));

        return $this->redirectToRoute('affiliations');
    }

    /**
     * Quita una afiliación ya resuelta (normalmente aprobada) — para casos
     * como un ejecutivo que terminó vinculado a una empresa por haberla dado
     * de alta el mismo desde el flujo del cliente (ver
     * DashboardCompanies::newCompany(), que aprueba automáticamente al que
     * la crea). Solo el administrador la puede quitar: a diferencia de
     * aprobar/rechazar (parte del trabajo diario del ejecutivo), esto le
     * quita a alguien un acceso que ya tenía, así que pesa más.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/dashboard/afiliaciones/{id}/eliminar', name: 'affiliation_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(#[MapEntity(id: 'id')] Associated $association, Request $r): Response
    {
        if (!$this->isCsrfTokenValid('affiliation_delete', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('affiliations');
        }

        $client = $association->getIdClient()->getEmail();
        $company = $association->getIdCompany()->getName();

        $this->entityManager->remove($association);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Se quitó la afiliación de %s a %s.', $client, $company));

        return $this->redirectToRoute('affiliations');
    }
}
