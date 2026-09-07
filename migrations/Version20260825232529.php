<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Las afiliaciones ahora se autorizan.
 *
 * Afiliar a un cliente con una empresa es lo que le da acceso a sus
 * expedientes, documentos y cuentas de gastos, y hasta ahora el propio cliente
 * podia concederselo. La columna nace en "approved" para no revocar las
 * afiliaciones que ya existian; a partir de aqui las nuevas nacen pendientes.
 */
final class Version20260825232529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega associated.status (afiliación autorizada)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE associated ADD status VARCHAR(16) DEFAULT 'approved' NOT NULL
        SQL);
        // El valor por defecto solo rellena las filas existentes; se retira para
        // que el esquema coincida con el mapeo, que no declara uno.
        $this->addSql(<<<'SQL'
            ALTER TABLE associated ALTER status DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE associated DROP status
        SQL);
    }
}
