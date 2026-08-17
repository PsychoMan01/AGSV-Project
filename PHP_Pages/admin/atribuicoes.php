<?php
// PHP_Pages/admin/atribuicoes.php
// Página onde o administrador atribui juízes a uma competição,
// escolhendo o escalão, o aparelho e o papel (A, E, DB, DA) de cada atribuição.

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

// --- Remover uma atribuição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    $sql = "DELETE FROM judge_assignments WHERE id = ? AND competition_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $competition_id);
    mysqli_stmt_execute($stmt);

    setFlash("success", "Atribuição removida.");
    header("Location: atribuicoes.php?competition_id=" . $competition_id);
    exit;
}

// --- Adicionar uma nova atribuição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $judge_id = (int) $_POST["judge_id"];
    $category_id = (int) $_POST["category_id"];
    $equipment_id = (int) $_POST["equipment_id"];
    $papel = $_POST["papel"];

    if ($judge_id == 0) {
        $erros[] = "Escolhe um juiz.";
    }
    if ($category_id == 0) {
        $erros[] = "Escolhe um escalão.";
    }
    if ($equipment_id == 0) {
        $erros[] = "Escolhe um aparelho.";
    }
    if (!array_key_exists($papel, papeis())) {
        $erros[] = "Escolhe um papel válido.";
    }

    if (count($erros) == 0) {
        // Regra 1: um juiz não pode ter a mesma atribuição duas vezes
        // (mesmo escalão + aparelho + competição).
        $sql_existe = "SELECT id FROM judge_assignments
                        WHERE judge_id = ? AND competition_id = ? AND category_id = ? AND equipment_id = ?
                        LIMIT 1";
        $stmt_existe = mysqli_prepare($conn, $sql_existe);
        mysqli_stmt_bind_param($stmt_existe, "iiii", $judge_id, $competition_id, $category_id, $equipment_id);
        mysqli_stmt_execute($stmt_existe);
        $resultado_existe = mysqli_stmt_get_result($stmt_existe);
        $ja_existe = mysqli_fetch_assoc($resultado_existe);

        // Regra 2: só pode haver um juiz por cada papel (A, E, DB, DA) em cada
        // competição. Ou seja, o papel "E" nesta competição tem sempre de ser
        // o mesmo juiz em todas as atribuições dele, e não pode ser dado a
        // outro juiz diferente.
        $juiz_do_papel = null;
        if (!$ja_existe) {
            $sql_papel = "SELECT DISTINCT judge_id FROM judge_assignments
                          WHERE competition_id = ? AND papel = ? AND judge_id != ?
                          LIMIT 1";
            $stmt_papel = mysqli_prepare($conn, $sql_papel);
            mysqli_stmt_bind_param($stmt_papel, "isi", $competition_id, $papel, $judge_id);
            mysqli_stmt_execute($stmt_papel);
            $resultado_papel = mysqli_stmt_get_result($stmt_papel);
            $juiz_do_papel = mysqli_fetch_assoc($resultado_papel);
        }

        if ($ja_existe) {
            setFlash("error", "Este juiz já está atribuído a este escalão/aparelho nesta competição.");
        } elseif ($juiz_do_papel) {
            setFlash("error", "Já existe outro juiz atribuído ao papel \"" . papelLabel($papel) . "\" nesta competição. Só pode haver um juiz por papel em cada competição.");
        } else {
            $sql = "INSERT INTO judge_assignments (judge_id, competition_id, category_id, equipment_id, papel)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiiis", $judge_id, $competition_id, $category_id, $equipment_id, $papel);

            if (mysqli_stmt_execute($stmt)) {
                setFlash("success", "Juiz atribuído com sucesso.");
            } else {
                setFlash("error", "Não foi possível atribuir o juiz. Verifica se o juiz escolhido é válido.");
            }
        }

        header("Location: atribuicoes.php?competition_id=" . $competition_id);
        exit;
    }
}

// --- Se não foi indicada nenhuma competição, mostrar a lista para escolher ---
if ($competition_id == 0) {
    $resultado_competicoes = mysqli_query($conn, "SELECT * FROM competitions ORDER BY start_date DESC");

    $pageTitle = "Atribuir Juízes - " . APP_NAME;
    require __DIR__ . "/../../includes/header.php";
    ?>

    <section class="page-intro">
        <a href="painel.php">&larr; Voltar ao painel</a>
        <h1>Atribuir Juízes</h1>
        <p>Escolhe a competição à qual queres atribuir juízes.</p>
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
                            <a class="btn btn-small" href="atribuicoes.php?competition_id=<?php echo $comp['id']; ?>">
                                Atribuir juízes
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
    header("Location: atribuicoes.php");
    exit;
}

