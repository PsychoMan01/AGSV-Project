create database AGSV_db;

create table users (
id int auto_increment primary key,
name varchar(150) not null,
user_name varchar(100) not null unique,
user_email varchar(150) not null unique,
password varchar(255) not null,
role enum('ADMIN', 'JUDGE', 'USER') not null default 'USER',
status enum('A','I'),
created_at datetime default current_timestamp
);

create table clubs (
id int auto_increment primary key,
name varchar(150) not null,
logo varchar(255),
created_at datetime default current_timestamp
);

CREATE TABLE escalao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL
);

create table equipments (
id auto_increment primary key,
name varchar(150) not null,
description text
);

create table categories (
id int auto_increment primary key,
name varchar(150) not null,
min_age int,
max_age int
);

create table competitions (
status enum('A','I'),
id int auto_increment primary key,
name varchar(150) not null,
location varchar(200),
start_date date,
end_date date,
stream_url varchar(255),
status tinyint unsigned not null default 1,
results_published boolean default false,
created_at datetime default current_timestamp
);

create table judges (
id int auto_increment primary key,
user_id int,
foreign key (user_id) references users(id)
);

create table scoring_rules(
id int auto_increment primary key,
competition_id int,
method varchar (100),
foreign key (competition_id) references competitions(id)
):

create table athletes(
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
foreign key (club_id) referneces clubs(id),
foreign key (user_id) references users(id),
foreign key (category_id) references categories(id)
);

creat table event_entries (
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

create table judge_assignments (
id int auto_increment primary key,
judge_id int,
competition_id int,
equipment_id int,
category_id int,
foreign key (judge_id) references judges(id),
foreign key (competition_id) references competitions(id),
foreign key (equipment_id) references equipments(id),
foreign key (category_id) references categories(id)
);

create table competition_schedule (
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

create table scores (
id int auto_increment primary key,
score_value decimal(5,3),
status enum('A','I'),
DB1 FLOAT NOT NULL,
DB2 FLOAT NOT NULL,
DA1 FLOAT NOT NULL,
DA2 FLOAT NOT NULL,
A1 FLOAT NOT NULL,
A2 FLOAT NOT NULL,
A3 FLOAT NOT NULL,
A4 FLOAT NOT NULL,
E1 FLOAT NOT NULL,
E2 FLOAT NOT NULL,
E3 FLOAT NOT NULL,
E4 FLOAT NOT NULL,
penal FLOAT NOT NULL,
final_score FLOAT NOT NULL,
submitted_at datetime,
competition_id int,
event_entry_id int,
athlete_id int,
judge_id int,
equipment_id int,
foreign key (competition_id) references competitions(id)
foreign key (event_entry_id) references event_entries(id),
foreign key (judge_id) references judges(id),
foreign key (equipment_id) references equipments(id) 
);

create table rankings (
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
