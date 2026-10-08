<?php
// PHP_Pages/admin/relatorios.php
// Dashboard > Criar relatórios: exportação de dados em CSV

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

requireAdminArea($conn);
requireTransaction($conn, "RELATORIOS_GERIR");

// Lista de competições, para o relatório de classificações escolher uma
$resultado_competicoes = mysqli_query($conn, "SELECT id, name FROM competitions ORDER BY start_date DESC");
$competicoes = array();
while ($linha = mysqli_fetch_assoc($resultado_competicoes)) {
    $competicoes[] = $linha;
}

// --- Geração do CSV pedido (só quando ?exportar=... vem no URL) ---
if (isset($_GET["exportar"])) {

    $exportar = $_GET["exportar"];

    if ($exportar == "atletas") {
        $sql = "SELECT a.name AS Nome, a.birth_date AS DataNascimento, a.gender AS Genero,
                       c.name AS Clube, cat.name AS Escalao, a.email AS Email, a.phone_number AS Telefone
                FROM athletes a
                LEFT JOIN clubs c ON c.id = a.club_id
                LEFT JOIN categories cat ON cat.id = a.category_id
                ORDER BY a.name";
        $resultado = mysqli_query($conn, $sql);
        exportarCsv("atletas.csv", $resultado);
    }

    if ($exportar == "competicoes") {
        $sql = "SELECT name AS Nome, location AS Local, tipo AS Tipo,
                       start_date AS DataInicio, end_date AS DataFim, status AS Estado
                FROM competitions
                ORDER BY start_date DESC";
        $resultado = mysqli_query($conn, $sql);
        exportarCsv("competicoes.csv", $resultado);
    }

    if ($exportar == "classificacoes") {
        $competition_id = (int) ($_GET["competition_id"] ?? 0);
        $classificacao = obterClassificacaoGeral($conn, $competition_id);

        $linhas_csv = array();
        foreach ($classificacao["linhas"] as $linha) {
            $linha_csv = array(
                "Posicao" => $linha["posicao"] !== null ? $linha["posicao"] : "--",
                "Nome"    => $linha["athlete_name"],
                "Escalao" => $linha["category_name"],
            );
            foreach ($classificacao["aparelhos"] as $aparelho) {
                $nota = $linha["notas_por_aparelho"][$aparelho["id"]];
                $linha_csv[$aparelho["name"]] = $nota !== null ? $nota : "--";
            }
            $linha_csv["PontuacaoFinal"] = $linha["ja_pontuado_algures"] ? $linha["pontuacao_final"] : "--";
            $linhas_csv[] = $linha_csv;
        }

        exportarCsvArray("classificacoes.csv", $linhas_csv);
    }

    exit;
}

$pageTitle = "Relatórios - " . APP_NAME;
$pagina_atual = "dashboard";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Criar Relatórios</h1>
    <p>Exporta dados do sistema em ficheiros CSV, prontos a abrir no Excel ou Google Sheets.</p>
</section>

<?php mostrarFlash(); ?>

<section class="admin-links">
    <h2>Relatórios gerais</h2>
    <ul>
        <li><a href="relatorios.php?exportar=atletas" download><span class="stat-icon tone-accent"><i class="fa-solid fa-person-running" aria-hidden="true"></i></span>Exportar lista de atletas (CSV)</a></li>
        <li><a href="relatorios.php?exportar=competicoes" download><span class="stat-icon tone-primary"><i class="fa-solid fa-trophy" aria-hidden="true"></i></span>Exportar lista de competições (CSV)</a></li>
    </ul>
</section>

<section class="form-card">
    <h2>Classificação geral de uma competição</h2>

    <?php if (count($competicoes) == 0): ?>
        <div class="empty-state">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <p>Ainda não existem competições registadas.</p>
        </div>
    <?php else: ?>
        <form method="get" action="relatorios.php" data-no-lock>
            <input type="hidden" name="exportar" value="classificacoes">

            <label for="competition_id">Competição</label>
            <select id="competition_id" name="competition_id" required>
                <?php foreach ($competicoes as $comp): ?>
                    <option value="<?php echo $comp['id']; ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i> Exportar classificação (CSV)</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
