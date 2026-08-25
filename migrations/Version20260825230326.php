<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Registra los pasos opcionales por los que el expediente si paso.
 *
 * El estatus solo dice donde esta ahora. Sin esta columna, una inspeccion fuera
 * de puerto omitida se veia identica a una realizada en cuanto el expediente
 * avanzaba, y la linea de tiempo la marcaba como completada.
 *
 * Se agrega con valor por defecto para que corra sobre una base que ya tenga
 * expedientes; los existentes quedan sin pasos opcionales registrados.
 */
final class Version20260825230326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega import_request.optional_steps_taken';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD optional_steps_taken JSON DEFAULT '[]' NOT NULL
        SQL);
        // El valor por defecto solo sirve para rellenar las filas existentes; se
        // retira para que el esquema coincida con el mapeo, que no declara uno.
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ALTER optional_steps_taken DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP optional_steps_taken
        SQL);
    }
}
