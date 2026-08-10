<?php
// config/db.php
// Definições gerais do site + ligação à base de dados (tudo num único ficheiro)

session_start();

// Nome do site (para aparecer no título das páginas)
define("APP_NAME", "AGSV - Sistema de Gestão de Competições de Ginástica");

// Caminho base do site (deixa "/" se o projeto estiver na raiz do servidor)
define("BASE_URL", "/");

// Dados de acesso à base de dados
$db_host = "localhost";
$db_user = "root";
$db_pass = "mysql";
$db_name = "AGSV_db";

// Ligação simples ao MySQL
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Erro ao ligar à base de dados: " . mysqli_connect_error());
}

// Definir o conjunto de caracteres para suportar acentos (ã, ç, etc.)
mysqli_set_charset($conn, "utf8mb4");
