<?php
// PHP_Pages/judge/submeter_pontuacao.php
// Aqui o juiz escolhe um atleta e submete a pontuação dele

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só juízes com conta ativa podem ver esta página
requireActiveJudge($conn);

// Esta página processa submissões de pontuação por POST: exigir sempre um
// token CSRF válido antes de gravar seja o que for.
exigirCsrf();

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
$sql2 = "SELECT ja.*, comp.name AS competition_name
         FROM judge_assignments ja
         JOIN competitions comp ON comp.id = ja.competition_id
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

// O papel + número desta atribuição dizem qual é o ÚNICO campo que este
// juiz pontua. Ex: um juiz com papel "A" e número 2 só vê/pontua o campo
// A2 (não A1, A3 nem A4 — esses são responsabilidade de outros juízes).
// Isto evita que um juiz preencha campos que não são da sua responsabilidade.
$papel_juiz = $atribuicao["papel"];
$numero_juiz = (int) $atribuicao["numero_juiz"];
$campo_principal = campoPorNumero($papel_juiz, $numero_juiz);

if ($campo_principal === null) {
    setFlash("error", "Esta atribuição tem um número de juiz inválido. Fala com o administrador.");
    header("Location: painel.php");
    exit;
}

// O campo principal deste juiz (ex: "A2"); juízes de papel "E" também
// registam a penalização, que continua a ser um campo à parte.
$campos_formulario = array($campo_principal);
if ($papel_juiz == "E") {
    $campos_formulario[] = "penal";
}

// Todos os campos possíveis na tabela "scores" (para sabermos quais ficam a NULL)
$todos_os_campos = array("DB1", "DB2", "DA1", "DA2", "A1", "A2", "A3", "A4", "E1", "E2", "E3", "E4", "penal");

