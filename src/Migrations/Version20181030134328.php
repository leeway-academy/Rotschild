<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181030134328 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX saldo_banco');
        $this->addSql('DROP INDEX IDX_3A3CFAB4CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__saldo_bancario AS SELECT id, banco_id, fecha, valor, diferencia_con_proyectado FROM saldo_bancario');
        $this->addSql('DROP TABLE saldo_bancario');
        $this->addSql('CREATE TABLE saldo_bancario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, fecha DATE NOT NULL, valor NUMERIC(10, 2) NOT NULL, diferencia_con_proyectado NUMERIC(10, 2) NOT NULL, CONSTRAINT FK_3A3CFAB4CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO saldo_bancario (id, banco_id, fecha, valor, diferencia_con_proyectado) SELECT id, banco_id, fecha, valor, diferencia_con_proyectado FROM __temp__saldo_bancario');
        $this->addSql('DROP TABLE __temp__saldo_bancario');
        $this->addSql('CREATE UNIQUE INDEX saldo_banco ON saldo_bancario (fecha, banco_id)');
        $this->addSql('CREATE INDEX IDX_3A3CFAB4CC04A73E ON saldo_bancario (banco_id)');
        $this->addSql('DROP INDEX UNIQ_57B3CF5ECC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__bank_xlsstructure AS SELECT id, banco_id, stop_word, first_row, concept_col, amount_col, date_col FROM bank_xlsstructure');
        $this->addSql('DROP TABLE bank_xlsstructure');
        $this->addSql('CREATE TABLE bank_xlsstructure (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, stop_word VARCHAR(255) DEFAULT NULL COLLATE BINARY, first_row SMALLINT NOT NULL, concept_col SMALLINT NOT NULL, amount_col SMALLINT NOT NULL, date_col SMALLINT NOT NULL, date_format VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_57B3CF5ECC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO bank_xlsstructure (id, banco_id, stop_word, first_row, concept_col, amount_col, date_col) SELECT id, banco_id, stop_word, first_row, concept_col, amount_col, date_col FROM __temp__bank_xlsstructure');
        $this->addSql('DROP TABLE __temp__bank_xlsstructure');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57B3CF5ECC04A73E ON bank_xlsstructure (banco_id)');
        $this->addSql('DROP INDEX IDX_C8FF107ACC04A73E');
        $this->addSql('DROP INDEX IDX_C8FF107AB5DD50A6');
        $this->addSql('CREATE TEMPORARY TABLE __temp__movimiento AS SELECT id, banco_id, clon_de_id, concepto, fecha, importe, concretado FROM movimiento');
        $this->addSql('DROP TABLE movimiento');
        $this->addSql('CREATE TABLE movimiento (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, clon_de_id INTEGER DEFAULT NULL, concepto VARCHAR(255) NOT NULL COLLATE BINARY, fecha DATE NOT NULL, importe NUMERIC(10, 2) NOT NULL, concretado BOOLEAN NOT NULL, CONSTRAINT FK_C8FF107ACC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C8FF107AB5DD50A6 FOREIGN KEY (clon_de_id) REFERENCES gasto_fijo (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO movimiento (id, banco_id, clon_de_id, concepto, fecha, importe, concretado) SELECT id, banco_id, clon_de_id, concepto, fecha, importe, concretado FROM __temp__movimiento');
        $this->addSql('DROP TABLE __temp__movimiento');
        $this->addSql('CREATE INDEX IDX_C8FF107ACC04A73E ON movimiento (banco_id)');
        $this->addSql('CREATE INDEX IDX_C8FF107AB5DD50A6 ON movimiento (clon_de_id)');
        $this->addSql('DROP INDEX IDX_F9E4C2D2CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__gasto_fijo AS SELECT id, banco_id, concepto, dia, importe FROM gasto_fijo');
        $this->addSql('DROP TABLE gasto_fijo');
        $this->addSql('CREATE TABLE gasto_fijo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, concepto VARCHAR(255) NOT NULL COLLATE BINARY, dia INTEGER NOT NULL, importe NUMERIC(10, 2) NOT NULL, CONSTRAINT FK_F9E4C2D2CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO gasto_fijo (id, banco_id, concepto, dia, importe) SELECT id, banco_id, concepto, dia, importe FROM __temp__gasto_fijo');
        $this->addSql('DROP TABLE __temp__gasto_fijo');
        $this->addSql('CREATE INDEX IDX_F9E4C2D2CC04A73E ON gasto_fijo (banco_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX UNIQ_57B3CF5ECC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__bank_xlsstructure AS SELECT id, banco_id, first_row, stop_word, concept_col, amount_col, date_col FROM bank_xlsstructure');
        $this->addSql('DROP TABLE bank_xlsstructure');
        $this->addSql('CREATE TABLE bank_xlsstructure (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, stop_word VARCHAR(255) DEFAULT NULL, first_row SMALLINT NOT NULL, concept_col SMALLINT NOT NULL, amount_col SMALLINT NOT NULL, date_col SMALLINT NOT NULL)');
        $this->addSql('INSERT INTO bank_xlsstructure (id, banco_id, first_row, stop_word, concept_col, amount_col, date_col) SELECT id, banco_id, first_row, stop_word, concept_col, amount_col, date_col FROM __temp__bank_xlsstructure');
        $this->addSql('DROP TABLE __temp__bank_xlsstructure');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57B3CF5ECC04A73E ON bank_xlsstructure (banco_id)');
        $this->addSql('DROP INDEX IDX_F9E4C2D2CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__gasto_fijo AS SELECT id, banco_id, concepto, dia, importe FROM gasto_fijo');
        $this->addSql('DROP TABLE gasto_fijo');
        $this->addSql('CREATE TABLE gasto_fijo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, concepto VARCHAR(255) NOT NULL, dia INTEGER NOT NULL, importe NUMERIC(10, 2) NOT NULL)');
        $this->addSql('INSERT INTO gasto_fijo (id, banco_id, concepto, dia, importe) SELECT id, banco_id, concepto, dia, importe FROM __temp__gasto_fijo');
        $this->addSql('DROP TABLE __temp__gasto_fijo');
        $this->addSql('CREATE INDEX IDX_F9E4C2D2CC04A73E ON gasto_fijo (banco_id)');
        $this->addSql('DROP INDEX IDX_C8FF107ACC04A73E');
        $this->addSql('DROP INDEX IDX_C8FF107AB5DD50A6');
        $this->addSql('CREATE TEMPORARY TABLE __temp__movimiento AS SELECT id, banco_id, clon_de_id, concepto, fecha, importe, concretado FROM movimiento');
        $this->addSql('DROP TABLE movimiento');
        $this->addSql('CREATE TABLE movimiento (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, clon_de_id INTEGER DEFAULT NULL, concepto VARCHAR(255) NOT NULL, fecha DATE NOT NULL, importe NUMERIC(10, 2) NOT NULL, concretado BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO movimiento (id, banco_id, clon_de_id, concepto, fecha, importe, concretado) SELECT id, banco_id, clon_de_id, concepto, fecha, importe, concretado FROM __temp__movimiento');
        $this->addSql('DROP TABLE __temp__movimiento');
        $this->addSql('CREATE INDEX IDX_C8FF107ACC04A73E ON movimiento (banco_id)');
        $this->addSql('CREATE INDEX IDX_C8FF107AB5DD50A6 ON movimiento (clon_de_id)');
        $this->addSql('DROP INDEX IDX_3A3CFAB4CC04A73E');
        $this->addSql('DROP INDEX saldo_banco');
        $this->addSql('CREATE TEMPORARY TABLE __temp__saldo_bancario AS SELECT id, banco_id, fecha, valor, diferencia_con_proyectado FROM saldo_bancario');
        $this->addSql('DROP TABLE saldo_bancario');
        $this->addSql('CREATE TABLE saldo_bancario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, fecha DATE NOT NULL, valor NUMERIC(10, 2) NOT NULL, diferencia_con_proyectado NUMERIC(10, 2) NOT NULL)');
        $this->addSql('INSERT INTO saldo_bancario (id, banco_id, fecha, valor, diferencia_con_proyectado) SELECT id, banco_id, fecha, valor, diferencia_con_proyectado FROM __temp__saldo_bancario');
        $this->addSql('DROP TABLE __temp__saldo_bancario');
        $this->addSql('CREATE INDEX IDX_3A3CFAB4CC04A73E ON saldo_bancario (banco_id)');
        $this->addSql('CREATE UNIQUE INDEX saldo_banco ON saldo_bancario (fecha, banco_id)');
    }
}
