<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rectificaciones de un pedimento ya pagado ante el SAT (ver
 * Entity\Rectification): pueden pasar en cualquier momento, no en un punto
 * fijo del roadmap, y un mismo expediente puede tener varias.
 */
final class Version20260912011111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega la tabla rectification para las rectificaciones de pedimento.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE rectification (id INT AUTO_INCREMENT NOT NULL, reference_id INT NOT NULL, created_by_id INT DEFAULT NULL, agency_reference VARCHAR(255) NOT NULL, import_number VARCHAR(255) NOT NULL, full_pedimento_route VARCHAR(255) NOT NULL, simplified_pedimento_route VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_6AD509211645DEA9 (reference_id), INDEX IDX_6AD50921B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rectification ADD CONSTRAINT FK_6AD509211645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rectification ADD CONSTRAINT FK_6AD50921B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE rectification DROP FOREIGN KEY FK_6AD509211645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rectification DROP FOREIGN KEY FK_6AD50921B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE rectification
        SQL);
    }
}
