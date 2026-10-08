<?php
// PHP_Pages/admin/notas.php
// Área onde o administrador consulta as notas já submetidas pelos juízes
// numa competição e pode autorizar, pontualmente, a edição de uma nota
// específica (scores.pode_editar). Por omissão uma nota já submetida fica
// bloqueada para o juiz — só volta a ficar editável depois desta
// autorização, e a autorização é de uso único: assim que o juiz grava a
// correção, a nota volta a ficar bloqueada automaticamente (ver
// PHP_Pages/judge/submeter_pontuacao.php).

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

requireAdminArea($conn);
requireTransaction($conn, "NOTAS_GERIR");
exigirCsrf();

$competition_id = isset($_GET["competition_id"]) ? (int) $_GET["competition_id"] : 0;
if (isset($_POST["competition_id"])) {
    $competition_id = (int) $_POST["competition_id"];
}

// --- Autorizar ou voltar a bloquear a edição de uma nota específica ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && in_array($_POST["acao"] ?? "", array("autorizar", "bloquear"), true)) {
    $score_id = (int) ($_POST["score_id"] ?? 0);
    $novo_valor = ($_POST["acao"] === "autorizar") ? 1 : 0;

    // Confirmar que a nota pertence mesmo a esta competição, para não ser
    // possível manipular o id e alterar uma nota de outra competição.
    $stmt = mysqli_prepare($conn, "UPDATE scores SET pode_editar = ? WHERE id = ? AND competition_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $novo_valor, $score_id, $competition_id);
    $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;

    if ($ok) {
        setFlash("success", $novo_valor === 1 ? "Edição autorizada. O juiz já pode corrigir esta nota." : "Nota bloqueada novamente.");
    } else {
        setFlash("error", "Não foi possível atualizar esta nota.");
    }

    header("Location: notas.php?competition_id=" . $competition_id);
    exit;
}

// --- Ainda não escolheu uma competição: mostrar a lista para escolher ---
if ($competition_id === 0) {
    $resultado_competicoes = mysqli_query($conn, "SELECT * FROM competitions ORDER BY start_date DESC");
    $pageTitle = "Notas dos Juízes - " . APP_NAME;
    $pagina_atual = "admin_competicoes";
    require __DIR__ . "/../../includes/header.php";

    $titulo_pagina = "Notas dos Juízes";
    $descricao_pagina = "Escolhe a competição para ver as notas já submetidas e autorizar a edição de alguma, se for preciso.";
    $pagina_destino = "notas.php";
    $rotulo_acao = "Ver notas";
    require __DIR__ . "/../../includes/escolher_competicao.php";
    require __DIR__ . "/../../includes/footer.php";
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM competitions WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $competition_id);
mysqli_stmt_execute($stmt);
$competicao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$competicao) {
    setFlash("error", "Competição não encontrada.");
    header("Location: notas.php");
    exit;
}

// Lista as notas já submetidas nesta competição. O número de juiz
// (numero_juiz) vem de judge_assignments, para calcularmos o campo exato
// que o juiz preencheu (ex: "A2") a partir do papel + número.
$sql = "SELECT s.id, s.score_value, s.pode_editar, s.submitted_at, s.papel,
               u.name AS judge_name, a.name AS athlete_name, eq.name AS equipment_name,
               ja.numero_juiz
        FROM scores s
        JOIN judges j ON j.id = s.judge_id
        JOIN users u ON u.id = j.user_id
        JOIN athletes a ON a.id = s.athlete_id
        LEFT JOIN equipments eq ON eq.id = s.equipment_id
        LEFT JOIN judge_assignments ja ON ja.judge_id = s.judge_id AND ja.competition_id = s.competition_id
        WHERE s.competition_id = ?
        ORDER BY u.name, a.name, eq.name";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $competition_id);
mysqli_stmt_execute($stmt);
$resultado_notas = mysqli_stmt_get_result($stmt);

$pageTitle = "Notas dos Juízes - " . APP_NAME;
$pagina_atual = "admin_competicoes";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="competicoes.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar às competições</a>
    <h1>Notas dos Juízes</h1>
    <ul class="meta-list">
        <li><i class="fa-solid fa-trophy" aria-hidden="true"></i> <strong><?php echo htmlspecialchars($competicao["name"]); ?></strong></li>
    </ul>
    <p class="hint">Por omissão uma nota já submetida fica bloqueada para o juiz. Autoriza a edição só quando for mesmo preciso corrigir — a autorização serve para uma única correção e depois volta a bloquear-se sozinha.</p>
</section>

<?php mostrarFlash(); ?>

<section class="competitions-list">
    <h2>Notas submetidas<span class="count-pill"><?php echo mysqli_num_rows($resultado_notas); ?></span></h2>
    <?php if (mysqli_num_rows($resultado_notas) === 0): ?>
        <div class="empty-state">
            <i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i>
            <p>Ainda não há notas submetidas nesta competição.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Juiz</th>
                        <th scope="col">Campo</th>
                        <th scope="col">Atleta</th>
                        <th scope="col">Aparelho</th>
                        <th scope="col">Nota</th>
                        <th scope="col">Submetida em</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($nota = mysqli_fetch_assoc($resultado_notas)): ?>
                        <?php
                        $campo_nota = $nota["numero_juiz"] !== null
                            ? campoNumeroLabel($nota["papel"], (int) $nota["numero_juiz"])
                            : $nota["papel"];
                        $editavel = (int) $nota["pode_editar"] === 1;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($nota["judge_name"]); ?></td>
                            <td data-label="Campo"><span class="badge badge-code"><?php echo htmlspecialchars($campo_nota); ?></span></td>
                            <td data-label="Atleta"><?php echo htmlspecialchars($nota["athlete_name"]); ?></td>
                            <td data-label="Aparelho"><?php echo $nota["equipment_name"] !== null ? htmlspecialchars($nota["equipment_name"]) : "—"; ?></td>
                            <td class="num cell-total" data-label="Nota"><?php echo $nota["score_value"] !== null ? number_format($nota["score_value"], 3, ",", ".") : "--"; ?></td>
                            <td class="num cell-muted" data-label="Submetida em"><?php echo $nota["submitted_at"] ? formatarData($nota["submitted_at"]) : "--"; ?></td>
                            <td data-label="Estado">
                                <span class="badge <?php echo $editavel ? "badge-active" : "badge-inactive"; ?>">
                                    <?php echo $editavel ? "Edição autorizada" : "Bloqueada"; ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <form method="post" action="notas.php" class="inline-form">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="competition_id" value="<?php echo $competition_id; ?>">
                                        <input type="hidden" name="score_id" value="<?php echo (int) $nota["id"]; ?>">
                                        <input type="hidden" name="acao" value="<?php echo $editavel ? "bloquear" : "autorizar"; ?>">
                                        <button type="submit" class="btn btn-small <?php echo $editavel ? "btn-danger-soft" : ""; ?>">
                                            <i class="fa-solid <?php echo $editavel ? "fa-lock" : "fa-lock-open"; ?>" aria-hidden="true"></i>
                                            <?php echo $editavel ? "Bloquear novamente" : "Autorizar edição"; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
