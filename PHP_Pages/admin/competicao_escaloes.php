<?php
// PHP_Pages/admin/competicao_escaloes.php
// Página onde o administrador escolhe quais escalões (categorias) participam
// numa competição e, para cada escalão adicionado, quais dos seus aparelhos
// vão mesmo ser usados nessa competição.

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Área administrativa: ADMIN tem sempre acesso; um USER só entra se
// lhe tiver sido atribuído o menu correspondente (ver Parametrizações).
requireAdminArea($conn);
requireTransaction($conn, "COMPETICAO_ESCALOES_GERIR");

// Todos os pedidos POST desta página alteram dados: exigimos sempre um
// token CSRF válido antes de processar qualquer ação.
exigirCsrf();

$erros = array();

$competition_id = isset($_GET["competition_id"]) ? (int) $_GET["competition_id"] : 0;
if (isset($_POST["competition_id"])) {
    $competition_id = (int) $_POST["competition_id"];
}

// --- Remover um escalão desta competição (e os aparelhos ligados a ele nesta competição) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $category_id = (int) $_POST["category_id"];

    // Apagar primeiro os aparelhos escolhidos e só depois o próprio escalão,
    // tudo em bloco: se a segunda parte falhar não queremos ficar com os
    // aparelhos já apagados mas o escalão ainda lá.
    mysqli_begin_transaction($conn);
    $tudo_ok = true;

    $sql_del_eq = "DELETE FROM competition_category_equipment WHERE competition_id = ? AND category_id = ?";
    $stmt_del_eq = mysqli_prepare($conn, $sql_del_eq);
    mysqli_stmt_bind_param($stmt_del_eq, "ii", $competition_id, $category_id);
    $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt_del_eq);

    $sql_del_cat = "DELETE FROM competition_categories WHERE competition_id = ? AND category_id = ?";
    $stmt_del_cat = mysqli_prepare($conn, $sql_del_cat);
    mysqli_stmt_bind_param($stmt_del_cat, "ii", $competition_id, $category_id);
    $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt_del_cat);

    if ($tudo_ok) {
        mysqli_commit($conn);
        setFlash("success", "Escalão removido desta competição.");
    } else {
        mysqli_rollback($conn);
        setFlash("error", "Não foi possível remover este escalão desta competição.");
    }

    header("Location: competicao_escaloes.php?competition_id=" . $competition_id);
    exit;
}

// --- Adicionar um escalão a esta competição, ou atualizar os aparelhos escolhidos para um escalão já adicionado ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $category_id = (int) $_POST["category_id"];
    $equipamentos_escolhidos = isset($_POST["equipment_ids"]) && is_array($_POST["equipment_ids"])
        ? array_map("intval", $_POST["equipment_ids"])
        : array();

    if ($category_id == 0) {
        $erros[] = "Escolhe um escalão.";
    }

    // Um aparelho só pode ser escolhido para este escalão se estiver mesmo
    // ligado a ele, ou se não estiver ligado a nenhum escalão em particular
    // (o que o torna disponível para todos) — repetimos aqui a mesma regra
    // já usada em inscricoes.php, para nunca confiar só na filtragem feita
    // em JavaScript.
    if (count($erros) == 0 && count($equipamentos_escolhidos) > 0) {
        $sql_validos = "SELECT eq.id
                         FROM equipments eq
                         LEFT JOIN equipment_categories ec ON ec.equipment_id = eq.id
                         WHERE ec.category_id = ? OR ec.equipment_id IS NULL";
        $stmt_validos = mysqli_prepare($conn, $sql_validos);
        mysqli_stmt_bind_param($stmt_validos, "i", $category_id);
        mysqli_stmt_execute($stmt_validos);
        $resultado_validos = mysqli_stmt_get_result($stmt_validos);

        $ids_validos = array();
        while ($linha_valida = mysqli_fetch_assoc($resultado_validos)) {
            $ids_validos[] = (int) $linha_valida["id"];
        }

        foreach ($equipamentos_escolhidos as $equipment_id) {
            if (!in_array($equipment_id, $ids_validos)) {
                $erros[] = "Um dos aparelhos escolhidos não está disponível para este escalão.";
                break;
            }
        }
    }

    if (count($erros) == 0) {
        // Guardar o escalão + os aparelhos escolhidos tem de acontecer em
        // bloco, tal como em aparelhos.php: apagamos os aparelhos antigos
        // deste escalão nesta competição e inserimos de novo os que ficaram
        // marcados.
        mysqli_begin_transaction($conn);
        $tudo_ok = true;

        $sql_cat = "INSERT IGNORE INTO competition_categories (competition_id, category_id) VALUES (?, ?)";
        $stmt_cat = mysqli_prepare($conn, $sql_cat);
        mysqli_stmt_bind_param($stmt_cat, "ii", $competition_id, $category_id);
        $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt_cat);

        $sql_del = "DELETE FROM competition_category_equipment WHERE competition_id = ? AND category_id = ?";
        $stmt_del = mysqli_prepare($conn, $sql_del);
        mysqli_stmt_bind_param($stmt_del, "ii", $competition_id, $category_id);
        $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt_del);

        if ($tudo_ok && count($equipamentos_escolhidos) > 0) {
            $sql_ins = "INSERT INTO competition_category_equipment (competition_id, category_id, equipment_id) VALUES (?, ?, ?)";
            $stmt_ins = mysqli_prepare($conn, $sql_ins);
            foreach ($equipamentos_escolhidos as $equipment_id) {
                mysqli_stmt_bind_param($stmt_ins, "iii", $competition_id, $category_id, $equipment_id);
                $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt_ins);
            }
        }

        if ($tudo_ok) {
            mysqli_commit($conn);
            setFlash("success", "Escalão e aparelhos guardados com sucesso.");
        } else {
            mysqli_rollback($conn);
            setFlash("error", "Não foi possível guardar o escalão e os aparelhos desta competição.");
        }

        header("Location: competicao_escaloes.php?competition_id=" . $competition_id);
        exit;
    }
}

