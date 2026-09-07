<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827004853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Documentos que sube el ejecutivo, prueba de entrega opcional, transporte pendiente y seguimiento de la consulta SOIA.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE required_document (id SERIAL NOT NULL, reference_id INT NOT NULL, type VARCHAR(255) NOT NULL, route VARCHAR(255) DEFAULT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_432CA9E11645DEA9 ON required_document (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN required_document.uploaded_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE required_document ADD CONSTRAINT FK_432CA9E11645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD proof_route VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD proof_uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ALTER transport_id DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN delivery.proof_uploaded_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD modulado_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD last_soia_check_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN import_request.modulado_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN import_request.last_soia_check_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE required_document DROP CONSTRAINT FK_432CA9E11645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE required_document
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP proof_route
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP proof_uploaded_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ALTER transport_id SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP modulado_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP last_soia_check_at
        SQL);
    }
}
