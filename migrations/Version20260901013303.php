<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Las listas de correos fijas de cada notificación (antes `public const` en
 * cada Mailer) pasan a vivir aqui, editables desde /admin. Se siembran con
 * los valores reales de hoy para no cambiar ningun comportamiento: las 4
 * claves que ya existian como constante mantienen sus direcciones, las 2
 * nuevas (los "cc" que antes no existian) nacen vacias y no-requeridas.
 */
final class Version20260901013303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega notification_recipients, con las listas de correos fijas de cada notificación editables desde /admin.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_recipients (
              id SERIAL NOT NULL,
              key VARCHAR(255) NOT NULL,
              label VARCHAR(255) NOT NULL,
              emails JSON NOT NULL,
              required BOOLEAN NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_EF1497E68A90ABA9 ON notification_recipients (key)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipients (key, label, emails, required) VALUES
            ('classification_to', 'Clasificaciones (destinatario)', '["maria.santiago@vca.mx","mcamacho@valxglobalservices.com","ing.bueno@ialnetwork.com","zyf1967_2025@outlook.com"]', true),
            ('classification_cc', 'Clasificaciones (copia adicional)', '[]', false),
            ('previo_to', 'Reportes de previo (destinatario)', '["maria.santiago@vca.mx","mcamacho@valxglobalservices.com"]', true),
            ('previo_cc', 'Reportes de previo (copia)', '["carlos.nolazco@vca.mx","adair.fernandez@vca.mx","aux.trafico2@vca.mx"]', true),
            ('consolidator_to', 'Instrucciones al consolidador de carga (XCF)', '["esther.lemus@xcf.com.mx","cotizadormzn@xcf.com.mx","customermzn02@xcf.com.mx"]', true),
            ('consolidator_cc', 'Instrucciones al consolidador de carga (copia adicional)', '[]', false)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE notification_recipients
        SQL);
    }
}
