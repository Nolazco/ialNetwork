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
 * Puntos de entrega (almacenes) del catalogo propio de cada cliente — antes
 * solo se podian dar de alta al vuelo desde el formulario de nueva solicitud
 * (ver DashboardImports::newImport()), sin forma de editarlos despues ni de
 * prepararlos con calma antes de la primera solicitud.
 *
 * A diferencia de Provider/Forwarder/Custodia (catalogos de toda la agencia),
 * este es un catalogo propio de cada cliente: el ejecutivo entra aqui para
 * ayudarle a mantener su catalogo, no porque el punto sea suyo.
 *
 * Un mismo punto puede compartirse entre varias Company del mismo cliente
 * (ver DeliveryPoint::$companies) — evita duplicar el registro cuando dos
 * empresas del cliente entregan en la misma bodega.
 */
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_EXECUTIVE") or is_granted("ROLE_CLIENT")'))]
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

        return $this->render('/dashboard/deliveryPointsCompanies.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'companies' => $this->accessibleCompanies($user),
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

        $points = $this->entityManager->getRepository(DeliveryPoint::class)->findByCompany($company);

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
            'otherCompanies' => $this->otherAccessibleCompanies($user, $company),
        ]);
    }

    #[Route('/dashboard/puntos-entrega/{rfc}/new', name: 'delivery_point_new', methods: ['POST'])]
    public function newPoint(string $rfc, Request $r): Response
    {
        /** @var User $user */
        $user = $this->getUser();

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
        $point->addCompany($company);
        $this->fillFromRequest($point, $r);
        $this->syncSharedCompanies($point, $this->otherAccessibleCompanies($user, $company), $r);

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

        $company = $this->entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);

        if (!$this->companyAccess->canAccess($company) || !$point->belongsTo($company)) {
            throw $this->createAccessDeniedException('Ese punto de entrega no está entre los tuyos.');
        }

        return $this->render('/dashboard/editDeliveryPoint.html.twig', [
            'name' => $user->getName(),
            'role' => $user->getRoles()[0],
            'loged' => 'true',
            'company' => $company,
            'point' => $point,
            'otherCompanies' => $this->otherAccessibleCompanies($user, $company),
        ]);
    }

    #[Route('/dashboard/puntos-entrega/{rfc}/{id}/editar', name: 'delivery_point_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editPoint(string $rfc, #[MapEntity(id: 'id')] DeliveryPoint $point, Request $r): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $company = $this->entityManager->getRepository(Company::class)->findOneBy(['rfc' => $rfc]);

        if (!$this->companyAccess->canAccess($company) || !$point->belongsTo($company)) {
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
        $this->syncSharedCompanies($point, $this->otherAccessibleCompanies($user, $company), $r);

        $this->entityManager->flush();

        $this->addFlash('success', 'Punto de entrega actualizado correctamente.');

        return $this->redirectToRoute('delivery_points', ['rfc' => $rfc]);
    }

    /**
     * Empresas que este usuario puede ver en el catalogo de puntos de
     * entrega: el admin/ejecutivo ven todas (entran a ayudarle al cliente,
     * ver clase docblock), el cliente solo las suyas aprobadas.
     *
     * @return list<Company>
     */
    private function accessibleCompanies(User $user): array
    {
        return $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_EXECUTIVE')
            ? $this->entityManager->getRepository(Company::class)->findAll()
            : $this->entityManager->getRepository(Company::class)->findAssociatedCompanies($user);
    }

    /**
     * Candidatas para compartir un punto ademas de $home: las mismas
     * accessibleCompanies() menos la empresa actual.
     *
     * @return list<Company>
     */
    private function otherAccessibleCompanies(User $user, Company $home): array
    {
        return array_values(array_filter(
            $this->accessibleCompanies($user),
            static fn (Company $c) => $c !== $home,
        ));
    }

    /**
     * Agrega o quita $point de cada empresa en $candidates segun venga
     * marcada en el formulario ("companies[]", por RFC) — nunca toca una
     * empresa fuera de $candidates, que ya viene acotada a lo que el usuario
     * puede ver, para que nadie comparta un punto con una empresa ajena.
     *
     * @param list<Company> $candidates
     */
    private function syncSharedCompanies(DeliveryPoint $point, array $candidates, Request $r): void
    {
        $selected = $r->request->all('companies');

        foreach ($candidates as $candidate) {
            if (in_array($candidate->getRfc(), $selected, true)) {
                $point->addCompany($candidate);
            } else {
                $point->removeCompany($candidate);
            }
        }
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
