<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cotizaciones a XCF (ver Entity\ConsolidatorQuote): se piden antes de
 * comprometerse con un transportista, a diferencia de consolidator_instruction.
 */
final class Version20260914163412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega la tabla consolidator_quote para las cotizaciones a XCF.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE consolidator_quote (id INT AUTO_INCREMENT NOT NULL, reference_id INT NOT NULL, delivery_point_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, descripcion VARCHAR(255) NOT NULL, clave_sat VARCHAR(255) NOT NULL, unidad VARCHAR(255) NOT NULL, quantity INT NOT NULL, weight_kg DOUBLE PRECISION NOT NULL, cubicaje DOUBLE PRECISION NOT NULL, merchandise_type VARCHAR(2) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_7BF8964D1645DEA9 (reference_id), INDEX IDX_7BF8964DA1492FCE (delivery_point_id), INDEX IDX_7BF8964DB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_quote ADD CONSTRAINT FK_7BF8964D1645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_quote ADD CONSTRAINT FK_7BF8964DA1492FCE FOREIGN KEY (delivery_point_id) REFERENCES delivery_point (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_quote ADD CONSTRAINT FK_7BF8964DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_quote DROP FOREIGN KEY FK_7BF8964D1645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_quote DROP FOREIGN KEY FK_7BF8964DA1492FCE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_quote DROP FOREIGN KEY FK_7BF8964DB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE consolidator_quote
        SQL);
    }
}
