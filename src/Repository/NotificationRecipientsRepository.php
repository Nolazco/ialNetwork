<?php

namespace App\Repository;

use App\Entity\NotificationRecipients;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationRecipients>
 */
class NotificationRecipientsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRecipients::class);
    }

    /**
     * @return list<string>
     */
    public function emailsFor(string $key): array
    {
        return $this->findOneBy(['key' => $key])?->getEmails() ?? [];
    }
}
