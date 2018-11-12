<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181112183412 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX UNIQ_57B3CF5ECC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__bank_xlsstructure AS SELECT id, banco_id, stop_word, first_row, concept_col, amount_col, date_col, date_format FROM bank_xlsstructure');
        $this->addSql('DROP TABLE bank_xlsstructure');
        $this->addSql('CREATE TABLE bank_xlsstructure (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, stop_word VARCHAR(255) DEFAULT NULL COLLATE BINARY, first_row SMALLINT NOT NULL, concept_col SMALLINT NOT NULL, amount_col SMALLINT NOT NULL, date_col SMALLINT NOT NULL, date_format VARCHAR(255) DEFAULT NULL, first_header VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_57B3CF5ECC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO bank_xlsstructure (id, banco_id, stop_word, first_row, concept_col, amount_col, date_col, date_format) SELECT id, banco_id, stop_word, first_row, concept_col, amount_col, date_col, date_format FROM __temp__bank_xlsstructure');
        $this->addSql('DROP TABLE __temp__bank_xlsstructure');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57B3CF5ECC04A73E ON bank_xlsstructure (banco_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX UNIQ_57B3CF5ECC04A73E');
        $this->addSql('CREATE TEMPORARY TABLE __temp__bank_xlsstructure AS SELECT id, banco_id, first_row, stop_word, concept_col, amount_col, date_col, date_format FROM bank_xlsstructure');
        $this->addSql('DROP TABLE bank_xlsstructure');
        $this->addSql('CREATE TABLE bank_xlsstructure (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, banco_id INTEGER NOT NULL, stop_word VARCHAR(255) DEFAULT NULL, first_row SMALLINT NOT NULL, concept_col SMALLINT NOT NULL, amount_col SMALLINT NOT NULL, date_col SMALLINT NOT NULL, date_format VARCHAR(255) DEFAULT NULL COLLATE BINARY)');
        $this->addSql('INSERT INTO bank_xlsstructure (id, banco_id, first_row, stop_word, concept_col, amount_col, date_col, date_format) SELECT id, banco_id, first_row, stop_word, concept_col, amount_col, date_col, date_format FROM __temp__bank_xlsstructure');
        $this->addSql('DROP TABLE __temp__bank_xlsstructure');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57B3CF5ECC04A73E ON bank_xlsstructure (banco_id)');
    }
}
