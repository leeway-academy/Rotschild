<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181219194630 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX IDX_F6395884CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__cheque_emitido AS SELECT id, banco_id, numero, fecha, importe FROM cheque_emitido');
        $this->addSql('DROP TABLE cheque_emitido');
        $this->addSql('CREATE TABLE cheque_emitido (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, movimiento_id INTEGER DEFAULT NULL, numero VARCHAR(255) NOT NULL COLLATE BINARY, fecha DATE NOT NULL, importe NUMERIC(10, 2) NOT NULL, CONSTRAINT FK_F6395884CC04A73E FOREIGN KEY (banco_id) REFERENCES bank (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F639588458E7D5A2 FOREIGN KEY (movimiento_id) REFERENCES movimiento (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO cheque_emitido (id, banco_id, numero, fecha, importe) SELECT id, banco_id, numero, fecha, importe FROM __temp__cheque_emitido');
        $this->addSql('DROP TABLE __temp__cheque_emitido');
        $this->addSql('CREATE INDEX IDX_F6395884CC04A73E ON cheque_emitido (banco_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F639588458E7D5A2 ON cheque_emitido (movimiento_id)');
        $this->addSql('DROP INDEX saldo_banco');
        $this->addSql('DROP INDEX IDX_3A3CFAB4CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__saldo_bancario AS SELECT id, banco_id, fecha, valor, diferencia_con_proyectado FROM saldo_bancario');
        $this->addSql('DROP TABLE saldo_bancario');
        $this->addSql('CREATE TABLE saldo_bancario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, fecha DATE NOT NULL, valor NUMERIC(10, 2) NOT NULL, diferencia_con_proyectado NUMERIC(10, 2) NOT NULL, CONSTRAINT FK_3A3CFAB4CC04A73E FOREIGN KEY (banco_id) REFERENCES bank (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO saldo_bancario (id, banco_id, fecha, valor, diferencia_con_proyectado) SELECT id, banco_id, fecha, valor, diferencia_con_proyectado FROM __temp__saldo_bancario');
        $this->addSql('DROP TABLE __temp__saldo_bancario');
        $this->addSql('CREATE UNIQUE INDEX saldo_banco ON saldo_bancario (fecha, banco_id)');
        $this->addSql('CREATE INDEX IDX_3A3CFAB4CC04A73E ON saldo_bancario (banco_id)');
        $this->addSql('DROP INDEX IDX_76CB3034B916CD13');
        $this->addSql('CREATE TEMPORARY TABLE __temp__renglon_extracto AS SELECT id, extracto_id, linea, fecha, concepto, importe FROM renglon_extracto');
        $this->addSql('DROP TABLE renglon_extracto');
        $this->addSql('CREATE TABLE renglon_extracto (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, extracto_id INTEGER NOT NULL, linea INTEGER NOT NULL, fecha DATE NOT NULL, concepto VARCHAR(255) NOT NULL COLLATE BINARY, importe NUMERIC(10, 2) NOT NULL, CONSTRAINT FK_76CB3034B916CD13 FOREIGN KEY (extracto_id) REFERENCES extracto_bancario (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO renglon_extracto (id, extracto_id, linea, fecha, concepto, importe) SELECT id, extracto_id, linea, fecha, concepto, importe FROM __temp__renglon_extracto');
        $this->addSql('DROP TABLE __temp__renglon_extracto');
        $this->addSql('CREATE INDEX IDX_76CB3034B916CD13 ON renglon_extracto (extracto_id)');
        $this->addSql('DROP INDEX IDX_C41BE019CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__extracto_bancario AS SELECT id, banco_id, fecha, archivo FROM extracto_bancario');
        $this->addSql('DROP TABLE extracto_bancario');
        $this->addSql('CREATE TABLE extracto_bancario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, fecha DATE NOT NULL, archivo VARCHAR(255) NOT NULL COLLATE BINARY, CONSTRAINT FK_C41BE019CC04A73E FOREIGN KEY (banco_id) REFERENCES bank (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO extracto_bancario (id, banco_id, fecha, archivo) SELECT id, banco_id, fecha, archivo FROM __temp__extracto_bancario');
        $this->addSql('DROP TABLE __temp__extracto_bancario');
        $this->addSql('CREATE INDEX IDX_C41BE019CC04A73E ON extracto_bancario (banco_id)');
        $this->addSql('DROP INDEX UNIQ_57B3CF5ECC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__bank_xlsstructure AS SELECT id, banco_id, stop_word, first_header, extra_data_col, first_row, concept_col, amount_col, date_col, date_format FROM bank_xlsstructure');
        $this->addSql('DROP TABLE bank_xlsstructure');
        $this->addSql('CREATE TABLE bank_xlsstructure (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, stop_word VARCHAR(255) DEFAULT NULL COLLATE BINARY, first_header VARCHAR(255) DEFAULT NULL COLLATE BINARY, extra_data_col INTEGER DEFAULT NULL, first_row SMALLINT NOT NULL, concept_col SMALLINT NOT NULL, amount_col SMALLINT NOT NULL, date_col SMALLINT NOT NULL, date_format VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_57B3CF5ECC04A73E FOREIGN KEY (banco_id) REFERENCES bank (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO bank_xlsstructure (id, banco_id, stop_word, first_header, extra_data_col, first_row, concept_col, amount_col, date_col, date_format) SELECT id, banco_id, stop_word, first_header, extra_data_col, first_row, concept_col, amount_col, date_col, date_format FROM __temp__bank_xlsstructure');
        $this->addSql('DROP TABLE __temp__bank_xlsstructure');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57B3CF5ECC04A73E ON bank_xlsstructure (banco_id)');
        $this->addSql('DROP INDEX UNIQ_C8FF107AF7C641CF');
        $this->addSql('DROP INDEX IDX_C8FF107A96D3A5C1');
        $this->addSql('DROP INDEX IDX_C8FF107AB5DD50A6');
        $this->addSql('DROP INDEX IDX_C8FF107ACC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__movimiento AS SELECT id, banco_id, clon_de_id, renglon_extracto_id, applied_check_id, concepto, fecha, importe FROM movimiento');
        $this->addSql('DROP TABLE movimiento');
        $this->addSql('CREATE TABLE movimiento (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, clon_de_id INTEGER DEFAULT NULL, renglon_extracto_id INTEGER DEFAULT NULL, applied_check_id INTEGER DEFAULT NULL, concepto VARCHAR(255) NOT NULL COLLATE BINARY, fecha DATE NOT NULL, importe NUMERIC(10, 2) NOT NULL, CONSTRAINT FK_C8FF107ACC04A73E FOREIGN KEY (banco_id) REFERENCES bank (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C8FF107AB5DD50A6 FOREIGN KEY (clon_de_id) REFERENCES gasto_fijo (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C8FF107A96D3A5C1 FOREIGN KEY (renglon_extracto_id) REFERENCES renglon_extracto (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C8FF107AF7C641CF FOREIGN KEY (applied_check_id) REFERENCES applied_check (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO movimiento (id, banco_id, clon_de_id, renglon_extracto_id, applied_check_id, concepto, fecha, importe) SELECT id, banco_id, clon_de_id, renglon_extracto_id, applied_check_id, concepto, fecha, importe FROM __temp__movimiento');
        $this->addSql('DROP TABLE __temp__movimiento');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C8FF107AF7C641CF ON movimiento (applied_check_id)');
        $this->addSql('CREATE INDEX IDX_C8FF107A96D3A5C1 ON movimiento (renglon_extracto_id)');
        $this->addSql('CREATE INDEX IDX_C8FF107AB5DD50A6 ON movimiento (clon_de_id)');
        $this->addSql('CREATE INDEX IDX_C8FF107ACC04A73E ON movimiento (banco_id)');
        $this->addSql('DROP INDEX IDX_F9E4C2D2CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__gasto_fijo AS SELECT id, banco_id, concepto, dia, importe, fecha_fin, fecha_inicio FROM gasto_fijo');
        $this->addSql('DROP TABLE gasto_fijo');
        $this->addSql('CREATE TABLE gasto_fijo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, concepto VARCHAR(255) NOT NULL COLLATE BINARY, dia INTEGER NOT NULL, importe NUMERIC(10, 2) NOT NULL, fecha_fin DATETIME DEFAULT NULL, fecha_inicio DATE NOT NULL, CONSTRAINT FK_F9E4C2D2CC04A73E FOREIGN KEY (banco_id) REFERENCES bank (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO gasto_fijo (id, banco_id, concepto, dia, importe, fecha_fin, fecha_inicio) SELECT id, banco_id, concepto, dia, importe, fecha_fin, fecha_inicio FROM __temp__gasto_fijo');
        $this->addSql('DROP TABLE __temp__gasto_fijo');
        $this->addSql('CREATE INDEX IDX_F9E4C2D2CC04A73E ON gasto_fijo (banco_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX UNIQ_57B3CF5ECC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__bank_xlsstructure AS SELECT id, banco_id, first_row, stop_word, concept_col, amount_col, date_col, date_format, first_header, extra_data_col FROM bank_xlsstructure');
        $this->addSql('DROP TABLE bank_xlsstructure');
        $this->addSql('CREATE TABLE bank_xlsstructure (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, stop_word VARCHAR(255) DEFAULT NULL, first_header VARCHAR(255) DEFAULT NULL, extra_data_col INTEGER DEFAULT NULL, first_row SMALLINT NOT NULL, concept_col SMALLINT NOT NULL, amount_col SMALLINT NOT NULL, date_col SMALLINT NOT NULL, date_format VARCHAR(255) DEFAULT NULL COLLATE BINARY)');
        $this->addSql('INSERT INTO bank_xlsstructure (id, banco_id, first_row, stop_word, concept_col, amount_col, date_col, date_format, first_header, extra_data_col) SELECT id, banco_id, first_row, stop_word, concept_col, amount_col, date_col, date_format, first_header, extra_data_col FROM __temp__bank_xlsstructure');
        $this->addSql('DROP TABLE __temp__bank_xlsstructure');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57B3CF5ECC04A73E ON bank_xlsstructure (banco_id)');
        $this->addSql('DROP INDEX IDX_F6395884CC04A73E');
        $this->addSql('DROP INDEX UNIQ_F639588458E7D5A2');
        $this->addSql('CREATE TEMPORARY TABLE __temp__cheque_emitido AS SELECT id, banco_id, numero, fecha, importe FROM cheque_emitido');
        $this->addSql('DROP TABLE cheque_emitido');
        $this->addSql('CREATE TABLE cheque_emitido (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, numero VARCHAR(255) NOT NULL, fecha DATE NOT NULL, importe NUMERIC(10, 2) NOT NULL)');
        $this->addSql('INSERT INTO cheque_emitido (id, banco_id, numero, fecha, importe) SELECT id, banco_id, numero, fecha, importe FROM __temp__cheque_emitido');
        $this->addSql('DROP TABLE __temp__cheque_emitido');
        $this->addSql('CREATE INDEX IDX_F6395884CC04A73E ON cheque_emitido (banco_id)');
        $this->addSql('DROP INDEX IDX_C41BE019CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__extracto_bancario AS SELECT id, banco_id, fecha, archivo FROM extracto_bancario');
        $this->addSql('DROP TABLE extracto_bancario');
        $this->addSql('CREATE TABLE extracto_bancario (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, fecha DATE NOT NULL, archivo VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO extracto_bancario (id, banco_id, fecha, archivo) SELECT id, banco_id, fecha, archivo FROM __temp__extracto_bancario');
        $this->addSql('DROP TABLE __temp__extracto_bancario');
        $this->addSql('CREATE INDEX IDX_C41BE019CC04A73E ON extracto_bancario (banco_id)');
        $this->addSql('DROP INDEX IDX_F9E4C2D2CC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__gasto_fijo AS SELECT id, banco_id, concepto, dia, importe, fecha_fin, fecha_inicio FROM gasto_fijo');
        $this->addSql('DROP TABLE gasto_fijo');
        $this->addSql('CREATE TABLE gasto_fijo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, concepto VARCHAR(255) NOT NULL, dia INTEGER NOT NULL, importe NUMERIC(10, 2) NOT NULL, fecha_fin DATETIME DEFAULT NULL, fecha_inicio DATE NOT NULL)');
        $this->addSql('INSERT INTO gasto_fijo (id, banco_id, concepto, dia, importe, fecha_fin, fecha_inicio) SELECT id, banco_id, concepto, dia, importe, fecha_fin, fecha_inicio FROM __temp__gasto_fijo');
        $this->addSql('DROP TABLE __temp__gasto_fijo');
        $this->addSql('CREATE INDEX IDX_F9E4C2D2CC04A73E ON gasto_fijo (banco_id)');
        $this->addSql('DROP INDEX IDX_C8FF107ACC04A73E');
        $this->addSql('DROP INDEX IDX_C8FF107AB5DD50A6');
        $this->addSql('DROP INDEX IDX_C8FF107A96D3A5C1');
        $this->addSql('DROP INDEX UNIQ_C8FF107AF7C641CF');
        $this->addSql('CREATE TEMPORARY TABLE __temp__movimiento AS SELECT id, banco_id, clon_de_id, renglon_extracto_id, applied_check_id, concepto, fecha, importe FROM movimiento');
        $this->addSql('DROP TABLE movimiento');
        $this->addSql('CREATE TABLE movimiento (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, clon_de_id INTEGER DEFAULT NULL, renglon_extracto_id INTEGER DEFAULT NULL, applied_check_id INTEGER DEFAULT NULL, concepto VARCHAR(255) NOT NULL, fecha DATE NOT NULL, importe NUMERIC(10, 2) NOT NULL)');
        $this->addSql('INSERT INTO movimiento (id, banco_id, clon_de_id, renglon_extracto_id, applied_check_id, concepto, fecha, importe) SELECT id, banco_id, clon_de_id, renglon_extracto_id, applied_check_id, concepto, fecha, importe FROM __temp__movimiento');
        $this->addSql('DROP TABLE __temp__movimiento');
        $this->addSql('CREATE INDEX IDX_C8FF107ACC04A73E ON movimiento (banco_id)');
        $this->addSql('CREATE INDEX IDX_C8FF107AB5DD50A6 ON movimiento (clon_de_id)');
        $this->addSql('CREATE INDEX IDX_C8FF107A96D3A5C1 ON movimiento (renglon_extracto_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C8FF107AF7C641CF ON movimiento (applied_check_id)');
        $this->addSql('DROP INDEX IDX_76CB3034B916CD13');
        $this->addSql('CREATE TEMPORARY TABLE __temp__renglon_extracto AS SELECT id, extracto_id, linea, fecha, concepto, importe FROM renglon_extracto');
        $this->addSql('DROP TABLE renglon_extracto');
        $this->addSql('CREATE TABLE renglon_extracto (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, extracto_id INTEGER NOT NULL, linea INTEGER NOT NULL, fecha DATE NOT NULL, concepto VARCHAR(255) NOT NULL, importe NUMERIC(10, 2) NOT NULL)');
        $this->addSql('INSERT INTO renglon_extracto (id, extracto_id, linea, fecha, concepto, importe) SELECT id, extracto_id, linea, fecha, concepto, importe FROM __temp__renglon_extracto');
        $this->addSql('DROP TABLE __temp__renglon_extracto');
        $this->addSql('CREATE INDEX IDX_76CB3034B916CD13 ON renglon_extracto (extracto_id)');
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
