create database if not exists AGSV_db;
use AGSV_db;

create table if not exists users (
id int auto_increment primary key,
name varchar(150) not null,
user_name varchar(100) not null unique,
user_email varchar(150) not null unique,
password varchar(255) not null,
role enum('ADMIN', 'JUDGE', 'USER') not null default 'USER',
funcao varchar(100) null,
status enum('A','I'),
created_at datetime default current_timestamp
);

create table if not exists clubs (
id int auto_increment primary key,
name varchar(150) not null,
logo varchar(255),
created_at datetime default current_timestamp
);

create table if not exists categories (
id int auto_increment primary key,
name varchar(150) not null,
min_age int,
max_age int
);

create table if not exists equipments (
id int auto_increment primary key,
name varchar(150) not null,
description text
);

create table if not exists equipment_categories (
equipment_id int not null,
category_id int not null,
primary key (equipment_id, category_id),
foreign key (equipment_id) references equipments(id) on delete cascade,
foreign key (category_id) references categories(id) on delete cascade
);

create table if not exists competitions (
status enum('A','I'),
id int auto_increment primary key,
name varchar(150) not null,
location varchar(200),
start_date date,
end_date date,
stream_url varchar(255),
tipo enum('INTERNA','REGIONAL') not null default 'REGIONAL',
created_at datetime default current_timestamp
);

create table if not exists judges (
id int auto_increment primary key,
user_id int,
papel enum('A','E','DB','DA'),
foreign key (user_id) references users(id)
);

create table if not exists scoring_rules (
id int auto_increment primary key,
competition_id int,
method varchar (100),
foreign key (competition_id) references competitions(id)
);

create table if not exists athletes(
id int auto_increment primary key,
name varchar(255) not null,
birth_date date not null,
gender enum('F', 'M', 'N/A'),
place_of_birth varchar(255) not null,
phone_number varchar(255) not null,
email varchar(255) not null,
photo varchar(255),
created_at datetime default current_timestamp,
club_id int,
user_id int,
category_id int,
foreign key (club_id) references clubs(id),
foreign key (user_id) references users(id),
foreign key (category_id) references categories(id)
);

create table if not exists event_entries (
id int auto_increment primary key,
competition_id int,
athlete_id int,
equipment_id int,
category_id int,
bib_number varchar(20),
foreign key (competition_id) references competitions(id),
foreign key(athlete_id) references athletes(id),
foreign key(equipment_id) references equipments(id),
foreign key (category_id) references categories(id),
unique key uq_inscricao (competition_id, athlete_id, category_id, equipment_id)
);

create table if not exists competition_categories (
id int auto_increment primary key,
competition_id int not null,
category_id int not null,
foreign key (competition_id) references competitions(id),
foreign key (category_id) references categories(id),
unique key uq_competition_category (competition_id, category_id)
);

create table if not exists competition_category_equipment (
id int auto_increment primary key,
competition_id int not null,
category_id int not null,
equipment_id int not null,
foreign key (competition_id) references competitions(id),
foreign key (category_id) references categories(id),
foreign key (equipment_id) references equipments(id),
unique key uq_competition_category_equipment (competition_id, category_id, equipment_id)
);

create table if not exists judge_assignments (
id int auto_increment primary key,
judge_id int,
competition_id int,
papel enum('A','E','DB','DA') not null,
numero_juiz int not null default 1,
foreign key (judge_id) references judges(id),
foreign key (competition_id) references competitions(id),
unique key uq_slot_juiz (competition_id, papel, numero_juiz)
);

create table if not exists competition_schedule (
id int auto_increment primary key,
competition_id int,
category_id int,
equipment_id int,
date_time datetime,
order_number int,
foreign key (competition_id) references competitions(id),
foreign key (category_id) references categories(id),
foreign key (equipment_id) references equipments(id)
);

create table if not exists scores (
id int auto_increment primary key,
score_value decimal(5,3),
status enum('A','I'),
papel enum('A','E','DB','DA') not null,
DB1 FLOAT NULL,
DB2 FLOAT NULL,
DA1 FLOAT NULL,
DA2 FLOAT NULL,
A1 FLOAT NULL,
A2 FLOAT NULL,
A3 FLOAT NULL,
A4 FLOAT NULL,
E1 FLOAT NULL,
E2 FLOAT NULL,
E3 FLOAT NULL,
E4 FLOAT NULL,
penal FLOAT NULL,
final_score FLOAT NULL,
pode_editar tinyint(1) not null default 0,
submitted_at datetime,
competition_id int,
event_entry_id int,
athlete_id int,
judge_id int,
equipment_id int,
foreign key (competition_id) references competitions(id),
foreign key (event_entry_id) references event_entries(id),
foreign key (judge_id) references judges(id),
foreign key (equipment_id) references equipments(id) 
);

create table if not exists rankings (
id int auto_increment primary key,
competition_id int,
category_id int,
equipment_id int,
athlete_id int,
final_score decimal(5,3),
position int,
status enum('final', 'parcial') not null default 'final',
calculated_at datetime default current_timestamp,
foreign key (competition_id) references competitions(id),
foreign key (category_id) references categories(id),
foreign key (equipment_id) references equipments(id),
foreign key (athlete_id) references athletes(id)
);

