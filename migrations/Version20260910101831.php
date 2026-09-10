<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Discriminacion pedida por el cliente sobre a quien se le manda cada
 * correo: de aqui en adelante cada empresa solo tiene dos listas,
 * "trafico" (alertas y todo lo del expediente, salvo el aviso al
 * transportista) y "compras" (solicitudes de clasificacion) — ya no
 * importa quien tenga cuenta aprobada en el portal (ver
 * RecipientResolver::traficoEmails()).
 *
 * company.alert_contact_emails se renombra a trafico sin perder los
 * correos que ya tuviera configurados. company.classification_contact_email
 * (un solo correo suelto) se convierte en compras (lista): el valor
 * existente, si habia, se envuelve en un arreglo de un elemento antes de
 * tirar la columna vieja.
 */
final class Version20260910101831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reemplaza classificationContactEmail/alertContactEmails de Company por las listas trafico/compras.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE company RENAME COLUMN alert_contact_emails TO trafico
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD compras JSON NOT NULL DEFAULT '[]'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE company SET compras = json_build_array(classification_contact_email) WHERE classification_contact_email IS NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ALTER compras DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP classification_contact_email
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE company RENAME COLUMN trafico TO alert_contact_emails
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD classification_contact_email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE company SET classification_contact_email = compras->>0 WHERE json_array_length(compras) > 0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP compras
        SQL);
    }
}
