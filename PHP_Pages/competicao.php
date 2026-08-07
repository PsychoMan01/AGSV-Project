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
    require __DIR__ . "/../includes/header.php";
    echo "<p>Competição não encontrada.</p>";
    echo "<p><a href='index.php'>Voltar</a></p>";
    require __DIR__ . "/../includes/footer.php";
    exit;
}

$pageTitle = $competicao["name"] . " - " . APP_NAME;
require __DIR__ . "/../includes/header.php";
?>

<section class="page-intro">
    <a href="index.php">&larr; Voltar às competições</a>
    <h1><?php echo htmlspecialchars($competicao["name"]); ?></h1>
    <p>
        <strong>Local:</strong> <?php echo htmlspecialchars($competicao["location"]); ?><br>
        <strong>Datas:</strong>
        <?php echo formatarData($competicao["start_date"]); ?> a
        <?php echo formatarData($competicao["end_date"]); ?>
    </p>
</section>

<section class="results">
    <h2>Resultados</h2>

    <?php if ($competicao["results_published"] != 1): ?>

        <p>Os resultados desta competição ainda não foram publicados.</p>

    <?php else: ?>

        <?php
        // Ir buscar a classificação (rankings) desta competição
        $sql_rankings = "SELECT r.position, r.final_score, a.name AS athlete_name,
                                 c.name AS category_name, e.name AS equipment_name
                          FROM rankings r
                          JOIN athletes a ON a.id = r.athlete_id
                          JOIN categories c ON c.id = r.category_id
                          JOIN equipments e ON e.id = r.equipment_id
                          WHERE r.competition_id = ?
                          ORDER BY e.name, c.name, r.position ASC";

        $stmt2 = mysqli_prepare($conn, $sql_rankings);
        mysqli_stmt_bind_param($stmt2, "i", $id);
        mysqli_stmt_execute($stmt2);
        $resultado_rankings = mysqli_stmt_get_result($stmt2);

        if (mysqli_num_rows($resultado_rankings) == 0):
        ?>
            <p>Ainda não há classificações registadas para esta competição.</p>
        <?php else: ?>

            <?php
            // Agrupar os resultados por "aparelho - escalão" para ficar organizado
            $grupos = array();
            while ($linha = mysqli_fetch_assoc($resultado_rankings)) {
                $chave = $linha["equipment_name"] . " — " . $linha["category_name"];
                if (!isset($grupos[$chave])) {
                    $grupos[$chave] = array();
                }
                $grupos[$chave][] = $linha;
            }
            ?>

            <?php foreach ($grupos as $nome_grupo => $linhas): ?>
                <h3><?php echo htmlspecialchars($nome_grupo); ?></h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Posição</th>
                            <th>Atleta</th>
                            <th>Pontuação Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $linha): ?>
                            <tr>
                                <td><?php echo $linha["position"]; ?>º</td>
                                <td><?php echo htmlspecialchars($linha["athlete_name"]); ?></td>
                                <td><?php echo number_format($linha["final_score"], 3, ",", "."); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>

        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . "/../includes/footer.php"; ?>
