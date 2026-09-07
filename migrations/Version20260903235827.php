<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuándo el SOIA reportó "Reconocimiento aduanero" por primera vez para un
 * expediente (ImportRequest::$reconocimientoAt, ver
 * SoiaResult::isUnderInspection()). Null en todos los expedientes existentes:
 * ninguno se ha marcado así todavía, y no hay forma de reconstruirlo con
 * datos históricos.
 */
final class Version20260903235827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega reconocimiento_at a import_request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD reconocimiento_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN import_request.reconocimiento_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP reconocimiento_at
        SQL);
    }
}
