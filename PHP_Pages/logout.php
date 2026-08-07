<?php
// PHP_Pages/logout.php
// Termina a sessão do utilizador

require_once __DIR__ . "/../config/db.php";

session_unset();
session_destroy();

// Como a sessão foi destruída, iniciamos uma nova só para mostrar a mensagem
session_start();
$_SESSION["flash_tipo"] = "success";
$_SESSION["flash_mensagem"] = "Sessão terminada com sucesso.";

header("Location: " . BASE_URL . "PHP_Pages/login.php");
exit;
