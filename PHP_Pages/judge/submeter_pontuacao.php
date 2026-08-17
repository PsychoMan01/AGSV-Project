<?php
// PHP_Pages/judge/submeter_pontuacao.php
// Aqui o juiz escolhe um atleta e submete a pontuação dele

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só juízes podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "JUDGE") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Confirmar que este utilizador é mesmo um juiz
$sql = "SELECT id FROM judges WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$juiz = mysqli_fetch_assoc($resultado);

if (!$juiz) {
    setFlash("error", "Não existe um registo de juiz associado a esta conta.");
    header("Location: painel.php");
    exit;
}

$judge_id = $juiz["id"];

// Ler o id da atribuição (assignment) que veio no URL ou no formulário
$assignment_id = 0;
if (isset($_GET["assignment_id"])) {
    $assignment_id = (int) $_GET["assignment_id"];
} elseif (isset($_POST["assignment_id"])) {
    $assignment_id = (int) $_POST["assignment_id"];
}

// Ir buscar os dados da atribuição, e confirmar que pertence a este juiz
$sql2 = "SELECT ja.*, comp.name AS competition_name, cat.name AS category_name, eq.name AS equipment_name
         FROM judge_assignments ja
         JOIN competitions comp ON comp.id = ja.competition_id
         JOIN categories cat ON cat.id = ja.category_id
         JOIN equipments eq ON eq.id = ja.equipment_id
         WHERE ja.id = ? AND ja.judge_id = ?
         LIMIT 1";

$stmt2 = mysqli_prepare($conn, $sql2);
mysqli_stmt_bind_param($stmt2, "ii", $assignment_id, $judge_id);
mysqli_stmt_execute($stmt2);
$resultado2 = mysqli_stmt_get_result($stmt2);
$atribuicao = mysqli_fetch_assoc($resultado2);

if (!$atribuicao) {
    setFlash("error", "Atribuição inválida ou não pertence a este juiz.");
    header("Location: painel.php");
    exit;
}

$erros = array();

// O papel desta atribuição diz quais campos este juiz pode pontuar.
// Ex: um juiz com papel "DB" só vê/pontua DB1 e DB2; um juiz "E" só vê/pontua
// E1, E2, E3, E4 e penal. Isto evita que um juiz preencha campos que não são
// da sua responsabilidade.
$papel_juiz = $atribuicao["papel"];
$campos_formulario = camposPorPapel($papel_juiz);

// Todos os campos possíveis na tabela "scores" (para sabermos quais ficam a NULL)
$todos_os_campos = array("DB1", "DB2", "DA1", "DA2", "A1", "A2", "A3", "A4", "E1", "E2", "E3", "E4", "penal");

