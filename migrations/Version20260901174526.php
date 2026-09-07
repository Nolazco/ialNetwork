<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * XCF necesita saber, en el correo de instrucciones, que transportista se
 * va a presentar a recoger la mercancia con el folio que ellos generan.
 */
final class Version20260901174526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega consolidator_instruction.transport_id (FreightHauler, obligatorio).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction ADD transport_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction ADD CONSTRAINT FK_A8BF2509909C13F FOREIGN KEY (transport_id) REFERENCES freight_hauler (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A8BF2509909C13F ON consolidator_instruction (transport_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP CONSTRAINT FK_A8BF2509909C13F
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_A8BF2509909C13F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP transport_id
        SQL);
    }
}
