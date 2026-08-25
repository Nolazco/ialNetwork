<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Guarda la ruta del EIR escaneado que acompania a la devolucion del vacio.
 *
 * Nullable: el formulario pide el documento, pero la columna no debe impedir
 * registrar la devolucion si el escaneo llega despues.
 */
final class Version20260825222302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega empty_return.eir_route (EIR escaneado)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD eir_route VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP eir_route
        SQL);
    }
}
