<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El aviso al transporte ya no adjunta automaticamente el pedimento
 * simplificado ni el BL revalidado — el campo de subida manual en el
 * despacho ahora es generico ("maniobra"), renombrado al enviarse por
 * correo con el contenedor o "MANIOBRA CS ..." si es carga suelta (ver
 * DeliveryMailer).
 */
final class Version20260907183548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renombra delivery.pedimento_simplificado_route a maniobra_route.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery RENAME COLUMN pedimento_simplificado_route TO maniobra_route
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery RENAME COLUMN maniobra_route TO pedimento_simplificado_route
        SQL);
    }
}
