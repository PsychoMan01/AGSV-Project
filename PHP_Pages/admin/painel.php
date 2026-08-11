<?php
// PHP_Pages/admin/painel.php
// Painel principal do administrador, com alguns números gerais do sistema

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só administradores podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "ADMIN") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

// Função simples para contar quantas linhas tem uma tabela
function contarLinhas($conn, $nome_tabela) {
    $resultado = mysqli_query($conn, "SELECT COUNT(*) AS total FROM " . $nome_tabela);
    $linha = mysqli_fetch_assoc($resultado);
    return $linha["total"];
}

$estatisticas = array(
    "Competições"  => contarLinhas($conn, "competitions"),
    "Atletas"      => contarLinhas($conn, "athletes"),
    "Clubes"       => contarLinhas($conn, "clubs"),
    "Juízes"       => contarLinhas($conn, "judges"),
    "Utilizadores" => contarLinhas($conn, "users"),
    "Pontuações"   => contarLinhas($conn, "scores"),
);

$pageTitle = "Painel de Administração - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <h1>Painel de Administração</h1>
    <p>Visão geral do sistema AGSV.</p>
</section>

<?php mostrarFlash(); ?>

<section class="stats-grid">
    <?php foreach ($estatisticas as $nome => $valor): ?>
        <div class="stat-card">
            <span class="stat-value"><?php echo $valor; ?></span>
            <span class="stat-label"><?php echo htmlspecialchars($nome); ?></span>
        </div>
    <?php endforeach; ?>
</section>

<section class="admin-links">
    <h2>Gestão</h2>
    <ul>
        <li><a href="competicoes.php">Gerir competições</a></li>
        <li><a href="juizes.php">Gerir juízes</a></li>
        <li><a href="atribuicoes.php">Atribuir juízes a competições</a></li>
        <li><a href="clubes.php">Gerir clubes</a></li>
        <li><a href="atletas.php">Gerir atletas</a></li>
    </ul>
</section>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
