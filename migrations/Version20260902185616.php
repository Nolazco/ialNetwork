<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correos de contacto adicionales de una empresa de transporte
 * (FreightHauler::$contactEmails): el aviso de transporte (DeliveryMailer)
 * los notifica junto con el correo del dueño de la cuenta, que siempre lo
 * recibe. Nullable a propósito: la mayoría de los transportistas no
 * necesita ninguno adicional.
 */
final class Version20260902185616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega contact_emails a freight_hauler, para el aviso de transporte.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE freight_hauler ADD contact_emails JSON DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE freight_hauler DROP contact_emails
        SQL);
    }
}