// --- Quando o formulário de pontuação é submetido ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $event_entry_id = (int) $_POST["event_entry_id"];

    // Só lemos e validamos os campos que pertencem ao papel deste juiz
    $valores = array();

    foreach ($campos_formulario as $campo) {
        $valor = isset($_POST[$campo]) ? $_POST[$campo] : "";
        if ($valor === "" || !is_numeric($valor)) {
            $erros[] = "O campo " . $campo . " tem de ser um número.";
        }
        $valores[$campo] = (float) $valor;
    }

    if (count($erros) == 0) {

        // Soma dos campos que este juiz preencheu (só a parte do seu papel).
        // A nota final do atleta (juntando todos os papéis) só é calculada
        // mais tarde, quando as classificações da competição são recalculadas.
        $subtotal = 0;
        foreach ($campos_formulario as $campo) {
            if ($campo != "penal") {
                $subtotal += $valores[$campo];
            }
        }
        if (isset($valores["penal"])) {
            $subtotal -= $valores["penal"];
        }
        $subtotal = round($subtotal, 3);

        // Confirmar que a entrada do atleta pertence mesmo a esta competição/escalão/aparelho
        $sql3 = "SELECT id, athlete_id FROM event_entries
                 WHERE id = ? AND competition_id = ? AND category_id = ? AND equipment_id = ?
                 LIMIT 1";
        $stmt3 = mysqli_prepare($conn, $sql3);
        mysqli_stmt_bind_param(
            $stmt3, "iiii",
            $event_entry_id,
            $atribuicao["competition_id"],
            $atribuicao["category_id"],
            $atribuicao["equipment_id"]
        );
        mysqli_stmt_execute($stmt3);
        $resultado3 = mysqli_stmt_get_result($stmt3);
        $entrada = mysqli_fetch_assoc($resultado3);

        if (!$entrada) {
            $erros[] = "Entrada de competição inválida.";
        } else {

            $athlete_id = $entrada["athlete_id"];

            // Preparar os valores de todos os campos da tabela: os do papel deste
            // juiz levam o número que ele preencheu, os restantes ficam a NULL
            // (não são da responsabilidade deste juiz).
            $valores_bind = array();
            foreach ($todos_os_campos as $campo) {
                $valores_bind[$campo] = isset($valores[$campo]) ? $valores[$campo] : null;
            }

            // Verificar se este juiz já pontuou esta entrada antes. Se já
            // pontuou, atualizamos a linha existente em vez de criar uma
            // nova — assim evitamos linhas duplicadas que iriam distorcer
            // a média calculada em calcularClassificacoes().
            $sql_existe = "SELECT id FROM scores WHERE event_entry_id = ? AND judge_id = ? LIMIT 1";
            $stmt_existe = mysqli_prepare($conn, $sql_existe);
            mysqli_stmt_bind_param($stmt_existe, "ii", $event_entry_id, $judge_id);
            mysqli_stmt_execute($stmt_existe);
            $resultado_existe = mysqli_stmt_get_result($stmt_existe);
            $pontuacao_existente = mysqli_fetch_assoc($resultado_existe);

            if ($pontuacao_existente) {
                // Já existe: atualizar a pontuação já submetida por este juiz
                $sql4 = "UPDATE scores
                         SET score_value = ?, DB1 = ?, DB2 = ?, DA1 = ?, DA2 = ?,
                             A1 = ?, A2 = ?, A3 = ?, A4 = ?, E1 = ?, E2 = ?, E3 = ?, E4 = ?,
                             penal = ?, submitted_at = NOW()
                         WHERE id = ?";

                $stmt4 = mysqli_prepare($conn, $sql4);

                mysqli_stmt_bind_param(
                    $stmt4,
                    "ddddddddddddddi",
                    $subtotal,
                    $valores_bind["DB1"], $valores_bind["DB2"], $valores_bind["DA1"], $valores_bind["DA2"],
                    $valores_bind["A1"], $valores_bind["A2"], $valores_bind["A3"], $valores_bind["A4"],
                    $valores_bind["E1"], $valores_bind["E2"], $valores_bind["E3"], $valores_bind["E4"],
                    $valores_bind["penal"],
                    $pontuacao_existente["id"]
                );

                $mensagem_sucesso = "Pontuação (" . papelLabel($papel_juiz) . ") atualizada com sucesso: " . number_format($subtotal, 3, ",", ".") . ".";
            } else {
                // Ainda não existe: criar uma nova linha de pontuação
                $sql4 = "INSERT INTO scores
                            (score_value, status, papel, DB1, DB2, DA1, DA2, A1, A2, A3, A4, E1, E2, E3, E4,
                             penal, final_score, submitted_at, competition_id, event_entry_id, athlete_id, judge_id, equipment_id)
                         VALUES
                            (?, 'A', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), ?, ?, ?, ?, ?)";

                $stmt4 = mysqli_prepare($conn, $sql4);

                mysqli_stmt_bind_param(
                    $stmt4,
                    "dsdddddddddddddiiiii",
                    $subtotal,
                    $papel_juiz,
                    $valores_bind["DB1"], $valores_bind["DB2"], $valores_bind["DA1"], $valores_bind["DA2"],
                    $valores_bind["A1"], $valores_bind["A2"], $valores_bind["A3"], $valores_bind["A4"],
                    $valores_bind["E1"], $valores_bind["E2"], $valores_bind["E3"], $valores_bind["E4"],
                    $valores_bind["penal"],
                    $atribuicao["competition_id"], $event_entry_id, $athlete_id, $judge_id, $atribuicao["equipment_id"]
                );

                $mensagem_sucesso = "Pontuação (" . papelLabel($papel_juiz) . ") submetida com sucesso: " . number_format($subtotal, 3, ",", ".") . ".";
            }

            if (mysqli_stmt_execute($stmt4)) {
                setFlash("success", $mensagem_sucesso);
                header("Location: submeter_pontuacao.php?assignment_id=" . $assignment_id);
                exit;
            } else {
                $erros[] = "Erro ao gravar a pontuação. Tenta novamente.";
            }
        }
    }
}

