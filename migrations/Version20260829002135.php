<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalogo de forwarders (agentes de carga) a los que puede venir consignado
 * un expediente, en vez de al cliente directo. Relacion nueva sin datos
 * previos que preservar, asi que down() no necesita guardas.
 */
final class Version20260829002135 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega el catálogo de forwarders y la relación opcional de import_request hacia su forwarder consignatario.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE forwarder (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, contact_emails JSON NOT NULL, bank_accounts JSON NOT NULL, bank_accounts_file_route VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD forwarder_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_28872673E4DF36A3 FOREIGN KEY (forwarder_id) REFERENCES forwarder (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_28872673E4DF36A3 ON import_request (forwarder_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP CONSTRAINT FK_28872673E4DF36A3
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE forwarder
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_28872673E4DF36A3
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP forwarder_id
        SQL);
    }
}
