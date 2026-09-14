<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega el tipo de mercancia (catalogo de XCF, ver MerchandiseTypeCatalog)
 * tambien a las instrucciones reales, no solo a la cotizacion. Nullable:
 * las instrucciones ya mandadas antes de este campo no lo capturaron.
 */
final class Version20260914164029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega merchandise_type a consolidator_instruction.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction ADD merchandise_type VARCHAR(2) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP merchandise_type
        SQL);
    }
}
