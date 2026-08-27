<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827154608 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'El recinto ya no lo elige el cliente al crear la solicitud; ahora lo asigna el ejecutivo despues.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ALTER cr_id DROP NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ALTER cr_id SET NOT NULL
        SQL);
    }
}
