<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Numero de BL que XCF asigna cuando el cliente ya trae su propio documento
 * de instrucciones (hoy Sinbiotik, generado desde su propio portal) en vez
 * de que la agencia genere la hoja aqui — ver ConsolidatorInstruction::$xcfBlNumber.
 */
final class Version20260910005902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega el numero de BL de XCF cuando el cliente trae su propio documento de instrucciones.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction ADD xcf_bl_number VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP xcf_bl_number
        SQL);
    }
}
