<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260828191052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Un despacho puede marcarse como fallido (no se pudo realizar la carga), con motivo y quién lo reportó.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD failure_reported_by_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD failed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD failure_reason VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN delivery.failed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10EAB36B85 FOREIGN KEY (failure_reported_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3781EC10EAB36B85 ON delivery (failure_reported_by_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10EAB36B85
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_3781EC10EAB36B85
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP failure_reported_by_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP failed_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP failure_reason
        SQL);
    }
}