// --- Se não foi indicada nenhuma competição, mostrar a lista para escolher ---
if ($competition_id == 0) {
    $resultado_competicoes = mysqli_query($conn, "SELECT * FROM competitions ORDER BY start_date DESC");

    $pageTitle = "Escalões da Competição - " . APP_NAME;
    $pagina_atual = "admin_competicoes";
    require __DIR__ . "/../../includes/header.php";

    $titulo_pagina = "Escalões da Competição";
    $descricao_pagina = "Escolhe a competição para definir os escalões que participam e os aparelhos que vão ser usados em cada um.";
    $pagina_destino = "competicao_escaloes.php";
    $rotulo_acao = "Definir escalões";
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
    header("Location: competicao_escaloes.php");
    exit;
}

// --- Todos os escalões existentes, para a lista pendente de escolher ---
$resultado_categorias = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$categorias_lista = array();
while ($cat = mysqli_fetch_assoc($resultado_categorias)) {
    $categorias_lista[] = $cat;
}

// Devolve os aparelhos válidos para um escalão: os ligados a ele em
// equipment_categories, mais os que não estão ligados a nenhum escalão em
// particular (esses ficam disponíveis para todos).
function aparelhosDoEscalao($conn, $category_id) {
    $sql = "SELECT eq.id, eq.name
            FROM equipments eq
            LEFT JOIN equipment_categories ec ON ec.equipment_id = eq.id
            WHERE ec.category_id = ? OR ec.equipment_id IS NULL
            GROUP BY eq.id, eq.name
            ORDER BY eq.name";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    $lista = array();
    while ($linha = mysqli_fetch_assoc($resultado)) {
        $lista[] = $linha;
    }
    return $lista;
}

// Aparelhos já escolhidos nesta competição, agrupados por escalão
$equipamentos_escolhidos_por_escalao = array();
$sql_escolhidos = "SELECT category_id, equipment_id FROM competition_category_equipment WHERE competition_id = ?";
$stmt_escolhidos = mysqli_prepare($conn, $sql_escolhidos);
mysqli_stmt_bind_param($stmt_escolhidos, "i", $competition_id);
mysqli_stmt_execute($stmt_escolhidos);
$resultado_escolhidos = mysqli_stmt_get_result($stmt_escolhidos);
while ($linha = mysqli_fetch_assoc($resultado_escolhidos)) {
    $cid = (int) $linha["category_id"];
    if (!isset($equipamentos_escolhidos_por_escalao[$cid])) {
        $equipamentos_escolhidos_por_escalao[$cid] = array();
    }
    $equipamentos_escolhidos_por_escalao[$cid][] = (int) $linha["equipment_id"];
}

// Escalões já adicionados a esta competição, para marcar isso no <select>
$escaloes_da_competicao = array();
$sql_escaloes_comp = "SELECT category_id FROM competition_categories WHERE competition_id = ?";
$stmt_escaloes_comp = mysqli_prepare($conn, $sql_escaloes_comp);
mysqli_stmt_bind_param($stmt_escaloes_comp, "i", $competition_id);
mysqli_stmt_execute($stmt_escaloes_comp);
$resultado_escaloes_comp = mysqli_stmt_get_result($stmt_escaloes_comp);
while ($linha = mysqli_fetch_assoc($resultado_escaloes_comp)) {
    $escaloes_da_competicao[(int) $linha["category_id"]] = true;
}

