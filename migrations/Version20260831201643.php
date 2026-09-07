<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Programacion de la devolucion de vacios: el ejecutivo asigna patio, fecha
 * de cita y papeleta antes de que el transportista devuelva el contenedor,
 * asi que el registro de EmptyReturn ahora se crea desde la programacion,
 * no hasta la devolucion real. Todo nuevo y nullable donde aplica (incluidos
 * los campos que antes eran obligatorios: se llenan hasta la devolucion
 * real): sin backfill de datos, down() no necesita guardas.
 */
final class Version20260831201643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega appointment_date y slip_route a empty_return, y relaja transport/type/date/eir a nullable para poder crear el registro desde que se programa la cita.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD appointment_date DATE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD slip_route VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER transport_id DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER type DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER date DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER eir DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN empty_return.appointment_date IS '(DC2Type:date_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP appointment_date
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP slip_route
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER transport_id SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER type SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER date SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ALTER eir SET NOT NULL
        SQL);
    }
}
