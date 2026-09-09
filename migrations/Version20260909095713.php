<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correos de contacto de la empresa que se copian en las alertas de sus
 * expedientes sin necesidad de tener cuenta en el sistema (ver
 * Company::$alertContactEmails y RecipientResolver::clientEmails()). Vacio
 * significa "ninguno", asi que las empresas que ya existen no cambian nada.
 */
final class Version20260909095713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega los correos de contacto (sin cuenta) que se copian en las alertas de cada empresa.';
    }

    public function up(Schema $schema): void
    {
        // El DEFAULT es solo para rellenar las filas existentes: se quita
        // despues para que coincida con el mapeo (el default real, "[]", lo
        // pone la entidad en PHP al construir una Company nueva).
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD alert_contact_emails JSON NOT NULL DEFAULT '[]'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ALTER alert_contact_emails DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP alert_contact_emails
        SQL);
    }
}
