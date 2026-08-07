<?php
// PHP_Pages/login.php
// Página de login para os 3 tipos de utilizador (ADMIN, JUDGE, USER)

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Se já tiver sessão iniciada, não faz sentido ver o login outra vez
if (isset($_SESSION["user_id"])) {
    if ($_SESSION["user_role"] == "ADMIN") {
        header("Location: admin/painel.php");
    } elseif ($_SESSION["user_role"] == "JUDGE") {
        header("Location: judge/painel.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$erro = ""; // aqui vamos guardar uma mensagem de erro, se houver

// Isto só corre quando o formulário é submetido (método POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $utilizador = trim($_POST["user_name"]);
    $palavra_passe = $_POST["password"];

    if ($utilizador == "" || $palavra_passe == "") {
        $erro = "Preenche o utilizador e a palavra-passe.";
    } else {

        // Ir buscar o utilizador à base de dados pelo nome de utilizador ou email
        $sql = "SELECT id, name, password, role, status FROM users
                WHERE user_name = ? OR user_email = ? LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $utilizador, $utilizador);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($resultado);

        // Verificar se o utilizador existe e se a palavra-passe está certa
        if (!$user) {
            $erro = "Utilizador ou palavra-passe inválidos.";
        } elseif (!password_verify($palavra_passe, $user["password"])) {
            $erro = "Utilizador ou palavra-passe inválidos.";
        } elseif (isset($user["status"]) && $user["status"] == "I") {
            $erro = "Esta conta está inativa. Fala com o administrador.";
        } else {
            // Tudo certo! Guardar os dados na sessão e redirecionar
            $_SESSION["user_id"]   = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_role"] = $user["role"];

            setFlash("success", "Sessão iniciada com sucesso. Bem-vindo, " . $user["name"] . "!");

            if ($user["role"] == "ADMIN") {
                header("Location: admin/painel.php");
            } elseif ($user["role"] == "JUDGE") {
                header("Location: judge/painel.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }
}

$pageTitle = "Entrar - " . APP_NAME;
require __DIR__ . "/../includes/header.php";
?>

<section class="form-page">
    <h1>Entrar</h1>

    <?php if ($erro != ""): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    <?php mostrarFlash(); ?>

    <form method="post" action="" class="form-card">
        <label for="user_name">Utilizador ou email</label>
        <input type="text" id="user_name" name="user_name" required>

        <label for="password">Palavra-passe</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
</section>

<?php require __DIR__ . "/../includes/footer.php"; ?>
