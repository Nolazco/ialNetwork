<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El transportista debe dar el folio del CFDI junto con la unidad y el
 * chofer al asignar el despacho — la agencia lo necesita para adjuntarlo a
 * un documento propio.
 */
final class Version20260901232627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega delivery.cfdi_folio (UUID, 36 caracteres).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD cfdi_folio VARCHAR(36) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP cfdi_folio
        SQL);
    }
}
