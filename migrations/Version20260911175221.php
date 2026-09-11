<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega los avisos de WhatsApp al cliente final (Company::$whatsapp, ver
 * ModuladoConfirmer::clientMessage()) y la lista de WhatsApp de ejecutivos
 * (fila 'modulado_whatsapp' en notification_recipients, editable en /admin
 * igual que las listas de correos).
 *
 * De paso corrige el comentario de tipo de Doctrine en varias columnas de
 * fecha que quedaron sin el (DC2Type:...) — arrastrado desde que se restauro
 * un dump de produccion en local (ver scripts/refresh-local-db.sh): no
 * cambia ningun dato, solo evita que doctrine:schema:validate marque un
 * falso positivo cada vez que se use ese script.
 */
final class Version20260911175221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega Company.whatsapp y la lista de WhatsApp de ejecutivos en notification_recipients.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request CHANGE confirmed_at confirmed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction CHANGE delivery_date delivery_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request CHANGE reconocimiento_at reconocimiento_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE expires_at expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD whatsapp JSON NOT NULL DEFAULT '[]'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ALTER COLUMN whatsapp DROP DEFAULT
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE notification_recipients ADD phones JSON NOT NULL DEFAULT '[]'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification_recipients ALTER COLUMN phones DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipients (`key`, label, emails, phones, required) VALUES
            ('modulado_whatsapp', 'Números de WhatsApp de ejecutivos para avisos de modulado', '[]', '[]', 0)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM notification_recipients WHERE `key` = 'modulado_whatsapp'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification_recipients DROP phones
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP whatsapp
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request CHANGE reconocimiento_at reconocimiento_at DATETIME DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction CHANGE delivery_date delivery_date DATE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request CHANGE confirmed_at confirmed_at DATETIME DEFAULT NULL
        SQL);
    }
}