// --- Lista de atletas inscritos neste escalão/aparelho/competição ---
$sql5 = "SELECT ee.id AS event_entry_id, ee.bib_number, a.name AS athlete_name,
                (SELECT COUNT(*) FROM scores s WHERE s.event_entry_id = ee.id AND s.judge_id = ?) AS ja_pontuado
         FROM event_entries ee
         JOIN athletes a ON a.id = ee.athlete_id
         WHERE ee.competition_id = ? AND ee.category_id = ? AND ee.equipment_id = ?
         ORDER BY ee.bib_number ASC";

$stmt5 = mysqli_prepare($conn, $sql5);
mysqli_stmt_bind_param(
    $stmt5, "iiii",
    $judge_id,
    $atribuicao["competition_id"],
    $atribuicao["category_id"],
    $atribuicao["equipment_id"]
);
mysqli_stmt_execute($stmt5);
$resultado5 = mysqli_stmt_get_result($stmt5);

$entradas = array();
while ($linha = mysqli_fetch_assoc($resultado5)) {
    $entradas[] = $linha;
}

$pageTitle = "Pontuar - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="painel.php">&larr; Voltar ao painel</a>
    <h1>Pontuar atletas</h1>
    <p>
        <strong>Competição:</strong> <?php echo htmlspecialchars($atribuicao["competition_name"]); ?><br>
        <strong>Escalão:</strong> <?php echo htmlspecialchars($atribuicao["category_name"]); ?><br>
        <strong>Aparelho:</strong> <?php echo htmlspecialchars($atribuicao["equipment_name"]); ?><br>
        <strong>O teu papel:</strong> <?php echo htmlspecialchars(papelLabel($papel_juiz)); ?>
    </p>
    <p class="hint">
        Como juiz de <?php echo htmlspecialchars(papelLabel($papel_juiz)); ?>, só precisas de preencher os campos abaixo.
    </p>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<?php if (count($entradas) == 0): ?>

    <p>Não existem atletas inscritos neste escalão/aparelho.</p>

<?php else: ?>

    <?php foreach ($entradas as $entrada): ?>
        <div class="score-card">
            <h3>
                Dorsal <?php echo htmlspecialchars($entrada["bib_number"]); ?> —
                <?php echo htmlspecialchars($entrada["athlete_name"]); ?>

                <?php if ($entrada["ja_pontuado"] > 0): ?>
                    <span class="badge badge-active">Já pontuado por ti</span>
                <?php endif; ?>
            </h3>

            <form method="post" action="" class="score-form">
                <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                <input type="hidden" name="event_entry_id" value="<?php echo $entrada['event_entry_id']; ?>">

                <div class="score-grid">
                    <?php foreach ($campos_formulario as $campo): ?>
                        <label>
                            <?php echo $campo; ?>
                            <input type="number" step="0.001" name="<?php echo $campo; ?>" required>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $entrada["ja_pontuado"] > 0 ? "Atualizar pontuação" : "Submeter pontuação"; ?>
                </button>
            </form>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
