<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalogo de facturadores (Biller) y el campo billTo en ImportRequest: a
 * quien se factura el movimiento cuando no se factura al cliente directo.
 */
final class Version20260905180327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega el catálogo de facturadores y el campo "a quién se factura" del expediente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE biller (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, rfc VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD bill_to_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_288726736C51FFB FOREIGN KEY (bill_to_id) REFERENCES biller (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_288726736C51FFB ON import_request (bill_to_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP CONSTRAINT FK_288726736C51FFB
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE biller
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_288726736C51FFB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP bill_to_id
        SQL);
    }
}