create table if not exists menus (
    id int auto_increment primary key,
    name varchar(100) not null unique,
    label varchar(150) not null,
    route varchar(255) null,
    icon varchar(100) null,
    sort_order int not null default 0,
    active tinyint(1) not null default 1,
    created_at datetime default current_timestamp
);

create table if not exists transactions (
    id int auto_increment primary key,
    menu_id int null,
    code varchar(100) not null unique,
    label varchar(150) not null,
    route varchar(255) not null,
    description varchar(255) null,
    sort_order int not null default 0,
    active tinyint(1) not null default 1,
    created_at datetime default current_timestamp,
    foreign key (menu_id) references menus(id) on delete set null
);

create table if not exists role_transactions (
    id int auto_increment primary key,
    role enum('ADMIN','JUDGE','USER') not null,
    transaction_id int not null,
    created_at datetime default current_timestamp,
    unique key uq_role_transaction (role, transaction_id),
    foreign key (transaction_id) references transactions(id) on delete cascade
);

create table if not exists user_transactions (
    id int auto_increment primary key,
    user_id int not null,
    transaction_id int not null,
    created_at datetime default current_timestamp,
    unique key uq_user_transaction (user_id, transaction_id),
    foreign key (user_id) references users(id) on delete cascade,
    foreign key (transaction_id) references transactions(id) on delete cascade
);

insert ignore into menus (name,label,route,sort_order) values
('ADMIN_GESTAO','Gestão','PHP_Pages/admin/painel.php',10);

insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'COMPETICOES_GERIR','Gerir competições','PHP_Pages/admin/competicoes.php','Criar e gerir competições',10
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'JUIZES_GERIR','Gerir juízes','PHP_Pages/admin/juizes.php','Criar e gerir juízes',20
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'UTILIZADORES_GERIR','Gerir utilizadores','PHP_Pages/admin/utilizadores.php','Criar, editar e ativar/desativar utilizadores',30
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'PARAMETRIZACOES_GERIR','Parametrizações','PHP_Pages/admin/parametrizacoes.php','Definir os menus a que cada utilizador tem acesso',35
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'ESCALOES_GERIR','Gerir escalões','PHP_Pages/admin/escaloes.php','Gerir escalões',40
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'APARELHOS_GERIR','Gerir aparelhos','PHP_Pages/admin/aparelhos.php','Gerir aparelhos e respetivas categorias',50
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'ATRIBUICOES_GERIR','Atribuir juízes','PHP_Pages/admin/atribuicoes.php','Atribuir juízes às competições',60
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'COMPETICAO_ESCALOES_GERIR','Escalões da competição','PHP_Pages/admin/competicao_escaloes.php','Escolher os escalões de uma competição e os aparelhos usados em cada um',65
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'INSCRICOES_GERIR','Gerir inscrições','PHP_Pages/admin/inscricoes.php','Gerir inscrições dos atletas',70
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'CLUBES_GERIR','Gerir clubes','PHP_Pages/admin/clubes.php','Gerir clubes',80
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'ATLETAS_GERIR','Gerir atletas','PHP_Pages/admin/atletas.php','Gerir atletas',90
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'NOTAS_GERIR','Notas dos juízes','PHP_Pages/admin/notas.php','Ver as notas submetidas e autorizar a edição pontual de uma nota',100
from menus where name='ADMIN_GESTAO';
insert ignore into transactions (menu_id,code,label,route,description,sort_order)
select id,'RELATORIOS_GERIR','Criar relatórios','PHP_Pages/admin/relatorios.php','Exportar relatórios em CSV (atletas, competições, classificações)',110
from menus where name='ADMIN_GESTAO';

insert ignore into role_transactions (role,transaction_id)
select 'ADMIN', id from transactions;

-- Base de dados já existente de uma versão anterior deste script: acrescenta
-- colunas sem apagar nada. Em vez de "ADD COLUMN IF NOT EXISTS" (que só
-- funciona em MySQL 8.0.29+ / MariaDB 10.5+), verifica-se a existência da
-- coluna em information_schema e só se executa o ALTER se for preciso —
-- isto funciona em qualquer versão de MySQL ou MariaDB.

SET @coluna_existe = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'scores' AND column_name = 'pode_editar'
);
SET @sql_pode_editar = IF(@coluna_existe = 0,
    'ALTER TABLE scores ADD COLUMN pode_editar TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt_pode_editar FROM @sql_pode_editar;
EXECUTE stmt_pode_editar;
DEALLOCATE PREPARE stmt_pode_editar;

-- Tipo de competição: interna (dentro do próprio clube) ou regional (entre
-- clubes). Competições já existentes ficam como 'REGIONAL' por omissão.
SET @coluna_existe = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'competitions' AND column_name = 'tipo'
);
SET @sql_tipo = IF(@coluna_existe = 0,
    "ALTER TABLE competitions ADD COLUMN tipo ENUM('INTERNA','REGIONAL') NOT NULL DEFAULT 'REGIONAL'",
    'SELECT 1');
PREPARE stmt_tipo FROM @sql_tipo;
EXECUTE stmt_tipo;
DEALLOCATE PREPARE stmt_tipo;
