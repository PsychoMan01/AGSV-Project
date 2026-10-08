<?php
// PHP_Pages/admin/clubes.php
// Página onde o administrador pode criar, editar e remover clubes.

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Área administrativa: ADMIN tem sempre acesso; um USER só entra se
// lhe tiver sido atribuído o menu correspondente (ver Parametrizações).
requireAdminArea($conn);
requireTransaction($conn, "CLUBES_GERIR");

// Todos os pedidos POST desta página alteram dados: exigimos sempre um
// token CSRF válido antes de processar qualquer ação.
exigirCsrf();

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
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Clube atualizado com sucesso.");
            } else {
                setFlash("error", "Não foi possível atualizar o clube.");
            }
        } else {
            $sql = "INSERT INTO clubs (name, logo) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $nome, $logo);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Clube criado com sucesso.");
            } else {
                setFlash("error", "Não foi possível criar o clube.");
            }
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
$pagina_atual = "admin_clubes";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Gerir Clubes</h1>
    <p>Clubes a que os atletas pertencem. Um clube com atletas associados não pode ser removido.</p>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $clube_a_editar ? "Editar clube" : "Novo clube"; ?></h2>

    <form method="post" action="clubes.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?php echo $clube_a_editar ? $clube_a_editar['id'] : 0; ?>">

        <label for="name">Nome do clube</label>
        <input type="text" id="name" name="name" required
               value="<?php echo $clube_a_editar ? htmlspecialchars($clube_a_editar['name']) : ''; ?>">

        <label for="logo">Logotipo (URL) <span class="label-optional">(opcional)</span></label>
        <input type="text" id="logo" name="logo" placeholder="https://" inputmode="url"
               value="<?php echo $clube_a_editar ? htmlspecialchars($clube_a_editar['logo'] ?? "") : ''; ?>">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?php echo $clube_a_editar ? "Guardar alterações" : "Criar clube"; ?>
            </button>

            <?php if ($clube_a_editar): ?>
                <a class="btn" href="clubes.php">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="competitions-list">
    <h2>Clubes existentes<span class="count-pill"><?php echo mysqli_num_rows($resultado_lista); ?></span></h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Ainda não existem clubes registados.</p>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Atletas</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($clube = mysqli_fetch_assoc($resultado_lista)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($clube["name"]); ?></td>
                            <td class="num" data-label="Atletas"><?php echo (int) $clube["total_atletas"]; ?></td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <a class="btn btn-small" href="clubes.php?action=editar&id=<?php echo $clube['id']; ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</a>

                                    <form method="post" action="clubes.php" class="inline-form" data-confirm="Remover este clube? Esta ação não pode ser desfeita.">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $clube['id']; ?>">
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
