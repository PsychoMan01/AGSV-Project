<?php
// PHP_Pages/judge/painel.php
// Painel do juiz: mostra as competições/aparelhos/escalões atribuídos a ele

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só juízes com conta ativa podem ver esta página
requireActiveJudge($conn);

$user_id = $_SESSION["user_id"];

// Cada utilizador com role JUDGE tem um registo associado na tabela "judges"
$sql = "SELECT id FROM judges WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$juiz = mysqli_fetch_assoc($resultado);

$atribuicoes = array();

if ($juiz) {
    $judge_id = $juiz["id"];

    $sql2 = "SELECT ja.id AS assignment_id, ja.papel, ja.numero_juiz,
                     comp.name AS competition_name
              FROM judge_assignments ja
              JOIN competitions comp ON comp.id = ja.competition_id
              WHERE ja.judge_id = ?
              ORDER BY comp.start_date DESC";

    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, "i", $judge_id);
    mysqli_stmt_execute($stmt2);
    $resultado2 = mysqli_stmt_get_result($stmt2);

    while ($linha = mysqli_fetch_assoc($resultado2)) {
        $atribuicoes[] = $linha;
    }
}

$pageTitle = "Painel do Juiz - " . APP_NAME;
$pagina_atual = "painel";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <div class="live-banner"><span></span> Área de julgamento</div>
    <h1>Painel do juiz</h1>
    <p>Consulta as tuas atribuições e entra diretamente no ambiente de pontuação de cada competição.</p>
</section>

<?php mostrarFlash(); ?>

<?php if (!$juiz): ?>
    <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div class="alert-body">Não existe um registo de juiz associado a esta conta. Contacta um administrador.</div></div>
<?php elseif (count($atribuicoes) == 0): ?>
    <section class="card">
        <div class="empty-state">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <h3>Sem atribuições neste momento</h3>
            <p>Quando fores associado a uma competição, ela aparecerá automaticamente nesta área.</p>
        </div>
    </section>
<?php else: ?>
    <section class="card">
        <div class="section-head">
            <div>
                <h2>As tuas competições</h2>
                <p>Seleciona uma atribuição para começar a pontuar.</p>
            </div>
            <span class="badge badge-active"><?php echo count($atribuicoes); ?> atribuição<?php echo count($atribuicoes) === 1 ? '' : 'ões'; ?></span>
        </div>
        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Competição</th>
                        <th scope="col">Função</th>
                        <th scope="col">Número</th>
                        <th scope="col"><span class="sr-only">Ação</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($atribuicoes as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a["competition_name"]); ?></td>
                        <td data-label="Função"><?php echo htmlspecialchars(papelLabel($a["papel"])); ?></td>
                        <td data-label="Número"><span class="badge badge-code"><?php echo htmlspecialchars(campoNumeroLabel($a["papel"], $a["numero_juiz"])); ?></span></td>
                        <td class="actions-cell">
                            <a class="btn btn-primary btn-small" href="submeter_pontuacao.php?assignment_id=<?php echo (int)$a['assignment_id']; ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Pontuar atletas</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
