<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalogo de empresas de custodia armada (App\Entity\Custodia) y la
 * referencia opcional desde el expediente (ImportRequest::$custodia) para el
 * caso en que la mercancia si la requiere.
 */
final class Version20260904195648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega el catalogo de custodias y la relacion opcional en import_request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE custodia (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, contact_emails JSON NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD custodia_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_2887267333C6874B FOREIGN KEY (custodia_id) REFERENCES custodia (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2887267333C6874B ON import_request (custodia_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP CONSTRAINT FK_2887267333C6874B
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_2887267333C6874B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP custodia_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE custodia
        SQL);
    }
}
