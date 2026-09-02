<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Instrucciones al consolidador de carga (XCF): catalogo de mercancias
 * reutilizable por cliente (MerchandiseProfile), el registro de cada
 * instruccion mandada (ConsolidatorInstruction), y los campos de domicilio
 * desglosado/contacto que le faltaban a DeliveryPoint (destinatario) y
 * Company (facturador) para llenar el Excel de XCF. Todo nuevo y nullable
 * donde aplica: sin backfill de datos, down() no necesita guardas.
 */
final class Version20260831235946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega MerchandiseProfile y ConsolidatorInstruction, y domicilio/contacto desglosados en DeliveryPoint y Company, para las instrucciones al consolidador de carga (XCF).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE consolidator_instruction (
              id SERIAL NOT NULL,
              reference_id INT NOT NULL,
              delivery_point_id INT NOT NULL,
              merchandise_profile_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              descripcion VARCHAR(255) NOT NULL,
              clave_sat VARCHAR(255) NOT NULL,
              clave_unidad VARCHAR(255) NOT NULL,
              unidad VARCHAR(255) NOT NULL,
              estibable BOOLEAN NOT NULL,
              quantity INT NOT NULL,
              weight_kg DOUBLE PRECISION NOT NULL,
              billed_to_client BOOLEAN NOT NULL,
              file_route VARCHAR(255) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A8BF2501645DEA9 ON consolidator_instruction (reference_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A8BF250A1492FCE ON consolidator_instruction (delivery_point_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A8BF250BFC486A3 ON consolidator_instruction (merchandise_profile_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A8BF250B03A8386 ON consolidator_instruction (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN consolidator_instruction.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE merchandise_profile (
              id SERIAL NOT NULL,
              company_id INT NOT NULL,
              descripcion VARCHAR(255) NOT NULL,
              clave_sat VARCHAR(255) NOT NULL,
              clave_unidad VARCHAR(255) NOT NULL,
              unidad VARCHAR(255) NOT NULL,
              estibable BOOLEAN NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D695AD6E979B1AD6 ON merchandise_profile (company_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consolidator_instruction
            ADD
              CONSTRAINT FK_A8BF2501645DEA9 FOREIGN KEY (reference_id) REFERENCES import_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consolidator_instruction
            ADD
              CONSTRAINT FK_A8BF250A1492FCE FOREIGN KEY (delivery_point_id) REFERENCES delivery_point (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consolidator_instruction
            ADD
              CONSTRAINT FK_A8BF250BFC486A3 FOREIGN KEY (merchandise_profile_id) REFERENCES merchandise_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consolidator_instruction
            ADD
              CONSTRAINT FK_A8BF250B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE
            SET
              NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              merchandise_profile
            ADD
              CONSTRAINT FK_D695AD6E979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD street VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD ext_number VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD int_number VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD neighborhood VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD locality VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD municipality VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD state VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD country VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD zip_code VARCHAR(32) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD contact_name VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD contact_phone VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company ADD contact_email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD rfc VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD street VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD ext_number VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD int_number VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD neighborhood VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD locality VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD municipality VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD state VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD country VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD zip_code VARCHAR(32) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD contact_name VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD contact_phone VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point ADD contact_email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request ADD tariff_fraction VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP CONSTRAINT FK_A8BF2501645DEA9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP CONSTRAINT FK_A8BF250A1492FCE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP CONSTRAINT FK_A8BF250BFC486A3
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consolidator_instruction DROP CONSTRAINT FK_A8BF250B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE merchandise_profile DROP CONSTRAINT FK_D695AD6E979B1AD6
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE consolidator_instruction
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE merchandise_profile
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP rfc
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP street
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP ext_number
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP int_number
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP neighborhood
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP locality
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP municipality
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP state
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP country
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP zip_code
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP contact_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP contact_phone
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE delivery_point DROP contact_email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP street
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP ext_number
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP int_number
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP neighborhood
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP locality
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP municipality
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP state
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP country
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP zip_code
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP contact_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP contact_phone
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE company DROP contact_email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_request DROP tariff_fraction
        SQL);
    }
}
