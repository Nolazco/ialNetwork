<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El destinatario de una instrucción a XCF ahora puede ser el domicilio
 * fiscal de la empresa en vez de un punto de entrega del catálogo (mismo
 * patrón que ImportRequest::deliveryPoint) — null en delivery_point_id pasa
 * a significar justo eso.
 */
final class Version20260901164539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relaja consolidator_instruction.delivery_point_id a nullable: null significa domicilio fiscal de la empresa.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction ALTER delivery_point_id DROP NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction ALTER delivery_point_id SET NOT NULL
        SQL);
    }
}
