<?php
// PHP_Pages/admin/inscricoes.php
// Página onde o administrador inscreve atletas numa competição, escolhendo
// apenas o atleta, o escalão e o número de dorsal. Os aparelhos já não se
// escolhem aqui: são sempre os que foram configurados para esse escalão
// nesta competição, em "Escalões da Competição" (competicao_escaloes.php) —
// inscrever um atleta cria automaticamente uma entrada por cada um desses
// aparelhos. Sem isto, não há atletas para os juízes pontuarem em
// submeter_pontuacao.php.

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Área administrativa: ADMIN tem sempre acesso; um USER só entra se
// lhe tiver sido atribuído o menu correspondente (ver Parametrizações).
requireAdminArea($conn);
requireTransaction($conn, "INSCRICOES_GERIR");

// Todos os pedidos POST desta página alteram dados: exigimos sempre um
// token CSRF válido antes de processar qualquer ação.
exigirCsrf();

$erros = array();

$competition_id = isset($_GET["competition_id"]) ? (int) $_GET["competition_id"] : 0;
if (isset($_POST["competition_id"])) {
    $competition_id = (int) $_POST["competition_id"];
}

// --- Remover uma inscrição ---
// Uma inscrição cobre todos os aparelhos configurados para o escalão nesta
// competição, por isso removê-la apaga de uma vez todas essas linhas em
// event_entries (uma por aparelho).
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $athlete_id = (int) $_POST["athlete_id"];
    $category_id = (int) $_POST["category_id"];

    // Se já existirem pontuações associadas a alguma destas entradas, a base
    // de dados vai recusar o apagar (chave estrangeira) — nesse caso
    // avisamos o admin e não é apagada nenhuma linha (tudo ou nada).
    $sql = "DELETE FROM event_entries WHERE competition_id = ? AND athlete_id = ? AND category_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $competition_id, $athlete_id, $category_id);
    $ok = mysqli_stmt_execute($stmt);

    if ($ok) {
        setFlash("success", "Inscrição removida.");
    } else {
        setFlash("error", "Não foi possível remover esta inscrição porque já existem pontuações associadas.");
    }

    header("Location: inscricoes.php?competition_id=" . $competition_id);
    exit;
}

