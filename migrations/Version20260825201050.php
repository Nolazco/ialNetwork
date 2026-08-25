<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Permite varios despachos por expediente y registra los momentos reales.
 *
 * delivery.reference_id era unico, asi que un expediente solo admitia un
 * despacho. La mercancia contenerizada necesita uno por camion, con hasta dos
 * contenedores cada uno, de ahi la tabla puente delivery_container.
 *
 * departed_at y delivered_at guardan cuando salio y cuando entrego realmente el
 * transportista, frente a la fecha y hora acordadas que ya estaban en date y
 * hour.
 */
final class Version20260825201050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Varios despachos por expediente, con sus contenedores y tiempos reales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE delivery_container (delivery_id INT NOT NULL, container_id INT NOT NULL, PRIMARY KEY(delivery_id, container_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_83C7A08912136921 ON delivery_container (delivery_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_83C7A089BC21F742 ON delivery_container (container_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_container ADD CONSTRAINT FK_83C7A08912136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_container ADD CONSTRAINT FK_83C7A089BC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_3781ec101645dea9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD departed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN delivery.departed_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN delivery.delivered_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3781EC101645DEA9 ON delivery (reference_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_container DROP CONSTRAINT FK_83C7A08912136921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_container DROP CONSTRAINT FK_83C7A089BC21F742
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE delivery_container
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_3781EC101645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP departed_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP delivered_at
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_3781ec101645dea9 ON delivery (reference_id)
        SQL);
    }
}
