<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nueva clave de NotificationRecipients para NewUserMailer: a quién avisar
 * en cuanto alguien se registra (ver UserManagement::create()). Se siembra
 * con el correo real de la agencia; se puede ampliar despues desde /admin
 * sin tocar código.
 */
final class Version20260902184753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega la clave new_user_to a notification_recipients, para avisar por correo cuando alguien se registra.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipients (key, label, emails, required) VALUES
            ('new_user_to', 'Nuevos usuarios registrados (destinatario)', '["gerencia@ialnetwork.com"]', true)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM notification_recipients WHERE key = 'new_user_to'
        SQL);
    }
}
