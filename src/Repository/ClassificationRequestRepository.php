<?php

namespace App\Repository;

use App\Entity\ClassificationRequest;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassificationRequest>
 */
class ClassificationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassificationRequest::class);
    }

    /**
     * Busca mercancía ya clasificada por nombre comercial, nombre químico,
     * número de CAS o la fracción arancelaria que ya se le confirmó — es lo
     * que deja ver, antes de mandar una solicitud nueva, si ese producto (o
     * uno con el mismo CAS) ya se clasificó antes.
     *
     * $companies filtra a las empresas indicadas (para el cliente, que solo
     * debe ver las suyas); null significa sin filtro de empresa (para el
     * ejecutivo, ya que la fracción depende del producto, no de quién lo
     * importa, y puede repetirse entre distintos clientes).
     *
     * @param list<Company>|null $companies
     *
     * @return list<ClassificationRequest>
     */
    public function search(?string $term, ?array $companies = null, int $limit = 50): array
    {
        if ($companies === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($companies !== null) {
            $qb->andWhere('c.company IN (:companies)')
                ->setParameter('companies', $companies);
        }

        if ($term !== null && $term !== '') {
            $qb->andWhere('c.merchandiseName LIKE :term OR c.chemicalName LIKE :term OR c.casNumber LIKE :term OR c.confirmedTariffFraction LIKE :term')
                ->setParameter('term', '%'.$term.'%');
        }

        return $qb->getQuery()->getResult();
    }
}
