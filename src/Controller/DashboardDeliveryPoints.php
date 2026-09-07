<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\DeliveryPoint;
use App\Entity\User;
use App\Security\CompanyAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Puntos de entrega (almacenes) del catalogo propio de cada empresa — antes
 * solo se podian dar de alta al vuelo desde el formulario de nueva solicitud
 * (ver DashboardImports::newImport()), sin forma de editarlos despues ni de
 * prepararlos con calma antes de la primera solicitud.
 *
 * Es cosa del cliente (los suyos) y del administrador (los de cualquier
 * empresa) — a diferencia de Provider/Forwarder/Custodia, que son catalogos
 * de toda la agencia, este es un catalogo propio de cada cliente, asi que no
 * le corresponde al ejecutivo en general.
 */
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_CLIENT")'))]
class DashboardDeliveryPoints extends AbstractController
{
    use AjaxCsrfTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyAccess $companyAccess,
    ) {
    }

    #[Route('/dashboard/puntos-entrega', name: 'delivery_points_companies', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $companies = $this->isGranted('ROLE_ADMIN')
            ? $this->entityManager->getRepository(Company::class)->findAll()
            : $this->entityManager->getRepository(Company::class)->findAssociatedCompanies($user);

        return $this->render('/dashboard/deliveryPointsCompanies.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'companies' => $companies,
        ]);
    }

    #[Route('/dashboard/puntos-entrega/{rfc}', name: 'delivery_points', methods: ['GET'])]
    public function points(string $rfc): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $company = $this->entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);

        if (!$this->companyAccess->canAccess($company)) {
            throw $this->createAccessDeniedException('Esa empresa no está entre las tuyas.');
        }

        $points = $this->entityManager->getRepository(DeliveryPoint::class)->findBy(['company' => $company]);

        return $this->render('/dashboard/deliveryPoints.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'company' => $company,
            'points' => $points,
        ]);
    }

    #[Route('/dashboard/puntos-entrega/{rfc}/nuevo', name: 'delivery_point_create', methods: ['GET'])]
    public function createPoint(string $rfc): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $company = $this->entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);

        if (!$this->companyAccess->canAccess($company)) {
            throw $this->createAccessDeniedException('Esa empresa no está entre las tuyas.');
        }

        return $this->render('/dashboard/newDeliveryPoint.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'company' => $company,
        ]);
    }

    #[Route('/dashboard/puntos-entrega/{rfc}/new', name: 'delivery_point_new', methods: ['POST'])]
    public function newPoint(string $rfc, Request $r): Response
    {
        $company = $this->entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);

        if (!$this->companyAccess->canAccess($company)) {
            throw $this->createAccessDeniedException('Esa empresa no está entre las tuyas.');
        }

        if (!$this->isCsrfTokenValid('create_delivery_point', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('delivery_point_create', ['rfc' => $rfc]);
        }

        $name = trim((string) $r->request->get('name'));
        $address = trim((string) $r->request->get('address'));

        if ($name === '' || $address === '') {
            $this->addFlash('error', 'Nombre y dirección son obligatorios.');

            return $this->redirectToRoute('delivery_point_create', ['rfc' => $rfc]);
        }

        $point = new DeliveryPoint();
        $point->setCompany($company);
        $this->fillFromRequest($point, $r);

        $this->entityManager->persist($point);
        $this->entityManager->flush();

        $this->addFlash('success', 'Punto de entrega registrado correctamente.');

        return $this->redirectToRoute('delivery_points', ['rfc' => $rfc]);
    }

    #[Route('/dashboard/puntos-entrega/{rfc}/{id}/editar', name: 'delivery_point_edit_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function editPointForm(string $rfc, #[MapEntity(id: 'id')] DeliveryPoint $point): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->companyAccess->canAccess($point->getCompany()) || $point->getCompany()->getRfc() !== $rfc) {
            throw $this->createAccessDeniedException('Ese punto de entrega no está entre los tuyos.');
        }

        return $this->render('/dashboard/editDeliveryPoint.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'company' => $point->getCompany(),
            'point' => $point,
        ]);
    }

    #[Route('/dashboard/puntos-entrega/{rfc}/{id}/editar', name: 'delivery_point_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editPoint(string $rfc, #[MapEntity(id: 'id')] DeliveryPoint $point, Request $r): Response
    {
        if (!$this->companyAccess->canAccess($point->getCompany()) || $point->getCompany()->getRfc() !== $rfc) {
            throw $this->createAccessDeniedException('Ese punto de entrega no está entre los tuyos.');
        }

        if (!$this->isCsrfTokenValid('edit_delivery_point', $r->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido, intenta de nuevo.');

            return $this->redirectToRoute('delivery_point_edit_form', ['rfc' => $rfc, 'id' => $point->getId()]);
        }

        $name = trim((string) $r->request->get('name'));
        $address = trim((string) $r->request->get('address'));

        if ($name === '' || $address === '') {
            $this->addFlash('error', 'Nombre y dirección son obligatorios.');

            return $this->redirectToRoute('delivery_point_edit_form', ['rfc' => $rfc, 'id' => $point->getId()]);
        }

        $this->fillFromRequest($point, $r);

        $this->entityManager->flush();

        $this->addFlash('success', 'Punto de entrega actualizado correctamente.');

        return $this->redirectToRoute('delivery_points', ['rfc' => $rfc]);
    }

    private function fillFromRequest(DeliveryPoint $point, Request $r): void
    {
        $point->setName(trim((string) $r->request->get('name')));
        $point->setAddress(trim((string) $r->request->get('address')));
        $point->setRfc($this->nullableTrim($r->request->get('rfc')));
        $point->setStreet($this->nullableTrim($r->request->get('street')));
        $point->setExtNumber($this->nullableTrim($r->request->get('extNumber')));
        $point->setIntNumber($this->nullableTrim($r->request->get('intNumber')));
        $point->setNeighborhood($this->nullableTrim($r->request->get('neighborhood')));
        $point->setLocality($this->nullableTrim($r->request->get('locality')));
        $point->setMunicipality($this->nullableTrim($r->request->get('municipality')));
        $point->setState($this->nullableTrim($r->request->get('state')));
        $point->setCountry($this->nullableTrim($r->request->get('country')) ?? 'MEXICO');
        $point->setZipCode($this->nullableTrim($r->request->get('zipCode')));
        $point->setContactName($this->nullableTrim($r->request->get('contactName')));
        $point->setContactPhone($this->nullableTrim($r->request->get('contactPhone')));
        $point->setContactEmail($this->nullableTrim($r->request->get('contactEmail')));
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
