<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La fecha de arribo que captura el cliente suele ser un estimado: se agrega
 * una bandera para marcarla como definitiva. Columna nueva con default, sin
 * backfill que perder al revertir.
 */
final class Version20260831184710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega eta_confirmed a import_request, para marcar la fecha de arribo como definitiva.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD eta_confirmed BOOLEAN DEFAULT false NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP eta_confirmed
        SQL);
    }
}
