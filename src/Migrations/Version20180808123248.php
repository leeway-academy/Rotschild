<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180808123248 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE movimiento (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, concepto VARCHAR(255) NOT NULL, recurrente BOOLEAN NOT NULL, fecha DATE NOT NULL, importe NUMERIC(10, 2) NOT NULL)');
        $this->addSql('CREATE INDEX IDX_C8FF107ACC04A73E ON movimiento (banco_id)');
        $this->addSql('CREATE TABLE banco (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, saldo NUMERIC(10, 2) NOT NULL)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE movimiento');
        $this->addSql('DROP TABLE banco');
    }
}
