<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El cliente ahora puede avisar, desde la alta de la solicitud (o despues),
 * si la mercancia viajara con el consolidador de carga (XCF) — mientras sea
 * true y no se le hayan mandado instrucciones, no se puede avisar al
 * transporte (ver ImportRequestWorkflow::canAssignTransport()).
 */
final class Version20260901174224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega import_request.travels_with_consolidator (default false).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD travels_with_consolidator BOOLEAN DEFAULT false NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP travels_with_consolidator
        SQL);
    }
}
