<?php
// PHP_Pages/admin/aparelhos.php
// Página onde o administrador pode criar, editar e remover aparelhos (equipments).
// Sem aparelhos registados, não é possível atribuir juízes nem inscrever atletas
// em competições (atribuicoes.php e inscricoes.php exigem pelo menos um).

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Área administrativa: ADMIN tem sempre acesso; um USER só entra se
// lhe tiver sido atribuído o menu correspondente (ver Parametrizações).
requireAdminArea($conn);
requireTransaction($conn, "APARELHOS_GERIR");

// Todos os pedidos POST desta página alteram dados: exigimos sempre um
// token CSRF válido antes de processar qualquer ação.
exigirCsrf();

$erros = array();

// --- Remover um aparelho ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    // Se o aparelho já estiver a ser usado em inscrições, atribuições ou
    // pontuações, a base de dados vai recusar o apagar (chave estrangeira).
    $sql = "DELETE FROM equipments WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok = mysqli_stmt_execute($stmt);

    if ($ok) {
        setFlash("success", "Aparelho removido com sucesso.");
    } else {
        setFlash("error", "Não foi possível remover este aparelho porque já está a ser usado (inscrições, atribuições ou pontuações).");
    }

    header("Location: aparelhos.php");
    exit;
}

// --- Criar ou atualizar um aparelho ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $id = (int) $_POST["id"];
    $nome = trim($_POST["name"]);
    $descricao = trim($_POST["description"]);

    // Categorias (escalões) marcadas no checkbox. Se um aparelho é "mãos
    // livres" e o admin marcar todas as categorias, o aparelho fica
    // disponível em todas; se marcar só uma (ex: "bola"), só fica disponível
    // nessa categoria.
    $categorias_escolhidas = isset($_POST["category_ids"]) && is_array($_POST["category_ids"])
        ? array_map("intval", $_POST["category_ids"])
        : array();

    if ($nome == "") {
        $erros[] = "O nome do aparelho é obrigatório.";
    }

    if (count($erros) == 0) {

        // Tudo o que se segue (guardar o aparelho + as suas categorias) tem de
        // acontecer em bloco: se a ligação às categorias falhar a meio, não
        // queremos ficar com um aparelho gravado mas com ligações incompletas
        // ou inconsistentes (ex: metade das categorias marcadas a menos).
        mysqli_begin_transaction($conn);
        $tudo_ok = true;

        if ($id > 0) {
            $sql = "UPDATE equipments SET name = ?, description = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $nome, $descricao, $id);
            $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt);
            $equipment_id = $id;

            $mensagem_sucesso = "Aparelho atualizado com sucesso.";
            $mensagem_erro = "Não foi possível atualizar o aparelho.";
        } else {
            $sql = "INSERT INTO equipments (name, description) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $nome, $descricao);
            $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt);
            $equipment_id = $tudo_ok ? mysqli_insert_id($conn) : 0;

            $mensagem_sucesso = "Aparelho criado com sucesso.";
            $mensagem_erro = "Não foi possível criar o aparelho.";
        }

        if ($tudo_ok) {
            // Guardar as categorias ligadas a este aparelho: apagamos as
            // ligações antigas e inserimos de novo as que ficaram marcadas.
            $sql_del = "DELETE FROM equipment_categories WHERE equipment_id = ?";
            $stmt_del = mysqli_prepare($conn, $sql_del);
            mysqli_stmt_bind_param($stmt_del, "i", $equipment_id);
            $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt_del);

            if ($tudo_ok && count($categorias_escolhidas) > 0) {
                $sql_ins = "INSERT INTO equipment_categories (equipment_id, category_id) VALUES (?, ?)";
                $stmt_ins = mysqli_prepare($conn, $sql_ins);
                foreach ($categorias_escolhidas as $category_id) {
                    mysqli_stmt_bind_param($stmt_ins, "ii", $equipment_id, $category_id);
                    $tudo_ok = $tudo_ok && mysqli_stmt_execute($stmt_ins);
                }
            }
        }

        if ($tudo_ok) {
            mysqli_commit($conn);
            setFlash("success", $mensagem_sucesso);
        } else {
            mysqli_rollback($conn);
            setFlash("error", $mensagem_erro);
        }

        header("Location: aparelhos.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados desse aparelho para pré-preencher o formulário ---
$aparelho_a_editar = null;
$categorias_do_aparelho = array(); // ids das categorias já ligadas (quando a editar)

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT * FROM equipments WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $aparelho_a_editar = mysqli_fetch_assoc($resultado);

    if ($aparelho_a_editar) {
        $sql_cat = "SELECT category_id FROM equipment_categories WHERE equipment_id = ?";
        $stmt_cat = mysqli_prepare($conn, $sql_cat);
        mysqli_stmt_bind_param($stmt_cat, "i", $id_editar);
        mysqli_stmt_execute($stmt_cat);
        $resultado_cat = mysqli_stmt_get_result($stmt_cat);
        while ($linha_cat = mysqli_fetch_assoc($resultado_cat)) {
            $categorias_do_aparelho[] = (int) $linha_cat["category_id"];
        }
    }
}