// --- Adicionar uma nova inscrição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $athlete_id = (int) $_POST["athlete_id"];
    $category_id = (int) $_POST["category_id"];
    $bib_number = trim($_POST["bib_number"]);

    if ($athlete_id == 0) {
        $erros[] = "Escolhe um atleta.";
    } else {
        // O escalão de uma inscrição tem de corresponder ao escalão do atleta
        // (quando o atleta já tem um escalão definido, calculado pela idade em
        // Gerir Atletas). Isto impede, por exemplo, inscrever um atleta
        // Juvenil na categoria Sénior por engano ou por um pedido manipulado.
        $sql_atleta = "SELECT category_id FROM athletes WHERE id = ? LIMIT 1";
        $stmt_atleta = mysqli_prepare($conn, $sql_atleta);
        mysqli_stmt_bind_param($stmt_atleta, "i", $athlete_id);
        mysqli_stmt_execute($stmt_atleta);
        $atleta_encontrado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_atleta));

        if (!$atleta_encontrado) {
            $erros[] = "O atleta escolhido não existe.";
        } elseif ($atleta_encontrado["category_id"] !== null && (int) $atleta_encontrado["category_id"] !== $category_id) {
            $erros[] = "Este atleta pertence a outro escalão. Atualiza o escalão do atleta na página de Atletas, se for necessário.";
        }
    }

    if ($category_id == 0) {
        $erros[] = "Escolhe um escalão.";
    } else {
        // O escalão escolhido tem de ser mesmo um dos que foram adicionados a
        // esta competição em "Escalões da Competição".
        $sql_escalao_comp = "SELECT 1 FROM competition_categories WHERE competition_id = ? AND category_id = ? LIMIT 1";
        $stmt_escalao_comp = mysqli_prepare($conn, $sql_escalao_comp);
        mysqli_stmt_bind_param($stmt_escalao_comp, "ii", $competition_id, $category_id);
        mysqli_stmt_execute($stmt_escalao_comp);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_escalao_comp))) {
            $erros[] = "Este escalão não foi adicionado a esta competição. Adiciona-o primeiro em 'Escalões da Competição'.";
        }
    }

    // Os aparelhos a inscrever são sempre os que já foram escolhidos para
    // este escalão nesta competição — o admin já não os escolhe aqui.
    $equipamentos_do_escalao = array();
    if (count($erros) == 0) {
        $sql_equip = "SELECT equipment_id FROM competition_category_equipment WHERE competition_id = ? AND category_id = ?";
        $stmt_equip = mysqli_prepare($conn, $sql_equip);
        mysqli_stmt_bind_param($stmt_equip, "ii", $competition_id, $category_id);
        mysqli_stmt_execute($stmt_equip);
        $resultado_equip = mysqli_stmt_get_result($stmt_equip);
        while ($linha_equip = mysqli_fetch_assoc($resultado_equip)) {
            $equipamentos_do_escalao[] = (int) $linha_equip["equipment_id"];
        }

        if (count($equipamentos_do_escalao) == 0) {
            $erros[] = "Este escalão ainda não tem nenhum aparelho configurado nesta competição. Define-os primeiro em 'Escalões da Competição'.";
        }
    }

    if (count($erros) == 0) {
        // Descobrir que aparelhos deste escalão o atleta ainda não tem, para
        // não duplicar inscrições já existentes (ex: se o escalão ganhar um
        // aparelho novo depois de o atleta já estar inscrito, voltar a
        // "inscrever" só acrescenta o que falta).
        $sql_existentes = "SELECT equipment_id FROM event_entries
                            WHERE athlete_id = ? AND competition_id = ? AND category_id = ?";
        $stmt_existentes = mysqli_prepare($conn, $sql_existentes);
        mysqli_stmt_bind_param($stmt_existentes, "iii", $athlete_id, $competition_id, $category_id);
        mysqli_stmt_execute($stmt_existentes);
        $resultado_existentes = mysqli_stmt_get_result($stmt_existentes);
        $ja_inscritos = array();
        while ($linha_existente = mysqli_fetch_assoc($resultado_existentes)) {
            $ja_inscritos[] = (int) $linha_existente["equipment_id"];
        }

        $equipamentos_por_inscrever = array_diff($equipamentos_do_escalao, $ja_inscritos);

        if (count($equipamentos_por_inscrever) == 0) {
            setFlash("error", "Este atleta já está inscrito neste escalão, em todos os aparelhos configurados para esta competição.");
        } else {
            // Inserir uma linha por aparelho tem de acontecer em bloco: se
            // falhar a meio, não queremos o atleta inscrito só em parte dos
            // aparelhos do escalão.
            mysqli_begin_transaction($conn);
            $tudo_ok = true;

            $sql = "INSERT INTO event_entries (competition_id, athlete_id, equipment_id, category_id, bib_number)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            foreach ($equipamentos_por_inscrever as $equipment_id) {
                mysqli_stmt_bind_param($stmt, "iiiis", $competition_id, $athlete_id, $equipment_id, $category_id, $bib_number);
                $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt);
            }

            if ($tudo_ok) {
                mysqli_commit($conn);
                setFlash("success", "Atleta inscrito com sucesso em " . count($equipamentos_por_inscrever) . " aparelho(s).");
            } else {
                mysqli_rollback($conn);
                setFlash("error", "Não foi possível inscrever o atleta.");
            }
        }

        header("Location: inscricoes.php?competition_id=" . $competition_id);
        exit;
    }
}

