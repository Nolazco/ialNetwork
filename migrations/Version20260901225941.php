<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los patios de devolucion de vacios son un catalogo aparte de los recintos
 * fiscalizados (ContainerYard, que se sigue usando en el alta del pedimento)
 * — la tabla vive vacia hoy (ningun EmptyReturn tiene aun un yard_id real),
 * asi que reapuntar la misma columna no pierde ningun dato.
 */
final class Version20260901225941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega empty_return_yard y reapunta empty_return.yard_id hacia ahi (antes apuntaba a container_yard).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE empty_return_yard (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP CONSTRAINT FK_7BBC9C40896259A0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD CONSTRAINT FK_7BBC9C40896259A0 FOREIGN KEY (yard_id) REFERENCES empty_return_yard (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return DROP CONSTRAINT FK_7BBC9C40896259A0
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE empty_return_yard
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE empty_return ADD CONSTRAINT fk_7bbc9c40896259a0 FOREIGN KEY (yard_id) REFERENCES container_yard (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }
}
