<?php
// includes/header.php
// Topo da página + menu, incluído em todas as páginas do site.

if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>CSS/style.css">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?php echo BASE_URL; ?>PHP_Pages/index.php">AGSV</a>

        <nav>
            <ul class="nav-menu">
                <li><a href="<?php echo BASE_URL; ?>PHP_Pages/index.php">Competições</a></li>

                <?php if (isset($_SESSION["user_id"])): ?>

                    <?php if ($_SESSION["user_role"] == "ADMIN"): ?>
                        <li><a href="<?php echo BASE_URL; ?>PHP_Pages/admin/painel.php">Painel Admin</a></li>
                        <li><a href="<?php echo BASE_URL; ?>PHP_Pages/admin/competicoes.php">Competições (gerir)</a></li>
                    <?php endif; ?>

                    <?php if ($_SESSION["user_role"] == "JUDGE"): ?>
                        <li><a href="<?php echo BASE_URL; ?>PHP_Pages/judge/painel.php">Painel do Juiz</a></li>
                    <?php endif; ?>

                    <li>
                        <span class="user-pill">
                            Olá, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
                            (<?php echo htmlspecialchars($_SESSION["user_role"]); ?>)
                        </span>
                    </li>
                    <li><a href="<?php echo BASE_URL; ?>PHP_Pages/logout.php">Sair</a></li>

                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>PHP_Pages/login.php">Entrar</a></li>
                <?php endif; ?>

            </ul>
        </nav>
    </div>
</header>

<main class="container">
