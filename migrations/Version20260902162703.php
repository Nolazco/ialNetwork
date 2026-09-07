<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega a ClassificationRequest la fracción arancelaria que el equipo de
 * clasificadores confirma por su propio correo. Hasta ahora ese resultado no
 * quedaba registrado en ningún lado; con estas columnas el ejecutivo puede
 * capturarlo y, de paso, se puede buscar mercancía ya clasificada antes de
 * mandar una solicitud nueva.
 */
final class Version20260902162703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega confirmed_tariff_fraction, confirmed_by_id y confirmed_at a classification_request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request ADD confirmed_by_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request ADD confirmed_tariff_fraction VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request ADD confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN classification_request.confirmed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request ADD CONSTRAINT FK_C8C407CD6F45385D FOREIGN KEY (confirmed_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C8C407CD6F45385D ON classification_request (confirmed_by_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request DROP CONSTRAINT FK_C8C407CD6F45385D
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_C8C407CD6F45385D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request DROP confirmed_by_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request DROP confirmed_tariff_fraction
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request DROP confirmed_at
        SQL);
    }
}
