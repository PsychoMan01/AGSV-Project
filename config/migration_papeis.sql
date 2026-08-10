-- config/migration_papeis.sql
-- Só é preciso correr este ficheiro UMA VEZ se já tiveres a base de dados
-- AGSV_db criada antes desta atualização (adiciona o campo "papel" que falta).
-- Se estiveres a criar a base de dados do zero, usa apenas o database.sql,
-- já não precisas deste ficheiro.

use AGSV_db;

alter table judges
    add column papel enum('A','E','DB','DA') after user_id;

alter table judge_assignments
    add column papel enum('A','E','DB','DA') not null default 'A' after category_id;
