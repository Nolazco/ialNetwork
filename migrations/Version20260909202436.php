<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un punto de entrega deja de pertenecer a una sola Company (ManyToOne) y
 * pasa a poder compartirse entre varias (ManyToMany) — un cliente con mas de
 * una empresa ya no tiene que duplicar el mismo almacen en cada una (ver
 * DeliveryPoint::$companies y DashboardDeliveryPoints).
 *
 * El INSERT ... SELECT antes de tirar la columna vieja preserva la relacion
 * de cada punto existente con su unica empresa de entonces.
 */
final class Version20260909202436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite que un punto de entrega se comparta entre varias empresas del mismo cliente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE delivery_point_company (delivery_point_id INT NOT NULL, company_id INT NOT NULL, PRIMARY KEY(delivery_point_id, company_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FF99B763A1492FCE ON delivery_point_company (delivery_point_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FF99B763979B1AD6 ON delivery_point_company (company_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point_company ADD CONSTRAINT FK_FF99B763A1492FCE FOREIGN KEY (delivery_point_id) REFERENCES delivery_point (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point_company ADD CONSTRAINT FK_FF99B763979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO delivery_point_company (delivery_point_id, company_id) SELECT id, company_id FROM delivery_point
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP CONSTRAINT fk_a7ae15b6979b1ad6
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_a7ae15b6979b1ad6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP company_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD company_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE delivery_point dp SET company_id = (SELECT MIN(company_id) FROM delivery_point_company WHERE delivery_point_id = dp.id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ALTER company_id SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD CONSTRAINT fk_a7ae15b6979b1ad6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_a7ae15b6979b1ad6 ON delivery_point (company_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point_company DROP CONSTRAINT FK_FF99B763A1492FCE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point_company DROP CONSTRAINT FK_FF99B763979B1AD6
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE delivery_point_company
        SQL);
    }
}
