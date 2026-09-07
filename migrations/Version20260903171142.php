<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Justificación completa que el clasificador manda junto con la fracción
 * (ClassificationRequest::$confirmedJustification) — el ejecutivo la pega
 * del correo del clasificador al confirmar. Se guarda ya saneada (ver
 * config/packages/html_sanitizer.yaml), lista para mostrarse tal cual.
 */
final class Version20260903171142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega confirmed_justification a classification_request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request ADD confirmed_justification TEXT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE classification_request DROP confirmed_justification
        SQL);
    }
}
