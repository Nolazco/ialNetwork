<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega el sentido de la operacion (importacion o exportacion) al expediente.
 *
 * Hasta ahora solo se distinguia contenedor de carga suelta, pero la secuencia
 * de estados depende de ambos ejes: son cuatro recorridos distintos
 * (App\Workflow\ImportRequestWorkflow).
 *
 * La columna se agrega nullable y se rellena antes de marcarla NOT NULL, para
 * que la migracion tambien corra sobre una base que ya tenga expedientes. Los
 * existentes se dan por importacion, que es lo unico que la aplicacion permitia
 * capturar hasta ahora.
 */
final class Version20260825194435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega import_request.direction (importación / exportación)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD direction VARCHAR(16) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE import_request SET direction = 'import' WHERE direction IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ALTER direction SET NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP direction
        SQL);
    }
}
