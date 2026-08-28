<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un despacho puede cargar mas de un expediente a la vez (mismo cliente,
 * misma unidad): Delivery.reference (ManyToOne) pasa a ser ManyToMany.
 */
final class Version20260828223339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Un despacho puede cargar más de un expediente: Delivery.reference pasa de ManyToOne a ManyToMany.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE delivery_import_request (delivery_id INT NOT NULL, import_request_id INT NOT NULL, PRIMARY KEY(delivery_id, import_request_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_888A364C12136921 ON delivery_import_request (delivery_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_888A364C80F486B6 ON delivery_import_request (import_request_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_import_request ADD CONSTRAINT FK_888A364C12136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_import_request ADD CONSTRAINT FK_888A364C80F486B6 FOREIGN KEY (import_request_id) REFERENCES import_request (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        // Migra los datos existentes antes de perder la columna singular: sin
        // esto, todos los despachos ya agendados se quedarian sin expediente.
        $this->addSql(<<<'SQL'
            INSERT INTO delivery_import_request (delivery_id, import_request_id)
            SELECT id, reference_id FROM delivery WHERE reference_id IS NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP CONSTRAINT fk_3781ec101645dea9
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_3781ec101645dea9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP reference_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Si para cuando alguien revierta ya existe algun despacho con mas de
        // un expediente (el motivo mismo de este cambio), no hay forma de
        // restaurar la columna singular sin perder datos: se aborta en vez
        // de elegir uno cualquiera en silencio.
        $conflicting = $this->connection->fetchOne(<<<'SQL'
            SELECT count(*) FROM (
                SELECT delivery_id FROM delivery_import_request GROUP BY delivery_id HAVING COUNT(*) > 1
            ) AS conflictos
        SQL);

        if ((int) $conflicting > 0) {
            throw new \RuntimeException(
                'No se puede revertir: hay despachos con más de un expediente y la columna reference_id original solo admite uno. Habría que elegir uno y perder la relación con los demás.'
            );
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD reference_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE delivery d SET reference_id = (
                SELECT import_request_id FROM delivery_import_request WHERE delivery_id = d.id
            )
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ALTER COLUMN reference_id SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD CONSTRAINT fk_3781ec101645dea9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_3781ec101645dea9 ON delivery (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_import_request DROP CONSTRAINT FK_888A364C12136921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_import_request DROP CONSTRAINT FK_888A364C80F486B6
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE delivery_import_request
        SQL);
    }
}
