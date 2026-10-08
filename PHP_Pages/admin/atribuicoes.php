<?php
// PHP_Pages/admin/atribuicoes.php
// Atribuição de juízes a uma COMPETIÇÃO.
// O escalão e o aparelho não são escolhidos nesta área: a atribuição é global
// para a competição e o juiz fica associado ao seu papel + número (ex: A2).

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

requireAdminArea($conn);
requireTransaction($conn, "ATRIBUICOES_GERIR");
exigirCsrf();

$erros = array();
$competition_id = isset($_GET["competition_id"]) ? (int)$_GET["competition_id"] : 0;
if (isset($_POST["competition_id"])) $competition_id = (int)$_POST["competition_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "eliminar") {
    $id = (int)($_POST["id"] ?? 0);
    $stmt = mysqli_prepare($conn, "DELETE FROM judge_assignments WHERE id=? AND competition_id=?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $competition_id);
    mysqli_stmt_execute($stmt);
    setFlash("success", "Atribuição removida.");
    header("Location: atribuicoes.php?competition_id=" . $competition_id);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "guardar") {
    $judge_id = (int)($_POST["judge_id"] ?? 0);
    $papel = $_POST["papel"] ?? "";
    $numero_juiz = (int)($_POST["numero_juiz"] ?? 0);

    if ($judge_id <= 0) {
        $erros[] = "Escolhe um juiz.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT j.id FROM judges j JOIN users u ON u.id=j.user_id WHERE j.id=? AND u.role='JUDGE' AND u.status='A' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $judge_id);
        mysqli_stmt_execute($stmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) $erros[] = "O juiz escolhido não existe ou está inativo.";
    }

    if (!array_key_exists($papel, papeis())) $erros[] = "Escolhe um papel válido.";
    if ($numero_juiz < 1 || $numero_juiz > numeroMaximoPorPapel($papel)) {
        $erros[] = "Escolhe um número de juiz válido para este papel.";
    }

    if (!$erros) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM judge_assignments WHERE competition_id=? AND judge_id=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ii", $competition_id, $judge_id);
        mysqli_stmt_execute($stmt);
        $ja_existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        $stmt = mysqli_prepare($conn, "SELECT id FROM judge_assignments WHERE competition_id=? AND papel=? AND numero_juiz=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "isi", $competition_id, $papel, $numero_juiz);
        mysqli_stmt_execute($stmt);
        $numero_ocupado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($ja_existe) {
            setFlash("error", "Este juiz já está atribuído a esta competição.");
        } elseif ($numero_ocupado) {
            setFlash("error", "O número " . htmlspecialchars(campoNumeroLabel($papel, $numero_juiz)) . " já está atribuído nesta competição.");
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO judge_assignments (judge_id,competition_id,papel,numero_juiz) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($stmt, "iisi", $judge_id, $competition_id, $papel, $numero_juiz);
            if (mysqli_stmt_execute($stmt)) {
                setFlash("success", "Juiz atribuído com sucesso.");
            } else {
                setFlash("error", "Não foi possível atribuir o juiz.");
            }
        }
        header("Location: atribuicoes.php?competition_id=" . $competition_id);
        exit;
    }
}

if ($competition_id === 0) {
    $resultado_competicoes = mysqli_query($conn, "SELECT * FROM competitions ORDER BY start_date DESC");
    $pageTitle = "Atribuir Juízes - " . APP_NAME;
    $pagina_atual = "admin_competicoes";
    require __DIR__ . "/../../includes/header.php";

    $titulo_pagina = "Atribuir Juízes";
    $descricao_pagina = "Escolhe a competição. O juiz será atribuído à competição inteira, sem selecionar escalão ou aparelho.";
    $pagina_destino = "atribuicoes.php";
    $rotulo_acao = "Atribuir juízes";
    require __DIR__ . "/../../includes/escolher_competicao.php";
    require __DIR__ . "/../../includes/footer.php";
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM competitions WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $competition_id);
mysqli_stmt_execute($stmt);
$competicao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$competicao) {
    setFlash("error", "Competição não encontrada.");
    header("Location: atribuicoes.php");
    exit;
}

