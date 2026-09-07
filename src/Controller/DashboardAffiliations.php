<?php

namespace App\Controller;

use App\Entity\Associated;
use App\Entity\Company;
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
 * Afiliacion de clientes a empresas: quien tiene acceso a los expedientes,
 * documentos y cuentas de gastos de cada empresa.
 *
 * El cliente ya no puede afiliarse solo (ver DashboardCompanies, de donde se
 * quito ese flujo) — ahora la agencia crea la afiliacion directamente desde
 * aqui, o decide sobre alguna que haya quedado pendiente de un flujo previo.
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

        $clients = array_filter(
            $this->entityManager->getRepository(User::class)->findAll(),
            static fn (User $u): bool => in_array('ROLE_CLIENT', $u->getRoles(), true)
        );

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
            'clients' => $clients,
            'companies' => $this->entityManager->getRepository(Company::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    /**
     * El cliente ya no se afilia solo (ver DashboardCompanies): la agencia
     * crea la afiliacion directamente, ya aprobada — no tiene sentido pasar
     * por "pendiente" cuando es el propio ejecutivo/admin quien la esta dando
     * de alta.
     */
    #[Route('/dashboard/afiliaciones/nueva', name: 'affiliation_create', methods: ['POST'])]
    public function create(Request $r, AffiliationStatusMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('affiliation_create', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('affiliations');
        }

        $client = $this->entityManager->getRepository(User::class)->find($r->request->get('clientId'));

        if (!$client || !in_array('ROLE_CLIENT', $client->getRoles(), true)) {
            $this->addFlash('error', 'Selecciona un cliente válido.');

            return $this->redirectToRoute('affiliations');
        }

        $company = $this->entityManager->getRepository(Company::class)->find($r->request->get('companyId'));

        if (!$company) {
            $this->addFlash('error', 'Selecciona una empresa válida.');

            return $this->redirectToRoute('affiliations');
        }

        $existing = $this->entityManager->getRepository(Associated::class)->findOneBy([
            'idClient' => $client,
            'idCompany' => $company,
        ]);

        if ($existing) {
            $this->addFlash('error', sprintf(
                '%s ya tiene una afiliación (%s) a %s.',
                $client->getEmail(),
                $existing->getStatus(),
                $company->getName()
            ));

            return $this->redirectToRoute('affiliations');
        }

        $association = new Associated();
        $association->setIdClient($client);
        $association->setIdCompany($company);
        $association->setStatus(Associated::APPROVED);

        $this->entityManager->persist($association);
        $this->entityManager->flush();
        $mailer->notify($association);

        $this->addFlash('success', sprintf('Se afilió a %s con %s.', $client->getEmail(), $company->getName()));

        return $this->redirectToRoute('affiliations');
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
