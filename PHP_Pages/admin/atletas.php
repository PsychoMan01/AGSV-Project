<?php
// PHP_Pages/admin/atletas.php
// Página onde o administrador pode criar, editar e remover atletas.
// Cada atleta pertence a um clube e, opcionalmente, a uma categoria/escalão.

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só administradores podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "ADMIN") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

$erros = array();

// --- Remover um atleta ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    // Se o atleta já tiver inscrições/pontuações associadas, a base de dados
    // vai recusar o apagar (chave estrangeira) — nesse caso avisamos o admin.
    $sql = "DELETE FROM athletes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok = mysqli_stmt_execute($stmt);

    if ($ok) {
        setFlash("success", "Atleta removido com sucesso.");
    } else {
        setFlash("error", "Não foi possível remover este atleta porque já tem inscrições ou pontuações associadas.");
    }

    header("Location: atletas.php");
    exit;
}

// --- Criar ou atualizar um atleta ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $id = (int) $_POST["id"];
    $nome = trim($_POST["name"]);
    $data_nascimento = $_POST["birth_date"];
    $genero = $_POST["gender"];
    $local_nascimento = trim($_POST["place_of_birth"]);
    $telefone = trim($_POST["phone_number"]);
    $email = trim($_POST["email"]);
    $foto = trim($_POST["photo"]);
    $club_id = (int) $_POST["club_id"];
    $category_id = (int) $_POST["category_id"]; // 0 = sem categoria

    if ($nome == "") {
        $erros[] = "O nome é obrigatório.";
    }
    if ($data_nascimento == "") {
        $erros[] = "A data de nascimento é obrigatória.";
    }
    if ($local_nascimento == "") {
        $erros[] = "A naturalidade é obrigatória.";
    }
    if ($telefone == "") {
        $erros[] = "O telefone é obrigatório.";
    }
    if ($email == "") {
        $erros[] = "O email é obrigatório.";
    }
    if ($club_id == 0) {
        $erros[] = "Escolhe o clube do atleta.";
    }
    if (!array_key_exists($genero, generos())) {
        $erros[] = "Escolhe um género válido.";
    }

    if (count($erros) == 0) {

        // category_id pode ficar por preencher (NULL na base de dados)
        $category_id_param = $category_id > 0 ? $category_id : null;

        if ($id > 0) {
            $sql = "UPDATE athletes
                    SET name = ?, birth_date = ?, gender = ?, place_of_birth = ?,
                        phone_number = ?, email = ?, photo = ?, club_id = ?, category_id = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssiii", $nome, $data_nascimento, $genero, $local_nascimento,
                $telefone, $email, $foto, $club_id, $category_id_param, $id);
            mysqli_stmt_execute($stmt);

            setFlash("success", "Atleta atualizado com sucesso.");
        } else {
            $sql = "INSERT INTO athletes (name, birth_date, gender, place_of_birth, phone_number, email, photo, club_id, category_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssii", $nome, $data_nascimento, $genero, $local_nascimento,
                $telefone, $email, $foto, $club_id, $category_id_param);
            mysqli_stmt_execute($stmt);

            setFlash("success", "Atleta criado com sucesso.");
        }

        header("Location: atletas.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados desse atleta para pré-preencher o formulário ---
$atleta_a_editar = null;

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT * FROM athletes WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $atleta_a_editar = mysqli_fetch_assoc($resultado);
}

// Listas para preencher os <select> do formulário
$clubes = mysqli_query($conn, "SELECT id, name FROM clubs ORDER BY name");
$categorias = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");

