<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pase PIS (captura de la cita del portal del recinto) por despacho: ver
 * Entity\Delivery::$pasePisRoute y DeliveryMailer, que lo incrusta en el
 * cuerpo del aviso de transporte.
 */
final class Version20260915171720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega delivery.pase_pis_route.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD pase_pis_route VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP pase_pis_route
        SQL);
    }
}
