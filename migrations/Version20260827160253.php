<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827160253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'El cliente anticipa, al dar de alta la solicitud, si espera inspección y de que autoridad.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD expected_inspection_authority VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP expected_inspection_authority
        SQL);
    }
}
