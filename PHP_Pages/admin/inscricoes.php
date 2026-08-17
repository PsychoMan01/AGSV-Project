<?php
// PHP_Pages/admin/inscricoes.php
// Página onde o administrador inscreve atletas numa competição, escolhendo
// o escalão, o aparelho e o número de dorsal. Sem isto, não há atletas
// para os juízes pontuarem em submeter_pontuacao.php.

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só administradores podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "ADMIN") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

$erros = array();

$competition_id = isset($_GET["competition_id"]) ? (int) $_GET["competition_id"] : 0;
if (isset($_POST["competition_id"])) {
    $competition_id = (int) $_POST["competition_id"];
}

// --- Remover uma inscrição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    // Se já existirem pontuações associadas a esta inscrição, a base de dados
    // vai recusar o apagar (chave estrangeira) — nesse caso avisamos o admin.
    $sql = "DELETE FROM event_entries WHERE id = ? AND competition_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $competition_id);
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
    $equipment_id = (int) $_POST["equipment_id"];
    $bib_number = trim($_POST["bib_number"]);

    if ($athlete_id == 0) {
        $erros[] = "Escolhe um atleta.";
    }
    if ($category_id == 0) {
        $erros[] = "Escolhe um escalão.";
    }
    if ($equipment_id == 0) {
        $erros[] = "Escolhe um aparelho.";
    }

    if (count($erros) == 0) {
        // Evitar inscrever o mesmo atleta duas vezes no mesmo escalão/aparelho
        $sql_existe = "SELECT id FROM event_entries
                        WHERE athlete_id = ? AND competition_id = ? AND category_id = ? AND equipment_id = ?
                        LIMIT 1";
        $stmt_existe = mysqli_prepare($conn, $sql_existe);
        mysqli_stmt_bind_param($stmt_existe, "iiii", $athlete_id, $competition_id, $category_id, $equipment_id);
        mysqli_stmt_execute($stmt_existe);
        $resultado_existe = mysqli_stmt_get_result($stmt_existe);
        $ja_existe = mysqli_fetch_assoc($resultado_existe);

        if ($ja_existe) {
            setFlash("error", "Este atleta já está inscrito neste escalão/aparelho nesta competição.");
        } else {
            $sql = "INSERT INTO event_entries (competition_id, athlete_id, equipment_id, category_id, bib_number)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiiis", $competition_id, $athlete_id, $equipment_id, $category_id, $bib_number);

            if (mysqli_stmt_execute($stmt)) {
                setFlash("success", "Atleta inscrito com sucesso.");
            } else {
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
    require __DIR__ . "/../../includes/header.php";
    ?>

    <section class="page-intro">
        <a href="painel.php">&larr; Voltar ao painel</a>
        <h1>Inscrever Atletas</h1>
        <p>Escolhe a competição na qual queres inscrever atletas.</p>
    </section>

    <?php mostrarFlash(); ?>

    <?php if (mysqli_num_rows($resultado_competicoes) == 0): ?>

        <p>Ainda não existem competições registadas. <a href="competicoes.php">Cria uma primeiro.</a></p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Início</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($comp = mysqli_fetch_assoc($resultado_competicoes)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($comp["name"]); ?></td>
                        <td><?php echo formatarData($comp["start_date"]); ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-small" href="inscricoes.php?competition_id=<?php echo $comp['id']; ?>">
                                Inscrever atletas
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php endif; ?>

    <?php require __DIR__ . "/../../includes/footer.php"; ?>
    <?php
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
$atletas = mysqli_query($conn, "SELECT a.id, a.name, c.name AS club_name
                                 FROM athletes a
                                 LEFT JOIN clubs c ON c.id = a.club_id
                                 ORDER BY a.name");

$categorias = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$aparelhos = mysqli_query($conn, "SELECT id, name FROM equipments ORDER BY name");

// Inscrições já existentes para esta competição
$sql_lista = "SELECT ee.id, ee.bib_number, a.name AS athlete_name, cat.name AS category_name, eq.name AS equipment_name
              FROM event_entries ee
              JOIN athletes a ON a.id = ee.athlete_id
              JOIN categories cat ON cat.id = ee.category_id
              JOIN equipments eq ON eq.id = ee.equipment_id
              WHERE ee.competition_id = ?
              ORDER BY cat.name, eq.name, a.name";
$stmt_lista = mysqli_prepare($conn, $sql_lista);
mysqli_stmt_bind_param($stmt_lista, "i", $competition_id);
mysqli_stmt_execute($stmt_lista);
$resultado_lista = mysqli_stmt_get_result($stmt_lista);

$pageTitle = "Inscrever Atletas - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="inscricoes.php">&larr; Escolher outra competição</a>
    <h1>Inscrever Atletas</h1>
    <p><strong>Competição:</strong> <?php echo htmlspecialchars($competicao["name"]); ?></p>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2>Nova inscrição</h2>

    <?php if (mysqli_num_rows($atletas) == 0 || mysqli_num_rows($categorias) == 0 || mysqli_num_rows($aparelhos) == 0): ?>
        <p>É preciso existir pelo menos um atleta, um escalão e um aparelho antes de inscrever atletas.</p>
    <?php else: ?>

        <form method="post" action="inscricoes.php">
            <input type="hidden" name="acao" value="guardar">
            <input type="hidden" name="competition_id" value="<?php echo $competition_id; ?>">

            <label for="athlete_id">Atleta</label>
            <select id="athlete_id" name="athlete_id" required>
                <option value="">-- Escolhe um atleta --</option>
                <?php while ($atleta = mysqli_fetch_assoc($atletas)): ?>
                    <option value="<?php echo $atleta['id']; ?>">
                        <?php echo htmlspecialchars($atleta["name"]); ?>
                        <?php echo $atleta["club_name"] ? " (" . htmlspecialchars($atleta["club_name"]) . ")" : ""; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <div class="form-row">
                <div>
                    <label for="category_id">Escalão</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">-- Escolhe um escalão --</option>
                        <?php mysqli_data_seek($categorias, 0); ?>
                        <?php while ($cat = mysqli_fetch_assoc($categorias)): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat["name"]); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="equipment_id">Aparelho</label>
                    <select id="equipment_id" name="equipment_id" required>
                        <option value="">-- Escolhe um aparelho --</option>
                        <?php mysqli_data_seek($aparelhos, 0); ?>
                        <?php while ($eq = mysqli_fetch_assoc($aparelhos)): ?>
                            <option value="<?php echo $eq['id']; ?>"><?php echo htmlspecialchars($eq["name"]); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <label for="bib_number">Número de dorsal (opcional)</label>
            <input type="text" id="bib_number" name="bib_number">

            <button type="submit" class="btn btn-primary">Inscrever atleta</button>
        </form>

    <?php endif; ?>
</section>

<section class="competitions-list">
    <h2>Atletas já inscritos</h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <p>Ainda não há atletas inscritos nesta competição.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Dorsal</th>
                    <th>Atleta</th>
                    <th>Escalão</th>
                    <th>Aparelho</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($e = mysqli_fetch_assoc($resultado_lista)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e["bib_number"]); ?></td>
                        <td><?php echo htmlspecialchars($e["athlete_name"]); ?></td>
                        <td><?php echo htmlspecialchars($e["category_name"]); ?></td>
                        <td><?php echo htmlspecialchars($e["equipment_name"]); ?></td>
                        <td class="actions-cell">
                            <form method="post" action="inscricoes.php" class="inline-form"
                                  onsubmit="return confirm('Remover esta inscrição?');">
                                <input type="hidden" name="acao" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                <input type="hidden" name="competition_id" value="<?php echo $competition_id; ?>">
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
