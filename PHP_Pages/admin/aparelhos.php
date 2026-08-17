<?php
// PHP_Pages/admin/aparelhos.php
// Página onde o administrador pode criar, editar e remover aparelhos (equipments).
// Sem aparelhos registados, não é possível atribuir juízes nem inscrever atletas
// em competições (atribuicoes.php e inscricoes.php exigem pelo menos um).

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só administradores podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "ADMIN") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

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

    if ($nome == "") {
        $erros[] = "O nome do aparelho é obrigatório.";
    }

    if (count($erros) == 0) {

        if ($id > 0) {
            $sql = "UPDATE equipments SET name = ?, description = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $nome, $descricao, $id);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Aparelho atualizado com sucesso.");
            } else {
                setFlash("error", "Não foi possível atualizar o aparelho.");
            }
        } else {
            $sql = "INSERT INTO equipments (name, description) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $nome, $descricao);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Aparelho criado com sucesso.");
            } else {
                setFlash("error", "Não foi possível criar o aparelho.");
            }
        }

        header("Location: aparelhos.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados desse aparelho para pré-preencher o formulário ---
$aparelho_a_editar = null;

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT * FROM equipments WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $aparelho_a_editar = mysqli_fetch_assoc($resultado);
}

// --- Lista de todos os aparelhos ---
$resultado_lista = mysqli_query($conn, "SELECT * FROM equipments ORDER BY name");

$pageTitle = "Gerir Aparelhos - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="painel.php">&larr; Voltar ao painel</a>
    <h1>Gerir Aparelhos</h1>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $aparelho_a_editar ? "Editar aparelho" : "Novo aparelho"; ?></h2>

    <form method="post" action="aparelhos.php">
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?php echo $aparelho_a_editar ? $aparelho_a_editar['id'] : 0; ?>">

        <label for="name">Nome do aparelho</label>
        <input type="text" id="name" name="name" required
               value="<?php echo $aparelho_a_editar ? htmlspecialchars($aparelho_a_editar['name']) : ''; ?>">

        <label for="description">Descrição (opcional)</label>
        <input type="text" id="description" name="description"
               value="<?php echo $aparelho_a_editar ? htmlspecialchars($aparelho_a_editar['description']) : ''; ?>">

        <button type="submit" class="btn btn-primary">
            <?php echo $aparelho_a_editar ? "Guardar alterações" : "Criar aparelho"; ?>
        </button>

        <?php if ($aparelho_a_editar): ?>
            <a class="btn" href="aparelhos.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<section class="competitions-list">
    <h2>Aparelhos existentes</h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <p>Ainda não existem aparelhos registados.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($aparelho = mysqli_fetch_assoc($resultado_lista)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($aparelho["name"]); ?></td>
                        <td><?php echo htmlspecialchars($aparelho["description"]); ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-small" href="aparelhos.php?action=editar&id=<?php echo $aparelho['id']; ?>">Editar</a>

                            <form method="post" action="aparelhos.php" class="inline-form"
                                  onsubmit="return confirm('Remover este aparelho? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="acao" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $aparelho['id']; ?>">
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
