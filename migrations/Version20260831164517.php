<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Traslado local: catalogo de puntos de inspeccion (sembrado con XCF y
 * Acoman), catalogo de puntos de entrega por empresa, registro de traspasos
 * entre transportistas, y transportista de devolucion de vacios distinto
 * del que entrego. Todo nuevo y nullable donde aplica: sin backfill de
 * datos, down() no necesita guardas.
 */
final class Version20260831164517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Traslado local: puntos de inspección, puntos de entrega por empresa, traspasos entre transportistas y transportista de devolución.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE delivery_point (id SERIAL NOT NULL, company_id INT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A7AE15B6979B1AD6 ON delivery_point (company_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inspection_point (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        // Los unicos dos que se han usado en la practica; el catalogo queda
        // abierto por si algun dia aparece otro.
        $this->addSql(<<<'SQL'
            INSERT INTO inspection_point (name) VALUES ('XCF'), ('Acoman')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE local_transfer (id SERIAL NOT NULL, from_delivery_id INT NOT NULL, reference_id INT NOT NULL, inspection_point_id INT DEFAULT NULL, reported_by_id INT DEFAULT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, place_type VARCHAR(255) NOT NULL, place VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D3A089588C5C5844 ON local_transfer (from_delivery_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D3A089581645DEA9 ON local_transfer (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D3A089583339EAF9 ON local_transfer (inspection_point_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D3A0895871CE806 ON local_transfer (reported_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN local_transfer.at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE local_transfer_container (local_transfer_id INT NOT NULL, container_id INT NOT NULL, PRIMARY KEY(local_transfer_id, container_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DA6DC3FA38793A01 ON local_transfer_container (local_transfer_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DA6DC3FABC21F742 ON local_transfer_container (container_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD CONSTRAINT FK_A7AE15B6979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer ADD CONSTRAINT FK_D3A089588C5C5844 FOREIGN KEY (from_delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer ADD CONSTRAINT FK_D3A089581645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer ADD CONSTRAINT FK_D3A089583339EAF9 FOREIGN KEY (inspection_point_id) REFERENCES inspection_point (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer ADD CONSTRAINT FK_D3A0895871CE806 FOREIGN KEY (reported_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer_container ADD CONSTRAINT FK_DA6DC3FA38793A01 FOREIGN KEY (local_transfer_id) REFERENCES local_transfer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer_container ADD CONSTRAINT FK_DA6DC3FABC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD return_transport_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10427A142C FOREIGN KEY (return_transport_id) REFERENCES freight_hauler (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3781EC10427A142C ON delivery (return_transport_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD delivery_point_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD delivery_instructions TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_28872673A1492FCE FOREIGN KEY (delivery_point_id) REFERENCES delivery_point (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_28872673A1492FCE ON import_request (delivery_point_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP CONSTRAINT FK_28872673A1492FCE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP CONSTRAINT FK_A7AE15B6979B1AD6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer DROP CONSTRAINT FK_D3A089588C5C5844
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer DROP CONSTRAINT FK_D3A089581645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer DROP CONSTRAINT FK_D3A089583339EAF9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer DROP CONSTRAINT FK_D3A0895871CE806
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer_container DROP CONSTRAINT FK_DA6DC3FA38793A01
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE local_transfer_container DROP CONSTRAINT FK_DA6DC3FABC21F742
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE delivery_point
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE inspection_point
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE local_transfer
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE local_transfer_container
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10427A142C
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_3781EC10427A142C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP return_transport_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_28872673A1492FCE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP delivery_point_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP delivery_instructions
        SQL);
    }
}
