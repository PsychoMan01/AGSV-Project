<?php
// PHP_Pages/admin/escaloes.php
// Página onde o administrador pode criar, editar e remover escalões (categories).
// Sem escalões registados, não é possível atribuir juízes nem inscrever atletas
// em competições (atribuicoes.php e inscricoes.php exigem pelo menos um).

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só administradores podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "ADMIN") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

$erros = array();

// --- Remover um escalão ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    // Se o escalão já tiver atletas, inscrições ou atribuições associadas, a base
    // de dados vai recusar o apagar (chave estrangeira) — nesse caso avisamos o admin.
    $sql = "DELETE FROM categories WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok = mysqli_stmt_execute($stmt);

    if ($ok) {
        setFlash("success", "Escalão removido com sucesso.");
    } else {
        setFlash("error", "Não foi possível remover este escalão porque já está a ser usado (atletas, inscrições ou atribuições).");
    }

    header("Location: escaloes.php");
    exit;
}

// --- Criar ou atualizar um escalão ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $id = (int) $_POST["id"];
    $nome = trim($_POST["name"]);
    $idade_min = trim($_POST["min_age"]);
    $idade_max = trim($_POST["max_age"]);

    if ($nome == "") {
        $erros[] = "O nome do escalão é obrigatório.";
    }

    // Idades são opcionais; se preenchidas, guardamos como número, senão como null
    $idade_min = ($idade_min == "") ? null : (int) $idade_min;
    $idade_max = ($idade_max == "") ? null : (int) $idade_max;

    if (count($erros) == 0) {

        if ($id > 0) {
            $sql = "UPDATE categories SET name = ?, min_age = ?, max_age = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "siii", $nome, $idade_min, $idade_max, $id);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Escalão atualizado com sucesso.");
            } else {
                setFlash("error", "Não foi possível atualizar o escalão.");
            }
        } else {
            $sql = "INSERT INTO categories (name, min_age, max_age) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sii", $nome, $idade_min, $idade_max);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Escalão criado com sucesso.");
            } else {
                setFlash("error", "Não foi possível criar o escalão.");
            }
        }

        header("Location: escaloes.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados desse escalão para pré-preencher o formulário ---
$escalao_a_editar = null;

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT * FROM categories WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $escalao_a_editar = mysqli_fetch_assoc($resultado);
}

// --- Lista de todos os escalões ---
$resultado_lista = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");

$pageTitle = "Gerir Escalões - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="painel.php">&larr; Voltar ao painel</a>
    <h1>Gerir Escalões</h1>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $escalao_a_editar ? "Editar escalão" : "Novo escalão"; ?></h2>

    <form method="post" action="escaloes.php">
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?php echo $escalao_a_editar ? $escalao_a_editar['id'] : 0; ?>">

        <label for="name">Nome do escalão</label>
        <input type="text" id="name" name="name" required
               value="<?php echo $escalao_a_editar ? htmlspecialchars($escalao_a_editar['name']) : ''; ?>">

        <div class="form-row">
            <div>
                <label for="min_age">Idade mínima (opcional)</label>
                <input type="number" id="min_age" name="min_age" min="0"
                       value="<?php echo $escalao_a_editar ? htmlspecialchars($escalao_a_editar['min_age']) : ''; ?>">
            </div>
            <div>
                <label for="max_age">Idade máxima (opcional)</label>
                <input type="number" id="max_age" name="max_age" min="0"
                       value="<?php echo $escalao_a_editar ? htmlspecialchars($escalao_a_editar['max_age']) : ''; ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?php echo $escalao_a_editar ? "Guardar alterações" : "Criar escalão"; ?>
        </button>

        <?php if ($escalao_a_editar): ?>
            <a class="btn" href="escaloes.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<section class="competitions-list">
    <h2>Escalões existentes</h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <p>Ainda não existem escalões registados.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Idade mínima</th>
                    <th>Idade máxima</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($escalao = mysqli_fetch_assoc($resultado_lista)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($escalao["name"]); ?></td>
                        <td><?php echo $escalao["min_age"] !== null ? htmlspecialchars($escalao["min_age"]) : "-"; ?></td>
                        <td><?php echo $escalao["max_age"] !== null ? htmlspecialchars($escalao["max_age"]) : "-"; ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-small" href="escaloes.php?action=editar&id=<?php echo $escalao['id']; ?>">Editar</a>

                            <form method="post" action="escaloes.php" class="inline-form"
                                  onsubmit="return confirm('Remover este escalão? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="acao" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $escalao['id']; ?>">
                                <button type="submit" class="btn btn-small btn-danger">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php endif; ?>
</section>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
