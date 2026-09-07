<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Una lista de correos (to/cc) por aduana, para avisar a sus responsables en
 * cuanto un cliente da de alta una solicitud nueva (ver
 * NewImportRequestMailer). Nacen vacias y no requeridas: hay que llenarlas
 * desde /admin con los correos reales de cada aduana.
 */
final class Version20260904001554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega a notification_recipients una lista to/cc por aduana, para avisar de solicitudes nuevas.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipients (key, label, emails, required) VALUES
            ('aduana_manzanillo_to', 'Aduana Manzanillo, Colima — solicitudes nuevas (destinatario)', '[]', false),
            ('aduana_manzanillo_cc', 'Aduana Manzanillo, Colima — solicitudes nuevas (copia)', '[]', false),
            ('aduana_lazaro_cardenas_to', 'Aduana Lázaro Cárdenas, Michoacán — solicitudes nuevas (destinatario)', '[]', false),
            ('aduana_lazaro_cardenas_cc', 'Aduana Lázaro Cárdenas, Michoacán — solicitudes nuevas (copia)', '[]', false),
            ('aduana_veracruz_to', 'Aduana Veracruz — solicitudes nuevas (destinatario)', '[]', false),
            ('aduana_veracruz_cc', 'Aduana Veracruz — solicitudes nuevas (copia)', '[]', false),
            ('aduana_aicm_to', 'Aduana Aeropuerto Internacional de la CDMX — solicitudes nuevas (destinatario)', '[]', false),
            ('aduana_aicm_cc', 'Aduana Aeropuerto Internacional de la CDMX — solicitudes nuevas (copia)', '[]', false)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM notification_recipients WHERE key IN (
                'aduana_manzanillo_to', 'aduana_manzanillo_cc',
                'aduana_lazaro_cardenas_to', 'aduana_lazaro_cardenas_cc',
                'aduana_veracruz_to', 'aduana_veracruz_cc',
                'aduana_aicm_to', 'aduana_aicm_cc'
            )
        SQL);
    }
}
