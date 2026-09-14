<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cambio de paradigma en los avisos de expediente: de "toda la empresa" a
 * "solo quien dio de alta la solicitud" (ver ImportRequest::$createdBy y
 * User::$whatsapp, resueltos en RecipientResolver::clientEmails()/
 * clientWhatsapp()). Ambas columnas nullable: los expedientes ya existentes
 * no tienen creador registrado y caen de vuelta a la lista de la empresa.
 */
final class Version20260914200027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega import_request.created_by_id y user.whatsapp.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD created_by_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_28872673B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_28872673B03A8386 ON import_request (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD whatsapp VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP whatsapp
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP FOREIGN KEY FK_28872673B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_28872673B03A8386 ON import_request
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP created_by_id
        SQL);
    }
}
