<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827175607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reportes de previo/inspección ligados al expediente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE previo_report (id SERIAL NOT NULL, reference_id INT NOT NULL, created_by_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, authority VARCHAR(255) DEFAULT NULL, place VARCHAR(255) NOT NULL, date DATE NOT NULL, start_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL, end_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL, container_num VARCHAR(255) DEFAULT NULL, seal_origin VARCHAR(255) DEFAULT NULL, seal_final VARCHAR(255) DEFAULT NULL, plates VARCHAR(255) DEFAULT NULL, transport_company_name VARCHAR(255) DEFAULT NULL, goods JSON NOT NULL, lots JSON NOT NULL, presentation VARCHAR(255) DEFAULT NULL, quantity VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, pdf_route VARCHAR(255) NOT NULL, photos_zip_route VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FE5E20591645DEA9 ON previo_report (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FE5E2059B03A8386 ON previo_report (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN previo_report.date IS '(DC2Type:date_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN previo_report.start_time IS '(DC2Type:time_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN previo_report.end_time IS '(DC2Type:time_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN previo_report.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE previo_report ADD CONSTRAINT FK_FE5E20591645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE previo_report ADD CONSTRAINT FK_FE5E2059B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE previo_report DROP CONSTRAINT FK_FE5E20591645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE previo_report DROP CONSTRAINT FK_FE5E2059B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE previo_report
        SQL);
    }
}
