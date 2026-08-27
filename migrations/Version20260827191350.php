<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827191350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Solicitudes de clasificación de mercancía y el contacto de clasificación opcional por empresa.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE classification_request (id SERIAL NOT NULL, company_id INT NOT NULL, requested_by_id INT NOT NULL, merchandise_name VARCHAR(255) NOT NULL, chemical_name VARCHAR(255) DEFAULT NULL, cas_number VARCHAR(255) DEFAULT NULL, merchandise_use VARCHAR(255) NOT NULL, presentation VARCHAR(255) NOT NULL, attachments JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C8C407CD979B1AD6 ON classification_request (company_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C8C407CD4DA1E751 ON classification_request (requested_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN classification_request.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request ADD CONSTRAINT FK_C8C407CD979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request ADD CONSTRAINT FK_C8C407CD4DA1E751 FOREIGN KEY (requested_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD classification_contact_email VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request DROP CONSTRAINT FK_C8C407CD979B1AD6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request DROP CONSTRAINT FK_C8C407CD4DA1E751
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE classification_request
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP classification_contact_email
        SQL);
    }
}
