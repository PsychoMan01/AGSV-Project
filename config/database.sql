create database if not exists AGSV_db;

create table if not exists users (
id int auto_increment primary key,
name varchar(150) not null,
user_name varchar(100) not null unique,
user_email varchar(150) not null unique,
password varchar(255) not null,
role enum('ADMIN', 'JUDGE', 'USER') not null default 'USER',
status enum('A','I'),
created_at datetime default current_timestamp
);

create table if not exists clubs (
id int auto_increment primary key,
name varchar(150) not null,
logo varchar(255),
created_at datetime default current_timestamp
);

create table if not exists equipments (
id int auto_increment primary key,
name varchar(150) not null,
description text
);

create table if not exists categories (
id int auto_increment primary key,
name varchar(150) not null,
min_age int,
max_age int
);

create table if not exists competitions (
status enum('A','I'),
id int auto_increment primary key,
name varchar(150) not null,
location varchar(200),
start_date date,
end_date date,
stream_url varchar(255),
results_published boolean default false,
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
foreign key (category_id) references categories(id)
);

create table if not exists judge_assignments (
id int auto_increment primary key,
judge_id int,
competition_id int,
equipment_id int,
category_id int,
papel enum('A','E','DB','DA') not null,
foreign key (judge_id) references judges(id),
foreign key (competition_id) references competitions(id),
foreign key (equipment_id) references equipments(id),
foreign key (category_id) references categories(id)
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
-- Cada juiz só pontua os campos do seu papel (A, E, DB ou DA), por isso
-- estas colunas passam a poder ficar a NULL (o que o juiz não avalia).
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
calculated_at datetime default current_timestamp,
foreign key (competition_id) references competitions(id),
foreign key (category_id) references categories(id),
foreign key (equipment_id) references equipments(id),
foreign key (athlete_id) references athletes(id)
);

-- ==========================================================================
-- MIGRAÇÃO (só é preciso correr isto se a base de dados já existia antes
-- destas alterações, ou seja, se já tinhas a tabela "scores" criada).
-- Se estás a criar a base de dados de raiz com este ficheiro, podes ignorar.
-- ==========================================================================
-- A tabela "escalao" nunca chegou a ser usada (o sistema usa "categories"
-- para os escalões) — se já a tinhas criado, podes removê-la:
-- DROP TABLE IF EXISTS escalao;
-- ALTER TABLE scores ADD COLUMN papel ENUM('A','E','DB','DA') NOT NULL AFTER status;
-- ALTER TABLE scores MODIFY DB1 FLOAT NULL;
-- ALTER TABLE scores MODIFY DB2 FLOAT NULL;
-- ALTER TABLE scores MODIFY DA1 FLOAT NULL;
-- ALTER TABLE scores MODIFY DA2 FLOAT NULL;
-- ALTER TABLE scores MODIFY A1 FLOAT NULL;
-- ALTER TABLE scores MODIFY A2 FLOAT NULL;
-- ALTER TABLE scores MODIFY A3 FLOAT NULL;
-- ALTER TABLE scores MODIFY A4 FLOAT NULL;
-- ALTER TABLE scores MODIFY E1 FLOAT NULL;
-- ALTER TABLE scores MODIFY E2 FLOAT NULL;
-- ALTER TABLE scores MODIFY E3 FLOAT NULL;
-- ALTER TABLE scores MODIFY E4 FLOAT NULL;
-- ALTER TABLE scores MODIFY penal FLOAT NULL;
-- ALTER TABLE scores MODIFY final_score FLOAT NULL;
