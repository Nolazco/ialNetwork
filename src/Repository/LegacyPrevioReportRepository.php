<?php

namespace App\Repository;

use App\Entity\LegacyPrevioReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegacyPrevioReport>
 */
class LegacyPrevioReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegacyPrevioReport::class);
    }
}
