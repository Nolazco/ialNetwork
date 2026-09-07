<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\ImportRequest;
use App\Workflow\ImportRequestWorkflow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportRequest>
 */
class ImportRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportRequest::class);
    }

    /**
     * Expedientes que todavia no llegan a "Finalizado" — el universo sobre el
     * que tiene sentido calcular la cola de atencion y el avance por etapa en
     * el Inicio del ejecutivo: uno finalizado ya no le pide nada a nadie.
     *
     * @return list<ImportRequest>
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.status != :finished')
            ->setParameter('finished', ImportRequestWorkflow::FINISHED)
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Expedientes activos de las empresas indicadas, para el Inicio del
     * cliente (que solo ve las suyas, ver CompanyRepository::findAssociatedCompanies()).
     *
     * @param list<Company> $companies
     *
     * @return list<ImportRequest>
     */
    public function findActiveForCompanies(array $companies): array
    {
        if ($companies === []) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->where('i.idCompany IN (:companies)')
            ->andWhere('i.status != :finished')
            ->setParameter('companies', $companies)
            ->setParameter('finished', ImportRequestWorkflow::FINISHED)
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return ImportRequest[] Returns an array of ImportRequest objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ImportRequest
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
