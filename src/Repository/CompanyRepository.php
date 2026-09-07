<?php

namespace App\Repository;

use App\Entity\Associated;
use App\Entity\Company;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * Empresas que el cliente ya tiene aprobadas.
     *
     * Una afiliacion pendiente de autorizacion no aparece aqui: hasta que la
     * agencia la aprueba, el cliente no ve nada de esa empresa.
     */
    public function findAssociatedCompanies(User $usuario): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.associateds', 'a')
            ->innerJoin('a.idClient', 'u')
            ->where('u = :usuario')
            ->andWhere('a.status = :aprobada')
            ->setParameter('usuario', $usuario)
            ->setParameter('aprobada', Associated::APPROVED)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Company[] Returns an array of Company objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Company
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