// Listas para preencher os <select> do formulário
$juizes = mysqli_query($conn, "SELECT j.id, u.name, j.papel
                                FROM users u
                                JOIN judges j ON j.user_id = u.id
                                WHERE u.role = 'JUDGE' AND u.status = 'A'
                                ORDER BY u.name");

$categorias = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$aparelhos = mysqli_query($conn, "SELECT id, name FROM equipments ORDER BY name");

// Atribuições já existentes para esta competição
$sql_lista = "SELECT ja.id, ja.papel, u.name AS judge_name, cat.name AS category_name, eq.name AS equipment_name
              FROM judge_assignments ja
              JOIN judges j ON j.id = ja.judge_id
              JOIN users u ON u.id = j.user_id
              JOIN categories cat ON cat.id = ja.category_id
              JOIN equipments eq ON eq.id = ja.equipment_id
              WHERE ja.competition_id = ?
              ORDER BY u.name";
$stmt_lista = mysqli_prepare($conn, $sql_lista);
mysqli_stmt_bind_param($stmt_lista, "i", $competition_id);
mysqli_stmt_execute($stmt_lista);
$resultado_lista = mysqli_stmt_get_result($stmt_lista);

$pageTitle = "Atribuir Juízes - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="atribuicoes.php">&larr; Escolher outra competição</a>
    <h1>Atribuir Juízes</h1>
    <p><strong>Competição:</strong> <?php echo htmlspecialchars($competicao["name"]); ?></p>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2>Nova atribuição</h2>

    <?php if (mysqli_num_rows($categorias) == 0 || mysqli_num_rows($aparelhos) == 0): ?>
        <p>É preciso existir pelo menos um escalão e um aparelho na base de dados antes de atribuir juízes.</p>
    <?php else: ?>

        <form method="post" action="atribuicoes.php" id="form-atribuicao">
            <input type="hidden" name="acao" value="guardar">
            <input type="hidden" name="competition_id" value="<?php echo $competition_id; ?>">

            <label for="judge_id">Juiz</label>
            <select id="judge_id" name="judge_id" required>
                <option value="">-- Escolhe um juiz --</option>
                <?php mysqli_data_seek($juizes, 0); ?>
                <?php while ($juiz = mysqli_fetch_assoc($juizes)): ?>
                    <option value="<?php echo $juiz['id']; ?>" data-papel="<?php echo htmlspecialchars($juiz['papel']); ?>">
                        <?php echo htmlspecialchars($juiz["name"]); ?> (<?php echo htmlspecialchars($juiz["papel"]); ?>)
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

            <label for="papel">Papel nesta competição</label>
            <select id="papel" name="papel" required>
                <option value="">-- Escolhe um papel --</option>
                <?php foreach (papeis() as $codigo => $rotulo): ?>
                    <option value="<?php echo $codigo; ?>"><?php echo htmlspecialchars($rotulo); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Atribuir juiz</button>
        </form>

        <script>
            // Pequeno atalho: ao escolher um juiz, sugere automaticamente o papel
            // que esse juiz costuma ter (o admin pode sempre alterar antes de gravar).
            document.getElementById("judge_id").addEventListener("change", function () {
                var opcaoEscolhida = this.options[this.selectedIndex];
                var papelSugerido = opcaoEscolhida.getAttribute("data-papel");
                if (papelSugerido) {
                    document.getElementById("papel").value = papelSugerido;
                }
            });
        </script>

    <?php endif; ?>
</section>

<section class="competitions-list">
    <h2>Juízes já atribuídos</h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <p>Ainda não há juízes atribuídos a esta competição.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Juiz</th>
                    <th>Papel</th>
                    <th>Escalão</th>
                    <th>Aparelho</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($resultado_lista)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a["judge_name"]); ?></td>
                        <td><?php echo htmlspecialchars(papelLabel($a["papel"])); ?></td>
                        <td><?php echo htmlspecialchars($a["category_name"]); ?></td>
                        <td><?php echo htmlspecialchars($a["equipment_name"]); ?></td>
                        <td class="actions-cell">
                            <form method="post" action="atribuicoes.php" class="inline-form"
                                  onsubmit="return confirm('Remover esta atribuição?');">
                                <input type="hidden" name="acao" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
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
