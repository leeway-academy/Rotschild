delete from applied_check;

delete from cheque_emitido;

delete from extracto_bancario;

delete from gasto_fijo;

delete from movimiento;

delete from renglon_extracto;

delete from saldo_bancario;

delete from bank;

INSERT INTO "bank" VALUES(1,'Banco Río',72);
INSERT INTO "bank" VALUES(2,'Banco Nación',11);

delete from bank_xlsstructure;

INSERT INTO bank_xlsstructure (id, banco_id, stop_word, first_header, extra_data_col, first_row, concept_col, amount_col, date_col, date_format) VALUES (1, 1, null, 'Últimos Movimientos', null, 5, 6, 7, 1, 'm/d/Y');
INSERT INTO bank_xlsstructure (id, banco_id, stop_word, first_header, extra_data_col, first_row, concept_col, amount_col, date_col, date_format) VALUES (2, 2, null, null, null, 15, 1, 5, 2, 'd/m/Y');