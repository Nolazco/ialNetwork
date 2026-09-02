<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ModuladoMailer no tenia ninguna lista fija (a diferencia de
 * ClassificationMailer/PrevioReportMailer/ConsolidatorMailer): to/cc eran
 * 100% dinamicos (cliente afiliado + ejecutivos). Se agregan dos listas
 * fijas mas, opcionales, que se mezclan con eso sin quitarlo — nacen vacias
 * y no requeridas porque nunca existio nada que preservar.
 */
final class Version20260901162723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega modulado_to y modulado_cc a notification_recipients, para las alertas de modulación.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipients (key, label, emails, required) VALUES
            ('modulado_to', 'Alertas de modulación (destinatario adicional)', '[]', false),
            ('modulado_cc', 'Alertas de modulación (copia adicional)', '[]', false)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM notification_recipients WHERE key IN ('modulado_to', 'modulado_cc')
        SQL);
    }
}
