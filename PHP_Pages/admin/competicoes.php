<?php
// PHP_Pages/admin/competicoes.php
// Página onde o administrador pode criar, editar, eliminar e publicar competições

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só administradores podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "ADMIN") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

$erros = array();

// --- Eliminar uma competição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    $sql = "DELETE FROM competitions WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    setFlash("success", "Competição eliminada.");
    header("Location: competicoes.php");
    exit;
}

// --- Publicar ou despublicar os resultados ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "publicar") {
    $id = (int) $_POST["id"];

    $sql = "UPDATE competitions SET results_published = NOT results_published WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    setFlash("success", "Estado de publicação atualizado.");
    header("Location: competicoes.php");
    exit;
}

// --- Criar ou atualizar uma competição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $id = (int) $_POST["id"];
    $nome = trim($_POST["name"]);
    $local = trim($_POST["location"]);
    $data_inicio = $_POST["start_date"];
    $data_fim = $_POST["end_date"];
    $stream_url = trim($_POST["stream_url"]);

    // A checkbox só vem no $_POST se estiver marcada
    $ativa = isset($_POST["status"]) ? 1 : 0;

    if ($nome == "") {
        $erros[] = "O nome da competição é obrigatório.";
    }
    if ($data_inicio == "" || $data_fim == "") {
        $erros[] = "As datas de início e fim são obrigatórias.";
    }

    if (count($erros) == 0) {

        if ($id > 0) {
            // Atualizar uma competição existente
            $sql = "UPDATE competitions
                    SET name = ?, location = ?, start_date = ?, end_date = ?, stream_url = ?, status = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssii", $nome, $local, $data_inicio, $data_fim, $stream_url, $ativa, $id);
            mysqli_stmt_execute($stmt);

            setFlash("success", "Competição atualizada com sucesso.");
        } else {
            // Criar uma nova competição
            $sql = "INSERT INTO competitions (name, location, start_date, end_date, stream_url, status, results_published)
                    VALUES (?, ?, ?, ?, ?, ?, 0)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssi", $nome, $local, $data_inicio, $data_fim, $stream_url, $ativa);
            mysqli_stmt_execute($stmt);

            setFlash("success", "Competição criada com sucesso.");
        }

        header("Location: competicoes.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados dessa competição para pré-preencher o formulário ---
$competicao_a_editar = null;

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT * FROM competitions WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $competicao_a_editar = mysqli_fetch_assoc($resultado);
}

// --- Lista de todas as competições ---
$resultado_lista = mysqli_query($conn, "SELECT * FROM competitions ORDER BY start_date DESC");

$pageTitle = "Gerir Competições - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="painel.php">&larr; Voltar ao painel</a>
    <h1>Gerir Competições</h1>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $competicao_a_editar ? "Editar competição" : "Nova competição"; ?></h2>

    <form method="post" action="competicoes.php">
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?php echo $competicao_a_editar ? $competicao_a_editar['id'] : 0; ?>">

        <label for="name">Nome</label>
        <input type="text" id="name" name="name" required
               value="<?php echo $competicao_a_editar ? htmlspecialchars($competicao_a_editar['name']) : ''; ?>">

        <label for="location">Local</label>
        <input type="text" id="location" name="location"
               value="<?php echo $competicao_a_editar ? htmlspecialchars($competicao_a_editar['location']) : ''; ?>">

        <div class="form-row">
            <div>
                <label for="start_date">Data de início</label>
                <input type="date" id="start_date" name="start_date" required
                       value="<?php echo $competicao_a_editar ? $competicao_a_editar['start_date'] : ''; ?>">
            </div>
            <div>
                <label for="end_date">Data de fim</label>
                <input type="date" id="end_date" name="end_date" required
                       value="<?php echo $competicao_a_editar ? $competicao_a_editar['end_date'] : ''; ?>">
            </div>
        </div>

        <label for="stream_url">URL de transmissão (opcional)</label>
        <input type="text" id="stream_url" name="stream_url"
               value="<?php echo $competicao_a_editar ? htmlspecialchars($competicao_a_editar['stream_url']) : ''; ?>">

        <label class="checkbox-label">
            <?php
            $marcada = true; // por defeito, uma competição nova começa ativa
            if ($competicao_a_editar && $competicao_a_editar["status"] != 1) {
                $marcada = false;
            }
            ?>
            <input type="checkbox" name="status" value="1" <?php echo $marcada ? "checked" : ""; ?>>
            Competição ativa
        </label>

        <button type="submit" class="btn btn-primary">
            <?php echo $competicao_a_editar ? "Guardar alterações" : "Criar competição"; ?>
        </button>

        <?php if ($competicao_a_editar): ?>
            <a class="btn" href="competicoes.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<section class="competitions-list">
    <h2>Competições existentes</h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <p>Ainda não existem competições registadas.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Local</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Estado</th>
                    <th>Resultados</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($comp = mysqli_fetch_assoc($resultado_lista)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($comp["name"]); ?></td>
                        <td><?php echo htmlspecialchars($comp["location"]); ?></td>
                        <td><?php echo formatarData($comp["start_date"]); ?></td>
                        <td><?php echo formatarData($comp["end_date"]); ?></td>
                        <td><?php echo $comp["status"] == 1 ? "Ativa" : "Inativa"; ?></td>
                        <td><?php echo $comp["results_published"] == 1 ? "Publicados" : "Por publicar"; ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-small" href="competicoes.php?action=editar&id=<?php echo $comp['id']; ?>">Editar</a>

                            <a class="btn btn-small" href="atribuicoes.php?competition_id=<?php echo $comp['id']; ?>">Juízes</a>

                            <form method="post" action="competicoes.php" class="inline-form">
                                <input type="hidden" name="acao" value="publicar">
                                <input type="hidden" name="id" value="<?php echo $comp['id']; ?>">
                                <button type="submit" class="btn btn-small">
                                    <?php echo $comp["results_published"] == 1 ? "Despublicar" : "Publicar"; ?>
                                </button>
                            </form>

                            <form method="post" action="competicoes.php" class="inline-form"
                                  onsubmit="return confirm('Eliminar esta competição? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="acao" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $comp['id']; ?>">
                                <button type="submit" class="btn btn-small btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php endif; ?>
</section>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
