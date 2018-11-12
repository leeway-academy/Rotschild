<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181112134941 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX IDX_F9E4C2D2CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__gasto_fijo AS SELECT id, banco_id, concepto, dia, importe FROM gasto_fijo');
        $this->addSql('DROP TABLE gasto_fijo');
        $this->addSql('CREATE TABLE gasto_fijo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, concepto VARCHAR(255) NOT NULL COLLATE BINARY, dia INTEGER NOT NULL, importe NUMERIC(10, 2) NOT NULL, fecha_fin DATETIME DEFAULT NULL, CONSTRAINT FK_F9E4C2D2CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO gasto_fijo (id, banco_id, concepto, dia, importe) SELECT id, banco_id, concepto, dia, importe FROM __temp__gasto_fijo');
        $this->addSql('DROP TABLE __temp__gasto_fijo');
        $this->addSql('CREATE INDEX IDX_F9E4C2D2CC04A73E ON gasto_fijo (banco_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX IDX_F9E4C2D2CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__gasto_fijo AS SELECT id, banco_id, concepto, dia, importe FROM gasto_fijo');
        $this->addSql('DROP TABLE gasto_fijo');
        $this->addSql('CREATE TABLE gasto_fijo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, concepto VARCHAR(255) NOT NULL, dia INTEGER NOT NULL, importe NUMERIC(10, 2) NOT NULL)');
        $this->addSql('INSERT INTO gasto_fijo (id, banco_id, concepto, dia, importe) SELECT id, banco_id, concepto, dia, importe FROM __temp__gasto_fijo');
        $this->addSql('DROP TABLE __temp__gasto_fijo');
        $this->addSql('CREATE INDEX IDX_F9E4C2D2CC04A73E ON gasto_fijo (banco_id)');
    }
}