// --- Lista de todos os atletas ---
$resultado_lista = mysqli_query($conn, "SELECT a.*, c.name AS club_name, cat.name AS category_name
                                         FROM athletes a
                                         LEFT JOIN clubs c ON c.id = a.club_id
                                         LEFT JOIN categories cat ON cat.id = a.category_id
                                         ORDER BY a.name");

$pageTitle = "Gerir Atletas - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="painel.php">&larr; Voltar ao painel</a>
    <h1>Gerir Atletas</h1>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $atleta_a_editar ? "Editar atleta" : "Novo atleta"; ?></h2>

    <?php if (mysqli_num_rows($clubes) == 0): ?>
        <p>É preciso existir pelo menos um clube antes de registares atletas. <a href="clubes.php">Cria um clube primeiro.</a></p>
    <?php else: ?>

        <form method="post" action="atletas.php">
            <input type="hidden" name="acao" value="guardar">
            <input type="hidden" name="id" value="<?php echo $atleta_a_editar ? $atleta_a_editar['id'] : 0; ?>">

            <label for="name">Nome</label>
            <input type="text" id="name" name="name" required
                   value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['name']) : ''; ?>">

            <div class="form-row">
                <div>
                    <label for="birth_date">Data de nascimento</label>
                    <input type="date" id="birth_date" name="birth_date" required
                           value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['birth_date']) : ''; ?>">
                </div>
                <div>
                    <label for="gender">Género</label>
                    <select id="gender" name="gender" required>
                        <option value="">-- Escolhe --</option>
                        <?php foreach (generos() as $codigo => $rotulo): ?>
                            <option value="<?php echo $codigo; ?>"
                                <?php echo ($atleta_a_editar && $atleta_a_editar["gender"] == $codigo) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($rotulo); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label for="place_of_birth">Naturalidade</label>
            <input type="text" id="place_of_birth" name="place_of_birth" required
                   value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['place_of_birth']) : ''; ?>">

            <div class="form-row">
                <div>
                    <label for="phone_number">Telefone</label>
                    <input type="text" id="phone_number" name="phone_number" required
                           value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['phone_number']) : ''; ?>">
                </div>
                <div>
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['email']) : ''; ?>">
                </div>
            </div>

            <label for="photo">Foto (URL, opcional)</label>
            <input type="text" id="photo" name="photo"
                   value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['photo']) : ''; ?>">

            <div class="form-row">
                <div>
                    <label for="club_id">Clube</label>
                    <select id="club_id" name="club_id" required>
                        <option value="">-- Escolhe um clube --</option>
                        <?php while ($clube = mysqli_fetch_assoc($clubes)): ?>
                            <option value="<?php echo $clube['id']; ?>"
                                <?php echo ($atleta_a_editar && $atleta_a_editar["club_id"] == $clube["id"]) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($clube["name"]); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="category_id">Escalão (opcional)</label>
                    <select id="category_id" name="category_id">
                        <option value="0">-- Sem escalão --</option>
                        <?php mysqli_data_seek($categorias, 0); ?>
                        <?php while ($cat = mysqli_fetch_assoc($categorias)): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo ($atleta_a_editar && $atleta_a_editar["category_id"] == $cat["id"]) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($cat["name"]); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <?php echo $atleta_a_editar ? "Guardar alterações" : "Criar atleta"; ?>
            </button>

            <?php if ($atleta_a_editar): ?>
                <a class="btn" href="atletas.php">Cancelar</a>
            <?php endif; ?>
        </form>

    <?php endif; ?>
</section>

<section class="competitions-list">
    <h2>Atletas existentes</h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <p>Ainda não existem atletas registados.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Clube</th>
                    <th>Escalão</th>
                    <th>Género</th>
                    <th>Nascimento</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($atleta = mysqli_fetch_assoc($resultado_lista)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($atleta["name"]); ?></td>
                        <td><?php echo htmlspecialchars($atleta["club_name"] ?? "-"); ?></td>
                        <td><?php echo htmlspecialchars($atleta["category_name"] ?? "-"); ?></td>
                        <td><?php echo htmlspecialchars(generoLabel($atleta["gender"])); ?></td>
                        <td><?php echo formatarData($atleta["birth_date"]); ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-small" href="atletas.php?action=editar&id=<?php echo $atleta['id']; ?>">Editar</a>

                            <form method="post" action="atletas.php" class="inline-form"
                                  onsubmit="return confirm('Remover este atleta? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="acao" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $atleta['id']; ?>">
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