// --- Se não foi indicada nenhuma competição, mostrar a lista para escolher ---
if ($competition_id == 0) {
    $resultado_competicoes = mysqli_query($conn, "SELECT * FROM competitions ORDER BY start_date DESC");

    $pageTitle = "Inscrever Atletas - " . APP_NAME;
    $pagina_atual = "admin_competicoes";
    require __DIR__ . "/../../includes/header.php";

    $titulo_pagina = "Inscrever Atletas";
    $descricao_pagina = "Escolhe a competição na qual queres inscrever atletas.";
    $pagina_destino = "inscricoes.php";
    $rotulo_acao = "Inscrever atletas";
    require __DIR__ . "/../../includes/escolher_competicao.php";
    require __DIR__ . "/../../includes/footer.php";
    exit;
}

// --- A partir daqui, já temos uma competição escolhida ---

$sql_comp = "SELECT * FROM competitions WHERE id = ? LIMIT 1";
$stmt_comp = mysqli_prepare($conn, $sql_comp);
mysqli_stmt_bind_param($stmt_comp, "i", $competition_id);
mysqli_stmt_execute($stmt_comp);
$resultado_comp = mysqli_stmt_get_result($stmt_comp);
$competicao = mysqli_fetch_assoc($resultado_comp);

if (!$competicao) {
    setFlash("error", "Competição não encontrada.");
    header("Location: inscricoes.php");
    exit;
}

