<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enlaza un pedimento secundario con el expediente original del que se
 * genero (ver DashboardCaseFiles::createSecondary()) — la agencia lo usa
 * cuando una mercancia se tiene que declarar en mas de un pedimento.
 */
final class Version20260911232015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega import_request.origin_request_id para enlazar pedimentos secundarios con su expediente original.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD origin_request_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_28872673FBA5E2E8 FOREIGN KEY (origin_request_id) REFERENCES import_request (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_28872673FBA5E2E8 ON import_request (origin_request_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP FOREIGN KEY FK_28872673FBA5E2E8
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_28872673FBA5E2E8 ON import_request
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP origin_request_id
        SQL);
    }
}
