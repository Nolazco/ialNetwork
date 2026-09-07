<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827201307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Solicitudes de clasificación y reportes de previo entrados por los accesos públicos sin login (/legacy/*).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE legacy_classification_request (id SERIAL NOT NULL, requester_name VARCHAR(255) NOT NULL, requester_email VARCHAR(255) NOT NULL, company_name VARCHAR(255) NOT NULL, merchandise_name VARCHAR(255) NOT NULL, chemical_name VARCHAR(255) DEFAULT NULL, cas_number VARCHAR(255) DEFAULT NULL, merchandise_use VARCHAR(255) NOT NULL, presentation VARCHAR(255) NOT NULL, attachments JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legacy_classification_request.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE legacy_previo_report (id SERIAL NOT NULL, referencia VARCHAR(255) NOT NULL, cliente VARCHAR(255) NOT NULL, correo VARCHAR(255) NOT NULL, cargo_type VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, authority VARCHAR(255) DEFAULT NULL, place VARCHAR(255) NOT NULL, date DATE NOT NULL, start_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL, end_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL, container_num VARCHAR(255) DEFAULT NULL, seal_origin VARCHAR(255) DEFAULT NULL, seal_final VARCHAR(255) DEFAULT NULL, plates VARCHAR(255) DEFAULT NULL, transport_company_name VARCHAR(255) DEFAULT NULL, goods JSON NOT NULL, lots JSON NOT NULL, presentation VARCHAR(255) DEFAULT NULL, quantity VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, pdf_route VARCHAR(255) NOT NULL, photos_zip_route VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legacy_previo_report.date IS '(DC2Type:date_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legacy_previo_report.start_time IS '(DC2Type:time_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legacy_previo_report.end_time IS '(DC2Type:time_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN legacy_previo_report.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE legacy_classification_request
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE legacy_previo_report
        SQL);
    }
}
