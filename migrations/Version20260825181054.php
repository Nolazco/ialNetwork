<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convierte a tipos de fecha reales las columnas que se guardaban como texto.
 *
 * PostgreSQL no castea VARCHAR a DATE de forma implicita, por eso cada ALTER
 * lleva su clausula USING. Los valores existentes en import_request.eta ya
 * estan en formato ISO (YYYY-MM-DD), y delivery, empty_return y operation
 * estaban vacias al momento de escribir esta migracion.
 */
final class Version20260825181054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convierte eta, date y hour de VARCHAR a DATE/TIME';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ALTER eta TYPE DATE USING eta::date
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN import_request.eta IS '(DC2Type:date_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE operation ALTER date TYPE DATE USING date::date
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN operation.date IS '(DC2Type:date_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ALTER date TYPE DATE USING date::date
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ALTER hour TYPE TIME(0) WITHOUT TIME ZONE USING hour::time
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN delivery.date IS '(DC2Type:date_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN delivery.hour IS '(DC2Type:time_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER date TYPE DATE USING date::date
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN empty_return.date IS '(DC2Type:date_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ALTER eta TYPE VARCHAR(255) USING eta::text
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN import_request.eta IS NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE operation ALTER date TYPE VARCHAR(255) USING date::text
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN operation.date IS NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ALTER date TYPE VARCHAR(255) USING date::text
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ALTER hour TYPE VARCHAR(255) USING hour::text
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN delivery.date IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN delivery.hour IS NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER date TYPE VARCHAR(255) USING date::text
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN empty_return.date IS NULL
        SQL);
    }
}