// --- Lista de todas as categorias, para as checkboxes do formulário ---
$todas_categorias = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");

// --- Lista de todos os aparelhos, já com o nome das categorias a que estão ligados ---
$resultado_lista = mysqli_query($conn, "SELECT eq.*,
                                                GROUP_CONCAT(cat.name ORDER BY cat.name SEPARATOR ', ') AS categorias_nomes
                                         FROM equipments eq
                                         LEFT JOIN equipment_categories ec ON ec.equipment_id = eq.id
                                         LEFT JOIN categories cat ON cat.id = ec.category_id
                                         GROUP BY eq.id
                                         ORDER BY eq.name");

$pageTitle = "Gerir Aparelhos - " . APP_NAME;
$pagina_atual = "admin_aparelhos";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Gerir Aparelhos</h1>
    <p>Aparelhos usados nas provas e os escalões em que cada um está disponível.</p>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $aparelho_a_editar ? "Editar aparelho" : "Novo aparelho"; ?></h2>

    <form method="post" action="aparelhos.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?php echo $aparelho_a_editar ? $aparelho_a_editar['id'] : 0; ?>">

        <label for="name">Nome do aparelho</label>
        <input type="text" id="name" name="name" required
               value="<?php echo $aparelho_a_editar ? htmlspecialchars($aparelho_a_editar['name']) : ''; ?>">

        <label for="description">Descrição <span class="label-optional">(opcional)</span></label>
        <input type="text" id="description" name="description"
               value="<?php echo $aparelho_a_editar ? htmlspecialchars($aparelho_a_editar['description'] ?? "") : ''; ?>">

        <label id="categorias-titulo">Categorias (escalões) onde este aparelho é usado</label>
        <p class="hint">
            Marca todas as categorias onde os atletas vão ter uma performance com este aparelho.
            Ex: "Mãos livres" pode estar marcado em todas; "Bola" pode estar marcado só numa.
        </p>

        <?php if (mysqli_num_rows($todas_categorias) == 0): ?>
            <div class="empty-state">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <p>Ainda não existem escalões registados. <a href="escaloes.php">Cria um primeiro.</a></p>
            </div>
        <?php else: ?>
            <div class="checkbox-group" role="group" aria-labelledby="categorias-titulo">
                <?php while ($cat = mysqli_fetch_assoc($todas_categorias)): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="category_ids[]" value="<?php echo $cat['id']; ?>"
                               <?php echo in_array($cat['id'], $categorias_do_aparelho) ? "checked" : ""; ?>>
                        <?php echo htmlspecialchars($cat["name"]); ?>
                    </label>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?php echo $aparelho_a_editar ? "Guardar alterações" : "Criar aparelho"; ?>
            </button>

            <?php if ($aparelho_a_editar): ?>
                <a class="btn" href="aparelhos.php">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="competitions-list">
    <h2>Aparelhos existentes<span class="count-pill"><?php echo mysqli_num_rows($resultado_lista); ?></span></h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Ainda não existem aparelhos registados.</p>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">Categorias</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($aparelho = mysqli_fetch_assoc($resultado_lista)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($aparelho["name"]); ?></td>
                            <td class="cell-muted" data-label="Descrição"><?php echo htmlspecialchars($aparelho["description"] ?? ""); ?></td>
                            <td data-label="Categorias"><?php echo $aparelho["categorias_nomes"] ? htmlspecialchars($aparelho["categorias_nomes"]) : "Disponível em todas as categorias"; ?></td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <a class="btn btn-small" href="aparelhos.php?action=editar&id=<?php echo $aparelho['id']; ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</a>

                                    <form method="post" action="aparelhos.php" class="inline-form" data-confirm="Remover este aparelho? Esta ação não pode ser desfeita.">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $aparelho['id']; ?>">
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