$juizes = mysqli_query($conn, "SELECT j.id,u.name FROM users u JOIN judges j ON j.user_id=u.id WHERE u.role='JUDGE' AND u.status='A' ORDER BY u.name");
$stmt = mysqli_prepare($conn,
    "SELECT ja.id,ja.papel,ja.numero_juiz,u.name AS judge_name
     FROM judge_assignments ja
     JOIN judges j ON j.id=ja.judge_id
     JOIN users u ON u.id=j.user_id
     WHERE ja.competition_id=?
     ORDER BY u.name,ja.papel,ja.numero_juiz");
mysqli_stmt_bind_param($stmt, "i", $competition_id);
mysqli_stmt_execute($stmt);
$resultado_lista = mysqli_stmt_get_result($stmt);

$pageTitle = "Atribuir Juízes - " . APP_NAME;
$pagina_atual = "admin_competicoes";
require __DIR__ . "/../../includes/header.php";
?>
<section class="page-intro">
    <a class="back-link" href="atribuicoes.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Escolher outra competição</a>
    <h1>Atribuir Juízes</h1>
    <ul class="meta-list">
        <li><i class="fa-solid fa-trophy" aria-hidden="true"></i> <strong><?php echo htmlspecialchars($competicao["name"]); ?></strong></li>
    </ul>
    <p class="hint">O escalão e o aparelho são definidos pelas inscrições da competição e não fazem parte da atribuição do juiz.</p>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<?php
// Números possíveis por papel (ex: A -> A1..A4), usados pelo seletor "Número do juiz"
$campos_por_papel = array();
foreach (array_keys(papeis()) as $codigo_papel) {
    $campos_por_papel[$codigo_papel] = camposNumeraveisPorPapel($codigo_papel);
}
?>

<section class="form-card">
    <h2>Nova atribuição</h2>
    <form method="post" action="atribuicoes.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="competition_id" value="<?php echo $competition_id; ?>">

        <label for="judge_id">Juiz</label>
        <select id="judge_id" name="judge_id" required>
            <option value="">-- Escolhe um juiz --</option>
            <?php while ($juiz = mysqli_fetch_assoc($juizes)): ?>
                <option value="<?php echo (int) $juiz["id"]; ?>"><?php echo htmlspecialchars($juiz["name"]); ?></option>
            <?php endwhile; ?>
        </select>

        <div class="form-row">
            <div>
                <label for="papel">Papel nesta competição</label>
                <select id="papel" name="papel" required
                        data-campos-por-papel="<?php echo htmlspecialchars(json_encode($campos_por_papel)); ?>"
                        data-numero-target="numero_juiz">
                    <option value="">-- Escolhe um papel --</option>
                    <?php foreach (papeis() as $codigo => $rotulo): ?>
                        <option value="<?php echo $codigo; ?>"><?php echo htmlspecialchars($rotulo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="numero_juiz">Número do juiz</label>
                <select id="numero_juiz" name="numero_juiz" required>
                    <option value="">-- Escolhe primeiro um papel --</option>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Atribuir juiz</button>
        </div>
    </form>
</section>

<section class="competitions-list">
    <h2>Juízes já atribuídos<span class="count-pill"><?php echo mysqli_num_rows($resultado_lista); ?></span></h2>
    <?php if (mysqli_num_rows($resultado_lista) === 0): ?>
        <div class="empty-state">
            <i class="fa-solid fa-gavel" aria-hidden="true"></i>
            <p>Ainda não há juízes atribuídos a esta competição.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Juiz</th>
                        <th scope="col">Papel</th>
                        <th scope="col">Número</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = mysqli_fetch_assoc($resultado_lista)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a["judge_name"]); ?></td>
                            <td data-label="Papel"><?php echo htmlspecialchars(papelLabel($a["papel"])); ?></td>
                            <td data-label="Número"><span class="badge badge-code"><?php echo htmlspecialchars(campoNumeroLabel($a["papel"], $a["numero_juiz"])); ?></span></td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <form method="post" action="atribuicoes.php" class="inline-form" data-confirm="Remover esta atribuição?">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int) $a["id"]; ?>">
                                        <input type="hidden" name="competition_id" value="<?php echo $competition_id; ?>">
                                        <button type="submit" class="btn btn-small btn-danger-soft"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Remover</button>
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