$pageTitle = "Escalões da Competição - " . APP_NAME;
$pagina_atual = "admin_competicoes";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="competicao_escaloes.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Escolher outra competição</a>
    <h1>Escalões da Competição</h1>
    <ul class="meta-list">
        <li><i class="fa-solid fa-trophy" aria-hidden="true"></i> <strong><?php echo htmlspecialchars($competicao["name"]); ?></strong></li>
    </ul>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2>Adicionar ou editar um escalão desta competição</h2>

    <?php if (count($categorias_lista) == 0): ?>
        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Ainda não existem escalões registados. <a href="escaloes.php">Cria um primeiro.</a></p>
        </div>
    <?php else: ?>

        <form method="post" action="competicao_escaloes.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="acao" value="guardar">
            <input type="hidden" name="competition_id" value="<?php echo $competition_id; ?>">

            <label for="category_id">Escalão</label>
            <select id="category_id" name="category_id" required data-grupos-escalao=".equipamentos-do-escalao">
                <option value="">-- Escolhe um escalão --</option>
                <?php foreach ($categorias_lista as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>">
                        <?php echo htmlspecialchars($cat["name"]); ?>
                        <?php echo isset($escaloes_da_competicao[$cat['id']]) ? " (já adicionado)" : ""; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label id="aparelhos-titulo">Aparelhos deste escalão a usar nesta competição</label>
            <p class="hint">
                Escolhe primeiro o escalão. Aparecem aqui todos os aparelhos que esse escalão
                usa — marca só os que vão mesmo ser usados nesta competição.
            </p>

            <?php foreach ($categorias_lista as $cat):
                $aparelhos_do_escalao = aparelhosDoEscalao($conn, $cat['id']);
                $ja_escolhidos = isset($equipamentos_escolhidos_por_escalao[$cat['id']])
                    ? $equipamentos_escolhidos_por_escalao[$cat['id']]
                    : array();
            ?>
                <div class="checkbox-group equipamentos-do-escalao" data-category-id="<?php echo $cat['id']; ?>" role="group" aria-labelledby="aparelhos-titulo" hidden>
                    <?php if (count($aparelhos_do_escalao) == 0): ?>
                        <p>Este escalão não tem nenhum aparelho disponível. <a href="aparelhos.php">Liga um primeiro.</a></p>
                    <?php else: ?>
                        <?php foreach ($aparelhos_do_escalao as $eq): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_ids[]" value="<?php echo $eq['id']; ?>"
                                       <?php echo in_array($eq['id'], $ja_escolhidos) ? "checked" : ""; ?>>
                                <?php echo htmlspecialchars($eq["name"]); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar escalão e aparelhos</button>
            </div>
        </form>

    <?php endif; ?>
</section>

<section class="competitions-list">
    <h2>Escalões já adicionados a esta competição</h2>

    <?php
    $sql_lista = "SELECT cat.id AS category_id, cat.name AS category_name,
                         GROUP_CONCAT(eq.name ORDER BY eq.name SEPARATOR ', ') AS aparelhos_nomes
                  FROM competition_categories cc
                  JOIN categories cat ON cat.id = cc.category_id
                  LEFT JOIN competition_category_equipment cce ON cce.competition_id = cc.competition_id AND cce.category_id = cc.category_id
                  LEFT JOIN equipments eq ON eq.id = cce.equipment_id
                  WHERE cc.competition_id = ?
                  GROUP BY cat.id, cat.name
                  ORDER BY cat.name";
    $stmt_lista = mysqli_prepare($conn, $sql_lista);
    mysqli_stmt_bind_param($stmt_lista, "i", $competition_id);
    mysqli_stmt_execute($stmt_lista);
    $resultado_lista = mysqli_stmt_get_result($stmt_lista);
    ?>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Ainda não foi adicionado nenhum escalão a esta competição.</p>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Escalão</th>
                        <th scope="col">Aparelhos usados</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($linha = mysqli_fetch_assoc($resultado_lista)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($linha["category_name"]); ?></td>
                            <td class="cell-muted" data-label="Aparelhos usados"><?php echo $linha["aparelhos_nomes"] ? htmlspecialchars($linha["aparelhos_nomes"]) : "Nenhum aparelho escolhido"; ?></td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <form method="post" action="competicao_escaloes.php" class="inline-form" data-confirm="Remover este escalão desta competição? Os aparelhos escolhidos para ele também são removidos.">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="eliminar">
                                        <input type="hidden" name="category_id" value="<?php echo $linha['category_id']; ?>">
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
