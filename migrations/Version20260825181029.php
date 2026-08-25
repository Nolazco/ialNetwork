<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Esquema inicial de IAL Network.
 *
 * Reconstruye el estado que hasta ahora solo existia en backup.sql: las 14
 * entidades del dominio mas la tabla pivote container_import_request. Las bases
 * de datos que ya venian de ese dump deben marcar esta version como ejecutada
 * (doctrine:migrations:version --add) en lugar de correrla.
 */
final class Version20260825181029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Esquema inicial: 14 entidades del dominio aduanal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE associated (id SERIAL NOT NULL, id_client_id INT NOT NULL, id_company_id INT NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D3D550D699DED506 ON associated (id_client_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D3D550D632119A01 ON associated (id_company_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE company (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, rfc VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE company_document (id SERIAL NOT NULL, id_company_id INT NOT NULL, type VARCHAR(255) NOT NULL, route VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C0FE9F1B32119A01 ON company_document (id_company_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE container (id SERIAL NOT NULL, num VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE container_import_request (container_id INT NOT NULL, import_request_id INT NOT NULL, PRIMARY KEY(container_id, import_request_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1C47599DBC21F742 ON container_import_request (container_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1C47599D80F486B6 ON container_import_request (import_request_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE container_yard (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, cr VARCHAR(10) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE delivery (id SERIAL NOT NULL, reference_id INT NOT NULL, transport_id INT NOT NULL, date VARCHAR(255) NOT NULL, hour VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_3781EC101645DEA9 ON delivery (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3781EC109909C13F ON delivery (transport_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE empty_return (id SERIAL NOT NULL, container_id INT NOT NULL, reference_id INT NOT NULL, transport_id INT NOT NULL, yard_id INT NOT NULL, type VARCHAR(255) NOT NULL, date VARCHAR(255) NOT NULL, eir VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_7BBC9C40BC21F742 ON empty_return (container_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7BBC9C401645DEA9 ON empty_return (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7BBC9C409909C13F ON empty_return (transport_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7BBC9C40896259A0 ON empty_return (yard_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE freight_hauler (id SERIAL NOT NULL, id_user_id INT NOT NULL, caat VARCHAR(4) NOT NULL, company_name VARCHAR(255) NOT NULL, rfc VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_363BC36179F37AE5 ON freight_hauler (id_user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE import_document (id SERIAL NOT NULL, reference_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, route VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_71C6348C1645DEA9 ON import_document (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE import_request (id SERIAL NOT NULL, id_company_id INT NOT NULL, id_provider_id INT NOT NULL, cr_id INT NOT NULL, client_reference VARCHAR(255) NOT NULL, agency_reference VARCHAR(255) NOT NULL, import_number VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, eta VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, goods VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2887267332119A01 ON import_request (id_company_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_288726731241655D ON import_request (id_provider_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2887267340868EB5 ON import_request (cr_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE intern_invoice (id SERIAL NOT NULL, reference_id INT NOT NULL, concept VARCHAR(255) NOT NULL, route VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E6F879721645DEA9 ON intern_invoice (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE operation (id SERIAL NOT NULL, reference_id INT NOT NULL, type VARCHAR(255) NOT NULL, date VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1981A66D1645DEA9 ON operation (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE provider (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, tax_id VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE associated ADD CONSTRAINT FK_D3D550D699DED506 FOREIGN KEY (id_client_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE associated ADD CONSTRAINT FK_D3D550D632119A01 FOREIGN KEY (id_company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_document ADD CONSTRAINT FK_C0FE9F1B32119A01 FOREIGN KEY (id_company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE container_import_request ADD CONSTRAINT FK_1C47599DBC21F742 FOREIGN KEY (container_id) REFERENCES container (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE container_import_request ADD CONSTRAINT FK_1C47599D80F486B6 FOREIGN KEY (import_request_id) REFERENCES import_request (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD CONSTRAINT FK_3781EC101645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery ADD CONSTRAINT FK_3781EC109909C13F FOREIGN KEY (transport_id) REFERENCES freight_hauler (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD CONSTRAINT FK_7BBC9C40BC21F742 FOREIGN KEY (container_id) REFERENCES container (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD CONSTRAINT FK_7BBC9C401645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD CONSTRAINT FK_7BBC9C409909C13F FOREIGN KEY (transport_id) REFERENCES freight_hauler (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD CONSTRAINT FK_7BBC9C40896259A0 FOREIGN KEY (yard_id) REFERENCES container_yard (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE freight_hauler ADD CONSTRAINT FK_363BC36179F37AE5 FOREIGN KEY (id_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_document ADD CONSTRAINT FK_71C6348C1645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_2887267332119A01 FOREIGN KEY (id_company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_288726731241655D FOREIGN KEY (id_provider_id) REFERENCES provider (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD CONSTRAINT FK_2887267340868EB5 FOREIGN KEY (cr_id) REFERENCES container_yard (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE intern_invoice ADD CONSTRAINT FK_E6F879721645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE operation ADD CONSTRAINT FK_1981A66D1645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE associated DROP CONSTRAINT FK_D3D550D699DED506
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE associated DROP CONSTRAINT FK_D3D550D632119A01
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company_document DROP CONSTRAINT FK_C0FE9F1B32119A01
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE container_import_request DROP CONSTRAINT FK_1C47599DBC21F742
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE container_import_request DROP CONSTRAINT FK_1C47599D80F486B6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP CONSTRAINT FK_3781EC101645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery DROP CONSTRAINT FK_3781EC109909C13F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP CONSTRAINT FK_7BBC9C40BC21F742
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP CONSTRAINT FK_7BBC9C401645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP CONSTRAINT FK_7BBC9C409909C13F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP CONSTRAINT FK_7BBC9C40896259A0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE freight_hauler DROP CONSTRAINT FK_363BC36179F37AE5
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_document DROP CONSTRAINT FK_71C6348C1645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP CONSTRAINT FK_2887267332119A01
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP CONSTRAINT FK_288726731241655D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP CONSTRAINT FK_2887267340868EB5
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE intern_invoice DROP CONSTRAINT FK_E6F879721645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE operation DROP CONSTRAINT FK_1981A66D1645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE associated
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE company
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE company_document
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE container
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE container_import_request
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE container_yard
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE delivery
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE empty_return
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE freight_hauler
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE import_document
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE import_request
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE intern_invoice
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE operation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE provider
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "user"
        SQL);
    }
}