// --- Quando o formulário de pontuação é submetido ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $event_entry_id = (int) $_POST["event_entry_id"];

    // Uma nota já submetida por este juiz fica bloqueada para edição por
    // omissão. Só é possível voltar a gravar sobre ela se o administrador
    // tiver dado autorização pontual (scores.pode_editar = 1) em "Notas" —
    // confirmamos isto aqui no servidor, e não só ao esconder o formulário,
    // para não ser possível contornar a proteção.
    $sql_bloqueio = "SELECT id, pode_editar FROM scores WHERE event_entry_id = ? AND judge_id = ? LIMIT 1";
    $stmt_bloqueio = mysqli_prepare($conn, $sql_bloqueio);
    mysqli_stmt_bind_param($stmt_bloqueio, "ii", $event_entry_id, $judge_id);
    mysqli_stmt_execute($stmt_bloqueio);
    $nota_existente_bloqueio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bloqueio));

    if ($nota_existente_bloqueio && (int) $nota_existente_bloqueio["pode_editar"] !== 1) {
        $erros[] = "Esta nota já foi submetida e está bloqueada para edição. Pede ao administrador para autorizar a edição em \"Notas\".";
    }

    // Só lemos e validamos os campos que pertencem ao papel deste juiz
    $valores = array();

    foreach ($campos_formulario as $campo) {
        $valor = isset($_POST[$campo]) ? $_POST[$campo] : "";
        if ($valor === "" || !validarNotaJuiz($valor)) {
            $erros[] = "O campo " . $campo . " tem de ser um número válido (não pode ser negativo, texto, nem um valor absurdamente alto).";
        } else {
            $valores[$campo] = (float) $valor;
        }
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
        $sql3 = "SELECT id, athlete_id, equipment_id FROM event_entries
                 WHERE id = ? AND competition_id = ?
                 LIMIT 1";
        $stmt3 = mysqli_prepare($conn, $sql3);
        mysqli_stmt_bind_param(
            $stmt3, "ii",
            $event_entry_id,
            $atribuicao["competition_id"]
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
                // A edição volta a ficar bloqueada depois de usada (pode_editar
                // = 0): a autorização do administrador serve para uma única
                // correção, não fica válida para sempre.
                $sql4 = "UPDATE scores
                         SET score_value = ?, DB1 = ?, DB2 = ?, DA1 = ?, DA2 = ?,
                             A1 = ?, A2 = ?, A3 = ?, A4 = ?, E1 = ?, E2 = ?, E3 = ?, E4 = ?,
                             penal = ?, submitted_at = NOW(), pode_editar = 0
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

                $mensagem_sucesso = "Pontuação (" . campoNumeroLabel($papel_juiz, $numero_juiz) . ") atualizada com sucesso: " . number_format($subtotal, 3, ",", ".") . ".";
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
                    $atribuicao["competition_id"], $event_entry_id, $athlete_id, $judge_id, $entrada["equipment_id"]
                );

                $mensagem_sucesso = "Pontuação (" . campoNumeroLabel($papel_juiz, $numero_juiz) . ") submetida com sucesso: " . number_format($subtotal, 3, ",", ".") . ".";
            }

            if (mysqli_stmt_execute($stmt4)) {
                // Recalcular já as classificações (por aparelho e a geral, que
                // depende delas) para a tabela pública ficar sempre atualizada
                // assim que uma nota é introduzida, sem o admin ter de clicar
                // manualmente em "Calcular Classificações".
                calcularClassificacoes($conn, $atribuicao["competition_id"]);

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
// Inclui a nota já submetida por este juiz (se existir) via LEFT JOIN, para
// a mostrar pré-preenchida no formulário e para saber se está bloqueada
// (pode_editar) — $campo_principal vem de campoPorNumero(), uma lista fixa
// de nomes de coluna, por isso é seguro interpolar aqui.
$sql5 = "SELECT ee.id AS event_entry_id, ee.bib_number, a.name AS athlete_name,
                c.name AS category_name, eq.name AS equipment_name,
                s.id AS score_id, s.pode_editar,
                s.$campo_principal AS valor_atual, s.penal AS penal_atual
         FROM event_entries ee
         JOIN athletes a ON a.id = ee.athlete_id
         JOIN categories c ON c.id = ee.category_id
         JOIN equipments eq ON eq.id = ee.equipment_id
         LEFT JOIN scores s ON s.event_entry_id = ee.id AND s.judge_id = ?
         WHERE ee.competition_id = ?
         ORDER BY ee.bib_number ASC, a.name ASC";

$stmt5 = mysqli_prepare($conn, $sql5);
mysqli_stmt_bind_param($stmt5, "ii", $judge_id, $atribuicao["competition_id"]);
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
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Pontuar atletas</h1>
    <ul class="meta-list">
        <li><i class="fa-solid fa-trophy" aria-hidden="true"></i> <strong><?php echo htmlspecialchars($atribuicao["competition_name"]); ?></strong></li>
        <li><i class="fa-solid fa-gavel" aria-hidden="true"></i> <?php echo htmlspecialchars(papelLabel($papel_juiz)); ?></li>
        <li>O teu número: <span class="badge badge-code"><?php echo htmlspecialchars(campoNumeroLabel($papel_juiz, $numero_juiz)); ?></span></li>
    </ul>
    <p class="hint">
        Como juiz <?php echo htmlspecialchars(campoNumeroLabel($papel_juiz, $numero_juiz)); ?>, só precisas de preencher a tua nota abaixo<?php echo $papel_juiz == "E" ? " (e a penalização, se houver)" : ""; ?>.
    </p>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<?php if (count($entradas) == 0): ?>

    <section class="card">
        <div class="empty-state">
            <i class="fa-solid fa-user-group" aria-hidden="true"></i>
            <h3>Sem atletas para pontuar</h3>
            <p>Não existem atletas inscritos nesta competição.</p>
        </div>
    </section>

<?php else: ?>

    <div class="score-list">
    <?php foreach ($entradas as $entrada): ?>
        <?php
        $ja_pontuado = $entrada["score_id"] !== null;
        $bloqueada = $ja_pontuado && (int) $entrada["pode_editar"] !== 1;
        $valores_atuais = array(
            $campo_principal => $entrada["valor_atual"],
            "penal"          => $entrada["penal_atual"],
        );
        $id_entrada = (int) $entrada["event_entry_id"];
        ?>
        <article class="score-card<?php echo $bloqueada ? " is-locked" : ""; ?>" aria-labelledby="atleta-<?php echo $id_entrada; ?>">
            <div class="score-head">
                <div class="score-bib" title="Dorsal"><?php echo $entrada["bib_number"] !== "" && $entrada["bib_number"] !== null ? htmlspecialchars($entrada["bib_number"]) : "—"; ?></div>
                <div class="score-athlete">
                    <h3 id="atleta-<?php echo $id_entrada; ?>"><?php echo htmlspecialchars($entrada["athlete_name"]); ?></h3>
                    <p><?php echo htmlspecialchars($entrada["category_name"]); ?> · <?php echo htmlspecialchars($entrada["equipment_name"]); ?></p>
                </div>
                <?php if ($ja_pontuado): ?>
                    <div class="score-badges">
                        <span class="badge badge-active">Já pontuado por ti</span>
                        <?php if ($bloqueada): ?>
                            <span class="badge badge-inactive">Bloqueada para edição</span>
                        <?php else: ?>
                            <span class="badge badge-info">Edição autorizada</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="post" action="" class="score-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                <input type="hidden" name="event_entry_id" value="<?php echo $id_entrada; ?>">

                <div class="score-grid">
                    <?php foreach ($campos_formulario as $campo): ?>
                        <label>
                            <?php echo $campo === "penal" ? "Penalização" : $campo; ?>
                            <input type="number" step="0.001" min="0" max="100" inputmode="decimal" name="<?php echo $campo; ?>"
                                   value="<?php echo $valores_atuais[$campo] !== null ? htmlspecialchars($valores_atuais[$campo]) : ""; ?>"
                                   <?php echo $bloqueada ? "disabled" : "required"; ?>>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary" <?php echo $bloqueada ? "disabled" : ""; ?>>
                    <i class="fa-solid <?php echo $ja_pontuado ? "fa-rotate" : "fa-check"; ?>" aria-hidden="true"></i>
                    <?php echo $ja_pontuado ? "Atualizar pontuação" : "Submeter pontuação"; ?>
                </button>
            </form>

            <?php if ($bloqueada): ?>
                <p class="hint hint-warning">Já submeteste esta nota. Pede ao administrador para autorizar a edição em "Notas" se precisares de a corrigir.</p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
