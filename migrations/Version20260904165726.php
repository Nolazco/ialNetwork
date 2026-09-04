<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Alternativa a Delivery::$transport para transportistas que no estan en el
 * catalogo y no se van a registrar (ver Delivery::$unregisteredHaulerName /
 * $unregisteredHaulerEmails). Ambas columnas nulas: mientras una tenga valor
 * la otra se queda vacia, nunca las dos a la vez.
 */
final class Version20260904165726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega unregistered_hauler_name y unregistered_hauler_emails a delivery.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD unregistered_hauler_name VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD unregistered_hauler_emails JSON DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP unregistered_hauler_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP unregistered_hauler_emails
        SQL);
    }
}
