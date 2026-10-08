<?php
// PHP_Pages/admin/painel.php
// Painel principal do administrador, com alguns números gerais do sistema

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// ADMIN vê tudo; USER só vê esta página se lhe tiver sido atribuído pelo
// menos um menu (verificado abaixo, ao filtrar a lista de gestão).
requireAdminArea($conn);

$souAdmin = $_SESSION["user_role"] === "ADMIN";

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

// Cada atalho de gestão só aparece se o utilizador tiver mesmo permissão
// para abrir a respetiva página (o ADMIN tem sempre acesso a tudo).
$atalhos_gestao = array(
    array("label" => "Gerir competições", "href" => "competicoes.php", "icone" => "fa-calendar-check", "codigo" => "COMPETICOES_GERIR"),
    array("label" => "Escalões da competição", "href" => "competicao_escaloes.php", "icone" => "fa-sitemap", "codigo" => "COMPETICAO_ESCALOES_GERIR"),
    array("label" => "Gerir juízes", "href" => "juizes.php", "icone" => "fa-gavel", "codigo" => "JUIZES_GERIR"),
    array("label" => "Gerir escalões", "href" => "escaloes.php", "icone" => "fa-layer-group", "codigo" => "ESCALOES_GERIR"),
    array("label" => "Gerir aparelhos", "href" => "aparelhos.php", "icone" => "fa-dumbbell", "codigo" => "APARELHOS_GERIR"),
    array("label" => "Atribuir juízes a competições", "href" => "atribuicoes.php", "icone" => "fa-user-check", "codigo" => "ATRIBUICOES_GERIR"),
    array("label" => "Notas dos juízes", "href" => "notas.php", "icone" => "fa-star-half-stroke", "codigo" => "NOTAS_GERIR"),
    array("label" => "Inscrever atletas em competições", "href" => "inscricoes.php", "icone" => "fa-clipboard-list", "codigo" => "INSCRICOES_GERIR"),
    array("label" => "Gerir clubes", "href" => "clubes.php", "icone" => "fa-building-flag", "codigo" => "CLUBES_GERIR"),
    array("label" => "Gerir atletas", "href" => "atletas.php", "icone" => "fa-person-running", "codigo" => "ATLETAS_GERIR"),
);
$total_atalhos_visiveis = 0;
foreach ($atalhos_gestao as &$atalho) {
    $atalho["visivel"] = temPermissaoTransacao($conn, $atalho["codigo"]);
    if ($atalho["visivel"]) {
        $total_atalhos_visiveis++;
    }
}
unset($atalho);

$pageTitle = "Dashboard - " . APP_NAME;
$pagina_atual = "dashboard";
require __DIR__ . "/../../includes/header.php";
?>

<?php
$estilo_estatisticas = array(
    "Competições"  => array("icone" => "fa-trophy", "tom" => "tone-primary"),
    "Atletas"      => array("icone" => "fa-person-running", "tom" => "tone-accent"),
    "Clubes"       => array("icone" => "fa-building-flag", "tom" => "tone-info"),
    "Juízes"       => array("icone" => "fa-gavel", "tom" => "tone-warning"),
    "Utilizadores" => array("icone" => "fa-users", "tom" => "tone-primary"),
    "Pontuações"   => array("icone" => "fa-star-half-stroke", "tom" => "tone-success"),
);
?>

<section class="page-intro page-intro--hero">
    <div class="live-banner"><span></span> Área administrativa</div>
    <h1>Olá, <?php echo htmlspecialchars(strtok($_SESSION["user_name"] ?? "", " ") ?: "bem-vindo"); ?></h1>
    <p>Gestão das competições, atletas, clubes, juízes e resultados da AGSV.</p>
    <div class="hero-actions">
        <?php if (temPermissaoTransacao($conn, "COMPETICOES_GERIR")): ?>
            <a class="btn btn-primary" href="competicoes.php"><i class="fa-solid fa-trophy" aria-hidden="true"></i> Gerir competições</a>
        <?php endif; ?>
        <?php if (temPermissaoTransacao($conn, "ATLETAS_GERIR")): ?>
            <a class="btn" href="atletas.php"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Novo atleta</a>
        <?php endif; ?>
    </div>
</section>

<?php mostrarFlash(); ?>

<section class="stats-grid" aria-label="Resumo do sistema">
    <?php foreach ($estatisticas as $nome => $valor): ?>
        <?php $estilo = $estilo_estatisticas[$nome]; ?>
        <div class="stat-card <?php echo $estilo["tom"]; ?>">
            <span class="stat-icon"><i class="fa-solid <?php echo $estilo["icone"]; ?>" aria-hidden="true"></i></span>
            <span class="stat-value" data-count="<?php echo (int) $valor; ?>"><?php echo number_format((int) $valor, 0, ",", " "); ?></span>
            <span class="stat-label"><?php echo htmlspecialchars($nome); ?></span>
        </div>
    <?php endforeach; ?>
</section>

<section class="admin-links">
    <div class="section-head">
        <div>
            <h2>Gestão</h2>
            <p>Áreas disponíveis para a tua conta.</p>
        </div>
        <span class="badge badge-active">Sistema ativo</span>
    </div>
    <?php if (!$souAdmin && $total_atalhos_visiveis === 0): ?>
        <p class="hint">Ainda não tens nenhum menu atribuído. Contacta um administrador para configurar as permissões.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($atalhos_gestao as $atalho): ?>
                <?php if ($atalho["visivel"]): ?>
                    <li><a href="<?php echo htmlspecialchars($atalho["href"]); ?>"><span class="stat-icon tone-primary"><i class="fa-solid <?php echo $atalho["icone"]; ?>" aria-hidden="true"></i></span><?php echo htmlspecialchars($atalho["label"]); ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if (temPermissaoTransacao($conn, "RELATORIOS_GERIR")): ?>
<section class="admin-links">
    <h2>Relatórios e análise</h2>
    <ul>
        <li><a href="relatorios.php"><span class="stat-icon tone-success"><i class="fa-solid fa-file-export" aria-hidden="true"></i></span>Criar relatórios e exportar dados</a></li>
    </ul>
</section>
<?php endif; ?>

<?php if ($souAdmin): ?>
<section class="admin-links">
    <h2>Contas e configuração</h2>
    <ul>
        <li><a href="utilizadores.php"><span class="stat-icon tone-accent"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>Utilizadores e permissões</a></li>
        <li><a href="parametrizacoes.php"><span class="stat-icon tone-accent"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>Parametrizações do sistema</a></li>
    </ul>
</section>
<?php endif; ?>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
