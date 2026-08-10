<?php
// PHP_Pages/admin/juizes.php
// Página onde o administrador pode criar, editar e remover juízes.
// Um "juiz" é sempre um utilizador (tabela users) com role = 'JUDGE',
// que tem também um registo associado na tabela judges (user_id).

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Só administradores podem ver esta página
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "ADMIN") {
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

$erros = array();

// --- Remover um juiz ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $user_id = (int) $_POST["user_id"];

    // Primeiro tenta apagar o registo da tabela judges, depois o utilizador.
    // Se o juiz já tiver atribuições/pontuações associadas, a base de dados
    // vai recusar o apagar (chave estrangeira) — nesse caso avisamos o admin
    // em vez de deixar a página rebentar com um erro feio.
    $sql_judge = "DELETE FROM judges WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql_judge);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);

    $sql_user = "DELETE FROM users WHERE id = ? AND role = 'JUDGE'";
    $stmt = mysqli_prepare($conn, $sql_user);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    $ok = mysqli_stmt_execute($stmt);

    if ($ok) {
        setFlash("success", "Juiz removido com sucesso.");
    } else {
        setFlash("error", "Não foi possível remover este juiz porque já tem atribuições ou pontuações associadas. Desativa-o em vez de o remover.");
    }

    header("Location: juizes.php");
    exit;
}

// --- Ativar / desativar um juiz (alternativa segura a remover) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "alternar_estado") {
    $user_id = (int) $_POST["user_id"];

    $sql = "UPDATE users SET status = IF(status = 'A', 'I', 'A') WHERE id = ? AND role = 'JUDGE'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);

    setFlash("success", "Estado do juiz atualizado.");
    header("Location: juizes.php");
    exit;
}

// --- Criar ou atualizar um juiz ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $user_id = (int) $_POST["user_id"];
    $nome = trim($_POST["name"]);
    $username = trim($_POST["user_name"]);
    $email = trim($_POST["user_email"]);
    $password = $_POST["password"]; // só é obrigatória ao criar
    $papel = $_POST["papel"];

    if ($nome == "") {
        $erros[] = "O nome é obrigatório.";
    }
    if ($username == "") {
        $erros[] = "O nome de utilizador é obrigatório.";
    }
    if ($email == "") {
        $erros[] = "O email é obrigatório.";
    }
    if ($user_id == 0 && $password == "") {
        $erros[] = "A password é obrigatória para criar um novo juiz.";
    }
    if (!array_key_exists($papel, papeis())) {
        $erros[] = "Escolhe um papel válido para o juiz.";
    }

    if (count($erros) == 0) {

        if ($user_id > 0) {
            // Atualizar um juiz existente
            if ($password != "") {
                // Foi indicada uma nova password: atualiza-a também
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name = ?, user_name = ?, user_email = ?, password = ? WHERE id = ? AND role = 'JUDGE'";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssi", $nome, $username, $email, $hash, $user_id);
            } else {
                $sql = "UPDATE users SET name = ?, user_name = ?, user_email = ? WHERE id = ? AND role = 'JUDGE'";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssi", $nome, $username, $email, $user_id);
            }
            mysqli_stmt_execute($stmt);

            $sql_papel = "UPDATE judges SET papel = ? WHERE user_id = ?";
            $stmt_papel = mysqli_prepare($conn, $sql_papel);
            mysqli_stmt_bind_param($stmt_papel, "si", $papel, $user_id);
            mysqli_stmt_execute($stmt_papel);

            setFlash("success", "Juiz atualizado com sucesso.");
        } else {
            // Criar um novo juiz: primeiro o utilizador, depois o registo em judges
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, user_name, user_email, password, role, status)
                    VALUES (?, ?, ?, ?, 'JUDGE', 'A')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssss", $nome, $username, $email, $hash);

            if (mysqli_stmt_execute($stmt)) {
                $novo_user_id = mysqli_insert_id($conn);

                $sql2 = "INSERT INTO judges (user_id, papel) VALUES (?, ?)";
                $stmt2 = mysqli_prepare($conn, $sql2);
                mysqli_stmt_bind_param($stmt2, "is", $novo_user_id, $papel);
                mysqli_stmt_execute($stmt2);

                setFlash("success", "Juiz criado com sucesso.");
            } else {
                setFlash("error", "Não foi possível criar o juiz. O nome de utilizador ou email já podem estar em uso.");
            }
        }

        header("Location: juizes.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados desse juiz para pré-preencher o formulário ---
$juiz_a_editar = null;

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT u.*, j.papel FROM users u
            JOIN judges j ON j.user_id = u.id
            WHERE u.id = ? AND u.role = 'JUDGE' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $juiz_a_editar = mysqli_fetch_assoc($resultado);
}

