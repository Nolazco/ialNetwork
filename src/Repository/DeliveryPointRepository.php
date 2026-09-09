<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\DeliveryPoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeliveryPoint>
 */
class DeliveryPointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryPoint::class);
    }

    /**
     * Puntos que la empresa comparte, sola o con otras (ver
     * DeliveryPoint::$companies) — reemplaza el antiguo findBy(['company' =>
     * ...]), que ya no aplica al dejar de ser una relacion ManyToOne.
     *
     * @return list<DeliveryPoint>
     */
    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('dp')
            ->innerJoin('dp.companies', 'c')
            ->andWhere('c = :company')
            ->setParameter('company', $company)
            ->orderBy('dp.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
