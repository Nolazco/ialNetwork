<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Una lista de correos (to/cc) por aduana para el aviso de transporte (ver
 * DeliveryMailer) — hasta ahora solo le llegaba al transportista y a la
 * custodia, sin nadie de la agencia en copia. Igual que las de "solicitudes
 * nuevas": nacen vacias y no requeridas, se llenan desde /admin.
 */
final class Version20260907234450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega a notification_recipients una lista to/cc por aduana para el aviso de transporte.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipients (key, label, emails, required) VALUES
            ('aduana_manzanillo_flete_to', 'Aduana Manzanillo, Colima — aviso de transporte (destinatario)', '[]', false),
            ('aduana_manzanillo_flete_cc', 'Aduana Manzanillo, Colima — aviso de transporte (copia)', '[]', false),
            ('aduana_lazaro_cardenas_flete_to', 'Aduana Lázaro Cárdenas, Michoacán — aviso de transporte (destinatario)', '[]', false),
            ('aduana_lazaro_cardenas_flete_cc', 'Aduana Lázaro Cárdenas, Michoacán — aviso de transporte (copia)', '[]', false),
            ('aduana_veracruz_flete_to', 'Aduana Veracruz — aviso de transporte (destinatario)', '[]', false),
            ('aduana_veracruz_flete_cc', 'Aduana Veracruz — aviso de transporte (copia)', '[]', false),
            ('aduana_aicm_flete_to', 'Aduana Aeropuerto Internacional de la CDMX — aviso de transporte (destinatario)', '[]', false),
            ('aduana_aicm_flete_cc', 'Aduana Aeropuerto Internacional de la CDMX — aviso de transporte (copia)', '[]', false),
            ('aduana_guadalajara_flete_to', 'Aduana Guadalajara, Jalisco — aviso de transporte (destinatario)', '[]', false),
            ('aduana_guadalajara_flete_cc', 'Aduana Guadalajara, Jalisco — aviso de transporte (copia)', '[]', false),
            ('aduana_aifa_flete_to', 'Aduana AIFA — aviso de transporte (destinatario)', '[]', false),
            ('aduana_aifa_flete_cc', 'Aduana AIFA — aviso de transporte (copia)', '[]', false)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM notification_recipients WHERE key IN (
                'aduana_manzanillo_flete_to', 'aduana_manzanillo_flete_cc',
                'aduana_lazaro_cardenas_flete_to', 'aduana_lazaro_cardenas_flete_cc',
                'aduana_veracruz_flete_to', 'aduana_veracruz_flete_cc',
                'aduana_aicm_flete_to', 'aduana_aicm_flete_cc',
                'aduana_guadalajara_flete_to', 'aduana_guadalajara_flete_cc',
                'aduana_aifa_flete_to', 'aduana_aifa_flete_cc'
            )
        SQL);
    }
}
