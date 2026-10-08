<?php
// PHP_Pages/index.php
// Página pública "Home": mensagem de boas-vindas + classificação geral do
// campeonato que está a decorrer hoje (se houver algum)

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$pageTitle = "Home - " . APP_NAME;
$pagina_atual = "inicio";

// Competição (se alguma) cujo intervalo de datas inclui o dia de hoje
$competicao_hoje = obterCompeticaoDeHoje($conn);

$classificacao_hoje = null;
if ($competicao_hoje) {
    $classificacao_hoje = obterClassificacaoGeral($conn, $competicao_hoje["id"]);
}

require __DIR__ . "/../includes/header.php";
?>

<section class="page-intro page-intro--hero">
    <div class="live-banner"><span></span> Associação de Ginástica de São Vicente</div>
    <h1>Competições e resultados da AGSV</h1>
    <p>Consulta as competições, os escalões e as classificações disponibilizadas pela Associação de Ginástica de São Vicente.</p>
    <div class="hero-actions">
        <a class="btn btn-primary" href="competicoes.php"><i class="fa-solid fa-trophy" aria-hidden="true"></i> Ver competições</a>
        <?php if ($competicao_hoje): ?>
            <a class="btn" href="competicao.php?id=<?php echo (int)$competicao_hoje['id']; ?>"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Resultados de hoje</a>
        <?php endif; ?>
    </div>
</section>

<?php mostrarFlash(); ?>

<section class="results">
    <div class="section-head">
        <div>
            <h2>Competição de hoje</h2>
            <p>Resultados registados no sistema até ao momento.</p>
        </div>
        <?php if ($competicao_hoje): ?><span class="badge badge-active">Em acompanhamento</span><?php endif; ?>
    </div>

    <?php if (!$competicao_hoje): ?>
        <div class="empty-state">
            <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
            <h3>Não há competição a decorrer hoje</h3>
            <p>Consulta o calendário para veres os próximos eventos e os resultados das competições anteriores.</p>
            <a class="btn" href="competicoes.php">Consultar calendário</a>
        </div>
    <?php else: ?>
        <div class="competition-hero">
            <div class="card">
                <div class="live-banner live-banner--live"><span></span> Em curso</div>
                <h3><?php echo htmlspecialchars($competicao_hoje["name"]); ?></h3>
                <?php if ($competicao_hoje["location"]): ?>
                    <ul class="meta-list">
                        <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($competicao_hoje["location"] ?? ""); ?></li>
                    </ul>
                <?php endif; ?>
                <a class="btn btn-primary" href="competicao.php?id=<?php echo (int)$competicao_hoje['id']; ?>">Abrir competição <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="card hero-stat">
                <span class="stat-label">Atletas com classificação</span>
                <strong><?php echo count($classificacao_hoje["linhas"]); ?></strong>
                <span class="stat-label">Registos disponíveis</span>
            </div>
        </div>

        <?php
        $aparelhos_geral = $classificacao_hoje["aparelhos"];
        $linhas_geral = $classificacao_hoje["linhas"];
        ?>
        <?php if (count($linhas_geral) == 0): ?>
            <div class="empty-state">
                <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                <h3>Ainda sem atletas inscritos</h3>
                <p>Assim que existirem inscrições e notas, a classificação aparece aqui.</p>
            </div>
        <?php else: ?>
            <?php
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
    <?php endif; ?>
</section>

<?php
// Galeria: fotografias e cartazes de eventos anteriores da AGSV (includes/img).
// As imagens grandes têm uma miniatura em includes/img/miniaturas/ para a grelha.
$galeria = array(
    array("ficheiro" => "475764630_603053405809314_3343631383308679218_n.jpg", "legenda" => "Ginastas da AGSV no pavilhão"),
    array("ficheiro" => "475786162_603053129142675_8147033643166520973_n.jpg", "legenda" => "Equipa de ginástica rítmica da AGSV"),
    array("ficheiro" => "648270985_910629025051749_5433434954769225549_n.jpg", "legenda" => "Cartaz do Torneio da Abertura de Ginástica Rítmica"),
    array("ficheiro" => "496302339_678680051579982_4040609264216813280_n.jpg", "legenda" => "Cartaz do Torneio de Abertura de Ginástica Rítmica no Mindelo"),
    array("ficheiro" => "672670399_943322591782392_5663540193090320889_n.jpg", "legenda" => "Cartaz de torneio de Ginástica Rítmica"),
    array("ficheiro" => "516690750_720471410734179_4338687188960341272_n.jpg", "legenda" => "Cartaz da Taça de São Vicente de Rítmica e Acrobacia"),
    array("ficheiro" => "511285421_711767848271202_8836970811230178007_n.jpg", "legenda" => "Cartaz do evento Zum Zum Conjunto 2025 de aeróbica e acrobática"),
    array("ficheiro" => "641607335_903736605740991_2527217983906173332_n.jpg", "legenda" => "Cartaz do espetáculo O Mundo Mágico"),
    array("ficheiro" => "480787855_621140827333905_722847868447336400_n.jpg", "legenda" => "Cartaz de Ginástica Rítmica 2024 no Dia da Criança"),
    array("ficheiro" => "594012494_840323652082287_8537884208649182247_n.jpg", "legenda" => "Cartaz do espetáculo Magia do Natal 2025"),
    array("ficheiro" => "598564704_848942994553686_3939952396731538851_n.jpg", "legenda" => "Cartaz do Espetáculo de Natal 2025"),
);
$pasta_galeria = __DIR__ . "/../includes/img/";
$galeria = array_values(array_filter($galeria, function ($foto) use ($pasta_galeria) {
    return is_file($pasta_galeria . $foto["ficheiro"]);
}));
?>

<?php if (count($galeria) > 0): ?>
<section class="results">
    <div class="section-head">
        <div>
            <h2>Momentos e eventos</h2>
            <p>Fotografias e cartazes de eventos realizados pela AGSV e pelos clubes de São Vicente.</p>
        </div>
    </div>

    <ul class="gallery" data-gallery>
        <?php foreach ($galeria as $foto): ?>
            <?php
            $url_original = BASE_URL . "includes/img/" . rawurlencode($foto["ficheiro"]);
            $tem_miniatura = is_file($pasta_galeria . "miniaturas/" . $foto["ficheiro"]);
            $url_miniatura = $tem_miniatura ? BASE_URL . "includes/img/miniaturas/" . rawurlencode($foto["ficheiro"]) : $url_original;
            ?>
            <li>
                <a class="gallery-item" href="<?php echo $url_original; ?>" data-gallery-item data-legenda="<?php echo htmlspecialchars($foto["legenda"]); ?>">
                    <img src="<?php echo $url_miniatura; ?>" alt="<?php echo htmlspecialchars($foto["legenda"]); ?>"
                         width="206" height="206" loading="lazy" decoding="async">
                    <span class="gallery-zoom" aria-hidden="true"><i class="fa-solid fa-expand"></i></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php require __DIR__ . "/../includes/footer.php"; ?>