// Listas para preencher os <select> do formulário
$atletas = mysqli_query($conn, "SELECT a.id, a.name, a.category_id, c.name AS club_name
                                 FROM athletes a
                                 LEFT JOIN clubs c ON c.id = a.club_id
                                 ORDER BY a.name");

// Só os escalões já adicionados a esta competição (ver "Escalões da
// Competição"), cada um já com os nomes dos aparelhos configurados para ele
// nesta competição — usados só para mostrar uma pré-visualização no
// formulário, o backend volta a ir buscar isto à base de dados ao guardar.
$sql_categorias = "SELECT cat.id, cat.name,
                           (SELECT GROUP_CONCAT(eq.name ORDER BY eq.name SEPARATOR ', ')
                            FROM competition_category_equipment cce
                            JOIN equipments eq ON eq.id = cce.equipment_id
                            WHERE cce.competition_id = cc.competition_id AND cce.category_id = cc.category_id
                           ) AS aparelhos_nomes
                    FROM competition_categories cc
                    JOIN categories cat ON cat.id = cc.category_id
                    WHERE cc.competition_id = ?
                    ORDER BY cat.name";
$stmt_categorias = mysqli_prepare($conn, $sql_categorias);
mysqli_stmt_bind_param($stmt_categorias, "i", $competition_id);
mysqli_stmt_execute($stmt_categorias);
$categorias = mysqli_stmt_get_result($stmt_categorias);

// Inscrições já existentes para esta competição, agrupadas por atleta+escalão
// (uma inscrição = várias linhas em event_entries, uma por aparelho)
$sql_lista = "SELECT a.id AS athlete_id, a.name AS athlete_name,
                     cat.id AS category_id, cat.name AS category_name,
                     MIN(ee.bib_number) AS bib_number,
                     GROUP_CONCAT(eq.name ORDER BY eq.name SEPARATOR ', ') AS aparelhos_nomes
              FROM event_entries ee
              JOIN athletes a ON a.id = ee.athlete_id
              JOIN categories cat ON cat.id = ee.category_id
              JOIN equipments eq ON eq.id = ee.equipment_id
              WHERE ee.competition_id = ?
              GROUP BY a.id, a.name, cat.id, cat.name
              ORDER BY cat.name, a.name";
$stmt_lista = mysqli_prepare($conn, $sql_lista);
mysqli_stmt_bind_param($stmt_lista, "i", $competition_id);
mysqli_stmt_execute($stmt_lista);
$resultado_lista = mysqli_stmt_get_result($stmt_lista);

$pageTitle = "Inscrever Atletas - " . APP_NAME;
$pagina_atual = "admin_competicoes";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="inscricoes.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Escolher outra competição</a>
    <h1>Inscrever Atletas</h1>
    <ul class="meta-list">
        <li><i class="fa-solid fa-trophy" aria-hidden="true"></i> <strong><?php echo htmlspecialchars($competicao["name"]); ?></strong></li>
    </ul>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2>Nova inscrição</h2>

    <?php if (mysqli_num_rows($atletas) == 0): ?>
        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>É preciso existir pelo menos um atleta antes de inscrever atletas. <a href="atletas.php">Cria um atleta primeiro.</a></p>
        </div>
    <?php elseif (mysqli_num_rows($categorias) == 0): ?>
        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Esta competição ainda não tem nenhum escalão adicionado. <a href="competicao_escaloes.php?competition_id=<?php echo $competition_id; ?>">Define os escalões e aparelhos desta competição primeiro.</a></p>
        </div>
    <?php else: ?>

        <form method="post" action="inscricoes.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="acao" value="guardar">
            <input type="hidden" name="competition_id" value="<?php echo $competition_id; ?>">

            <label for="athlete_id">Atleta</label>
            <select id="athlete_id" name="athlete_id" required data-categoria-target="category_id">
                <option value="">-- Escolhe um atleta --</option>
                <?php while ($atleta = mysqli_fetch_assoc($atletas)): ?>
                    <option value="<?php echo $atleta['id']; ?>" data-category-id="<?php echo $atleta['category_id']; ?>">
                        <?php echo htmlspecialchars($atleta["name"]); ?>
                        <?php echo $atleta["club_name"] ? " (" . htmlspecialchars($atleta["club_name"]) . ")" : ""; ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <p class="hint">Se o atleta já tiver um escalão definido (calculado pela idade em Gerir Atletas), ele é escolhido automaticamente e não pode ser alterado aqui.</p>

            <div class="form-row">
                <div>
                    <label for="category_id">Escalão</label>
                    <select id="category_id" name="category_id" required data-aparelhos-target="aparelhos-do-escalao">
                        <option value="">-- Escolhe um escalão --</option>
                        <?php mysqli_data_seek($categorias, 0); ?>
                        <?php while ($cat = mysqli_fetch_assoc($categorias)): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                    data-aparelhos="<?php echo htmlspecialchars($cat['aparelhos_nomes'] ?? ''); ?>"
                                    <?php echo !$cat['aparelhos_nomes'] ? "disabled" : ""; ?>>
                                <?php echo htmlspecialchars($cat["name"]); ?>
                                <?php echo !$cat['aparelhos_nomes'] ? " (sem aparelhos configurados nesta competição)" : ""; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="bib_number">Número de dorsal <span class="label-optional">(opcional)</span></label>
                    <input type="text" id="bib_number" name="bib_number" inputmode="numeric">
                </div>
            </div>
            <p class="hint" id="aparelhos-do-escalao" aria-live="polite"></p>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Inscrever atleta</button>
            </div>
        </form>

    <?php endif; ?>
</section>

<section class="competitions-list">
    <h2>Atletas já inscritos<span class="count-pill"><?php echo mysqli_num_rows($resultado_lista); ?></span></h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Ainda não há atletas inscritos nesta competição.</p>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Atleta</th>
                        <th scope="col">Dorsal</th>
                        <th scope="col">Escalão</th>
                        <th scope="col">Aparelhos</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($e = mysqli_fetch_assoc($resultado_lista)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($e["athlete_name"]); ?></td>
                            <td data-label="Dorsal"><span class="badge badge-code"><?php echo $e["bib_number"] !== "" && $e["bib_number"] !== null ? htmlspecialchars($e["bib_number"]) : "—"; ?></span></td>
                            <td data-label="Escalão"><?php echo htmlspecialchars($e["category_name"]); ?></td>
                            <td class="cell-muted" data-label="Aparelhos"><?php echo htmlspecialchars($e["aparelhos_nomes"]); ?></td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <form method="post" action="inscricoes.php" class="inline-form" data-confirm="Remover esta inscrição? Todos os aparelhos deste escalão são removidos.">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="eliminar">
                                        <input type="hidden" name="athlete_id" value="<?php echo $e['athlete_id']; ?>">
                                        <input type="hidden" name="category_id" value="<?php echo $e['category_id']; ?>">
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
