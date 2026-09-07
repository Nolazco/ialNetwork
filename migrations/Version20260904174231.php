<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aduana nueva: Aeropuerto Internacional Felipe Ángeles, AIFA
 * (AduanaCatalog::AIFA). Igual que las otras: nace vacia y no requerida, se
 * llena desde /admin.
 */
final class Version20260904174231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega a notification_recipients la lista to/cc de la aduana del AIFA.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipients (key, label, emails, required) VALUES
            ('aduana_aifa_to', 'Aduana AIFA — solicitudes nuevas (destinatario)', '[]', false),
            ('aduana_aifa_cc', 'Aduana AIFA — solicitudes nuevas (copia)', '[]', false)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM notification_recipients WHERE key IN ('aduana_aifa_to', 'aduana_aifa_cc')
        SQL);
    }
}
