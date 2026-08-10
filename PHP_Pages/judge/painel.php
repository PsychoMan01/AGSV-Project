<?php
// PHP_Pages/judge/painel.php
// Painel do juiz: mostra as competições/aparelhos/escalões atribuídos a ele

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só juízes podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "JUDGE") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

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

    $sql2 = "SELECT ja.id AS assignment_id, comp.name AS competition_name,
                     cat.name AS category_name, eq.name AS equipment_name
              FROM judge_assignments ja
              JOIN competitions comp ON comp.id = ja.competition_id
              JOIN categories cat ON cat.id = ja.category_id
              JOIN equipments eq ON eq.id = ja.equipment_id
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
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <h1>Painel do Juiz</h1>
    <p>As tuas atribuições de competições, escalões e aparelhos.</p>
</section>

<?php mostrarFlash(); ?>

<?php if (!$juiz): ?>

    <div class="alert alert-error">
        Não existe um registo de juiz associado a esta conta. Fala com o administrador.
    </div>

<?php elseif (count($atribuicoes) == 0): ?>

    <p>Ainda não tens atribuições de competições.</p>

<?php else: ?>

    <table class="data-table">
        <thead>
            <tr>
                <th>Competição</th>
                <th>Escalão</th>
                <th>Aparelho</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($atribuicoes as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a["competition_name"]); ?></td>
                    <td><?php echo htmlspecialchars($a["category_name"]); ?></td>
                    <td><?php echo htmlspecialchars($a["equipment_name"]); ?></td>
                    <td>
                        <a class="btn btn-small" href="submeter_pontuacao.php?assignment_id=<?php echo $a['assignment_id']; ?>">
                            Pontuar atletas
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
