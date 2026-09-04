<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aduana nueva: Guadalajara, Jalisco (AduanaCatalog::GUADALAJARA). Igual que
 * las otras 4, nace vacia y no requerida: se llena desde /admin.
 */
final class Version20260904002749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega a notification_recipients la lista to/cc de la aduana de Guadalajara.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipients (key, label, emails, required) VALUES
            ('aduana_guadalajara_to', 'Aduana Guadalajara, Jalisco — solicitudes nuevas (destinatario)', '[]', false),
            ('aduana_guadalajara_cc', 'Aduana Guadalajara, Jalisco — solicitudes nuevas (copia)', '[]', false)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM notification_recipients WHERE key IN ('aduana_guadalajara_to', 'aduana_guadalajara_cc')
        SQL);
    }
}
