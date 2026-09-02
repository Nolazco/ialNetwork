<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fecha estimada de entrega en XCF (ConsolidatorInstruction::$deliveryDate):
 * nullable a propósito, se puede avisar la instrucción sin tener todavía la
 * cita agendada — es solo un estimado para que XCF se organice.
 */
final class Version20260902182037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega delivery_date (fecha estimada de entrega) a consolidator_instruction.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction ADD delivery_date DATE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN consolidator_instruction.delivery_date IS '(DC2Type:date_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP delivery_date
        SQL);
    }
}
