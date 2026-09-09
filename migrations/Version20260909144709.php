<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un despacho puede facturarse con mas de un CFDI (ej. flete dividido entre
 * varias facturas), asi que delivery.cfdi_folio (un solo folio) se vuelve
 * delivery.cfdi_folios (lista) — ver Delivery::$cfdiFolios.
 *
 * El folio existente de cada despacho se preserva como primer (y unico)
 * elemento de su lista antes de tirar la columna vieja.
 */
final class Version20260909144709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convierte delivery.cfdi_folio (un folio) en delivery.cfdi_folios (lista de folios).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD cfdi_folios JSON NOT NULL DEFAULT '[]'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE delivery SET cfdi_folios = json_build_array(cfdi_folio) WHERE cfdi_folio IS NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ALTER cfdi_folios DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP cfdi_folio
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD cfdi_folio VARCHAR(36) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE delivery SET cfdi_folio = cfdi_folios->>0 WHERE json_array_length(cfdi_folios) > 0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP cfdi_folios
        SQL);
    }
}
