<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aduanas en las que trabaja cada usuario (solo aplica a ROLE_EXECUTIVE):
 * limita a que expedientes de que aduana le llegan las alertas (ver
 * User::$aduanas y RecipientResolver::executiveEmails()). Vacio significa
 * "todas", asi que los usuarios que ya existen no pierden ninguna alerta.
 */
final class Version20260907174340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega las aduanas asignadas a cada usuario.';
    }

    public function up(Schema $schema): void
    {
        // El DEFAULT es solo para rellenar las filas existentes: se quita
        // despues para que coincida con el mapeo (el default real, "[]", lo
        // pone la entidad en PHP al construir un User nuevo).
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD aduanas JSON NOT NULL DEFAULT '[]'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ALTER aduanas DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP aduanas
        SQL);
    }
}
