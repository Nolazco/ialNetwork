<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aduana por la que se despacha cada expediente (ImportRequest::$aduana,
 * ver App\Workflow\AduanaCatalog). El default '16' (Manzanillo) hace que
 * los expedientes ya existentes queden marcados con la aduana que la
 * agencia manejaba hasta ahora, sin necesidad de un backfill aparte.
 */
final class Version20260903224806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega aduana a import_request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD aduana VARCHAR(8) DEFAULT '16' NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP aduana
        SQL);
    }
}