// --- Lista de todos os juízes ---
$resultado_lista = mysqli_query($conn, "SELECT u.*, j.papel FROM users u
                                         JOIN judges j ON j.user_id = u.id
                                         WHERE u.role = 'JUDGE'
                                         ORDER BY u.name");

$pageTitle = "Gerir Juízes - " . APP_NAME;
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a href="painel.php">&larr; Voltar ao painel</a>
    <h1>Gerir Juízes</h1>
</section>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
<?php endforeach; ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $juiz_a_editar ? "Editar juiz" : "Novo juiz"; ?></h2>

    <form method="post" action="juizes.php">
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="user_id" value="<?php echo $juiz_a_editar ? $juiz_a_editar['id'] : 0; ?>">

        <label for="name">Nome</label>
        <input type="text" id="name" name="name" required
               value="<?php echo $juiz_a_editar ? htmlspecialchars($juiz_a_editar['name']) : ''; ?>">

        <div class="form-row">
            <div>
                <label for="user_name">Nome de utilizador</label>
                <input type="text" id="user_name" name="user_name" required
                       value="<?php echo $juiz_a_editar ? htmlspecialchars($juiz_a_editar['user_name']) : ''; ?>">
            </div>
            <div>
                <label for="user_email">Email</label>
                <input type="email" id="user_email" name="user_email" required
                       value="<?php echo $juiz_a_editar ? htmlspecialchars($juiz_a_editar['user_email']) : ''; ?>">
            </div>
        </div>

        <label for="password">
            Password
            <?php echo $juiz_a_editar ? "(deixa em branco para manter a atual)" : ""; ?>
        </label>
        <input type="password" id="password" name="password" <?php echo $juiz_a_editar ? "" : "required"; ?>>

        <label for="papel">Papel</label>
        <select id="papel" name="papel" required>
            <option value="">-- Escolhe um papel --</option>
            <?php foreach (papeis() as $codigo => $rotulo): ?>
                <option value="<?php echo $codigo; ?>"
                    <?php echo ($juiz_a_editar && $juiz_a_editar["papel"] == $codigo) ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($rotulo); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary">
            <?php echo $juiz_a_editar ? "Guardar alterações" : "Criar juiz"; ?>
        </button>

        <?php if ($juiz_a_editar): ?>
            <a class="btn" href="juizes.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<section class="competitions-list">
    <h2>Juízes existentes</h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <p>Ainda não existem juízes registados.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Utilizador</th>
                    <th>Email</th>
                    <th>Papel</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($juiz = mysqli_fetch_assoc($resultado_lista)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($juiz["name"]); ?></td>
                        <td><?php echo htmlspecialchars($juiz["user_name"]); ?></td>
                        <td><?php echo htmlspecialchars($juiz["user_email"]); ?></td>
                        <td><?php echo htmlspecialchars(papelLabel($juiz["papel"])); ?></td>
                        <td><?php echo $juiz["status"] == "A" ? "Ativo" : "Inativo"; ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-small" href="juizes.php?action=editar&id=<?php echo $juiz['id']; ?>">Editar</a>

                            <form method="post" action="juizes.php" class="inline-form">
                                <input type="hidden" name="acao" value="alternar_estado">
                                <input type="hidden" name="user_id" value="<?php echo $juiz['id']; ?>">
                                <button type="submit" class="btn btn-small">
                                    <?php echo $juiz["status"] == "A" ? "Desativar" : "Ativar"; ?>
                                </button>
                            </form>

                            <form method="post" action="juizes.php" class="inline-form"
                                  onsubmit="return confirm('Remover este juiz? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="acao" value="eliminar">
                                <input type="hidden" name="user_id" value="<?php echo $juiz['id']; ?>">
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