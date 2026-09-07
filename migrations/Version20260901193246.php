<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalogo de flota (unidades y choferes) de cada transportista, y el enganche
 * de un despacho a una unidad/chofer especificos — hasta ahora un despacho
 * solo sabia la empresa transportista, no quien se presentaba de verdad.
 */
final class Version20260901193246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega vehicle/driver (flota por transportista) y delivery.vehicle_id/driver_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE driver (id SERIAL NOT NULL, hauler_id INT NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(255) DEFAULT NULL, license VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_11667CD9D5712B92 ON driver (hauler_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE vehicle (id SERIAL NOT NULL, hauler_id INT NOT NULL, plates VARCHAR(32) NOT NULL, economic_number VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1B80E486D5712B92 ON vehicle (hauler_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE driver ADD CONSTRAINT FK_11667CD9D5712B92 FOREIGN KEY (hauler_id) REFERENCES freight_hauler (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E486D5712B92 FOREIGN KEY (hauler_id) REFERENCES freight_hauler (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD vehicle_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD driver_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3781EC10545317D1 ON delivery (vehicle_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3781EC10C3423909 ON delivery (driver_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10C3423909
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10545317D1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE driver DROP CONSTRAINT FK_11667CD9D5712B92
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE vehicle DROP CONSTRAINT FK_1B80E486D5712B92
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_3781EC10545317D1
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_3781EC10C3423909
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP vehicle_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP driver_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE driver
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE vehicle
        SQL);
    }
}
