<?php
// PHP_Pages/competicao.php
// Página pública: mostra os detalhes e resultados de UMA competição

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Ler o id da competição que veio no URL (?id=5)
$id = 0;
if (isset($_GET["id"])) {
    $id = (int) $_GET["id"];
}

// Ir buscar a competição
$sql = "SELECT * FROM competitions WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$competicao = mysqli_fetch_assoc($resultado);

// Se não encontrar nenhuma competição com esse id, mostrar mensagem e parar
if (!$competicao) {
    $pageTitle = "Competição não encontrada";
    $pagina_atual = "competicoes_publico";
    require __DIR__ . "/../includes/header.php";
    echo "<div class='empty-state'><i class='fa-regular fa-calendar-xmark' aria-hidden='true'></i>"
       . "<h3>Competição não encontrada</h3><p>A competição pedida não existe ou foi removida.</p>"
       . "<a class='btn' href='competicoes.php'>Ver todas as competições</a></div>";
    require __DIR__ . "/../includes/footer.php";
    exit;
}

$pageTitle = $competicao["name"] . " - " . APP_NAME;
$pagina_atual = "competicoes_publico";
require __DIR__ . "/../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="competicoes.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar às competições</a>
    <h1><?php echo htmlspecialchars($competicao["name"]); ?></h1>
    <ul class="meta-list">
        <?php if ($competicao["location"]): ?>
            <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($competicao["location"] ?? ""); ?></li>
        <?php endif; ?>
        <li><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo formatarData($competicao["start_date"]); ?> a <?php echo formatarData($competicao["end_date"]); ?></li>
        <li><i class="fa-solid fa-tag" aria-hidden="true"></i> <?php echo $competicao["tipo"] == "INTERNA" ? "Interna" : "Regional"; ?></li>
    </ul>
</section>

<section class="results">
    <div class="section-head">
        <div>
            <h2>Classificação geral</h2>
            <p>Atualiza-se automaticamente à medida que os juízes vão submetendo notas.</p>
        </div>
        <span class="live-banner live-banner--live"><span></span> Em direto</span>
    </div>

    <?php
    $classificacao_geral = obterClassificacaoGeral($conn, $id);
    $aparelhos_geral = $classificacao_geral["aparelhos"];
    $linhas_geral = $classificacao_geral["linhas"];
    ?>

    <?php if (count($linhas_geral) == 0): ?>
        <div class="empty-state">
            <i class="fa-solid fa-ranking-star" aria-hidden="true"></i>
            <h3>Ainda sem classificações</h3>
            <p>Ainda não há classificações registadas para esta competição.</p>
        </div>
    <?php else: ?>
        <?php
        // Agrupar as linhas por escalão: cada grupo mostra o nome real do
        // escalão (ex: "Nível 1", "Iniciados", ...). As linhas já vêm
        // ordenadas por escalão (ver obterClassificacaoGeral()).
        $grupos_geral = array();
        foreach ($linhas_geral as $linha) {
            $grupos_geral[$linha["category_id"]][] = $linha;
        }
        ?>
        <?php foreach ($grupos_geral as $linhas_do_nivel): ?>
            <h3 class="nivel-heading"><?php echo htmlspecialchars($linhas_do_nivel[0]["category_name"]); ?></h3>
            <?php require __DIR__ . "/../includes/tabela_classificacao.php"; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . "/../includes/footer.php"; ?>
