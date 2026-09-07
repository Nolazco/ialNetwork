<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827183959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuenta los intentos automáticos de consulta al SOIA por expediente, para que el poller se rinda tras 100.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD soia_poll_attempts INT NOT NULL DEFAULT 0
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP soia_poll_attempts
        SQL);
    }
}
