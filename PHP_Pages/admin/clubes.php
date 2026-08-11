<?php
// PHP_Pages/admin/clubes.php
// Página onde o administrador pode criar, editar e remover clubes.

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só administradores podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "ADMIN") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

$erros = array();

// --- Remover um clube ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    // Se o clube já tiver atletas associados, a base de dados vai recusar
    // o apagar (chave estrangeira) — nesse caso avisamos o admin.
    $sql = "DELETE FROM clubs WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok = mysqli_stmt_execute($stmt);

    if ($ok) {
        setFlash("success", "Clube removido com sucesso.");
    } else {
        setFlash("error", "Não foi possível remover este clube porque já tem atletas associados.");
    }

    header("Location: clubes.php");
    exit;
}

// --- Criar ou atualizar um clube ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $id = (int) $_POST["id"];
    $nome = trim($_POST["name"]);
    $logo = trim($_POST["logo"]);

    if ($nome == "") {
        $erros[] = "O nome do clube é obrigatório.";
    }

    if (count($erros) == 0) {

        if ($id > 0) {
            $sql = "UPDATE clubs SET name = ?, logo = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $nome, $logo, $id);
            mysqli_stmt_execute($stmt);

            setFlash("success", "Clube atualizado com sucesso.");
        } else {
            $sql = "INSERT INTO clubs (name, logo) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $nome, $logo);
            mysqli_stmt_execute($stmt);

            setFlash("success", "Clube criado com sucesso.");
        }

        header("Location: clubes.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados desse clube para pré-preencher o formulário ---
$clube_a_editar = null;

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT * FROM clubs WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $clube_a_editar = mysqli_fetch_assoc($resultado);
}

// --- Lista de todos os clubes, com o número de atletas de cada um ---
$resultado_lista = mysqli_query($conn, "SELECT c.*, COUNT(a.id) AS total_atletas
                                         FROM clubs c
                                         LEFT JOIN athletes a ON a.club_id = c.id
                                         GROUP BY c.id
                                         ORDER BY c.name");

$pageTitle = "Gerir Clubes - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="painel.php">&larr; Voltar ao painel</a>
    <h1>Gerir Clubes</h1>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $clube_a_editar ? "Editar clube" : "Novo clube"; ?></h2>

    <form method="post" action="clubes.php">
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?php echo $clube_a_editar ? $clube_a_editar['id'] : 0; ?>">

        <label for="name">Nome do clube</label>
        <input type="text" id="name" name="name" required
               value="<?php echo $clube_a_editar ? htmlspecialchars($clube_a_editar['name']) : ''; ?>">

        <label for="logo">Logotipo (URL, opcional)</label>
        <input type="text" id="logo" name="logo"
               value="<?php echo $clube_a_editar ? htmlspecialchars($clube_a_editar['logo']) : ''; ?>">

        <button type="submit" class="btn btn-primary">
            <?php echo $clube_a_editar ? "Guardar alterações" : "Criar clube"; ?>
        </button>

        <?php if ($clube_a_editar): ?>
            <a class="btn" href="clubes.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<section class="competitions-list">
    <h2>Clubes existentes</h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <p>Ainda não existem clubes registados.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Atletas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($clube = mysqli_fetch_assoc($resultado_lista)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($clube["name"]); ?></td>
                        <td><?php echo $clube["total_atletas"]; ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-small" href="clubes.php?action=editar&id=<?php echo $clube['id']; ?>">Editar</a>

                            <form method="post" action="clubes.php" class="inline-form"
                                  onsubmit="return confirm('Remover este clube? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="acao" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $clube['id']; ?>">
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
