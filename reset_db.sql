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

INSERT INTO "bank_xlsstructure" VALUES(1,1,NULL,NULL,NULL,21,6,7,1,NULL);
INSERT INTO "bank_xlsstructure" VALUES(2,2,NULL,NULL,NULL,13,1,5,2,'d/m/Y');