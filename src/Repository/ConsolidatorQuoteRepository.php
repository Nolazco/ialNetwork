<?php

namespace App\Repository;

use App\Entity\ConsolidatorQuote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsolidatorQuote>
 */
class ConsolidatorQuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsolidatorQuote::class);
    }
}
