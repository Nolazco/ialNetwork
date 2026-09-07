<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un usuario transportista ya puede operar mas de una empresa de transporte
 * (cada una con su propia flota y despachos) — freight_hauler.id_user_id deja
 * de ser unico.
 */
final class Version20260901194817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relaja freight_hauler.id_user_id: un usuario transportista puede tener varias empresas de transporte.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_363bc36179f37ae5
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_363BC36179F37AE5 ON freight_hauler (id_user_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_363BC36179F37AE5
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_363bc36179f37ae5 ON freight_hauler (id_user_id)
        SQL);
    }
}
