<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Al avisar al transporte tambien hay que mandarle la ficha de la mercancia
 * (clave SAT, descripcion, embalaje, bultos, peso, cubicaje), el pedimento
 * simplificado y, si aplica, el folio que XCF genero al recibir las
 * instrucciones — antes solo se agendaba transportista/fecha/hora.
 */
final class Version20260902004316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega la ficha de mercancia del aviso al transporte a delivery (clave_sat, descripcion, embalaje, bultos, weight_kg, cubicaje, pedimento_simplificado_route, xcf_folio).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD clave_sat VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD descripcion VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD embalaje VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD bultos INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD weight_kg DOUBLE PRECISION DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD cubicaje DOUBLE PRECISION DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD pedimento_simplificado_route VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD xcf_folio VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP clave_sat
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP descripcion
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP embalaje
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP bultos
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP weight_kg
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP cubicaje
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP pedimento_simplificado_route
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP xcf_folio
        SQL);
    }
}
