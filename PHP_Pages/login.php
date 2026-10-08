<?php
// PHP_Pages/login.php
// Página de login para os 3 tipos de utilizador (ADMIN, JUDGE, USER)

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Se já tiver sessão iniciada, não faz sentido ver o login outra vez
if (isset($_SESSION["user_id"])) {
    if ($_SESSION["user_role"] == "ADMIN" || $_SESSION["user_role"] == "USER") {
        header("Location: admin/painel.php");
    } elseif ($_SESSION["user_role"] == "JUDGE") {
        header("Location: judge/painel.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$erro = "";

// Isto só corre quando o formulário é submetido (método POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $utilizador = trim($_POST["user_name"] ?? "");
    $palavra_passe = $_POST["password"] ?? "";

    if ($utilizador == "" || $palavra_passe == "") {
        $erro = "Preenche o utilizador/email e a palavra-passe.";
    } else {

        // Ir buscar o utilizador à base de dados pelo nome de utilizador ou email
        $sql = "SELECT id, name, password, role, funcao, status FROM users
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
            // Regenerar o ID de sessão antes de autenticar.
            session_regenerate_id(true);

            $_SESSION["user_id"]     = $user["id"];
            $_SESSION["user_name"]   = $user["name"];
            $_SESSION["user_role"]   = $user["role"];
            $_SESSION["user_funcao"] = $user["funcao"];

            setFlash("success", "Sessão iniciada com sucesso. Bem-vindo, " . $user["name"] . "!");

            if ($user["role"] == "ADMIN" || $user["role"] == "USER") {
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
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . "/../includes/head_assets.php"; ?>
</head>
<body class="login-page">

<div class="login-layout">
    <!-- Área de apresentação da AGSV -->
    <section class="login-brand" aria-label="Associação de Ginástica de São Vicente">
        <div class="login-brand-inner">
            <img src="<?php echo BASE_URL; ?>CSS/images/agsv_logo.png"
                 alt="Associação de Ginástica de São Vicente"
                 class="brand-logo" width="640" height="521">
            <h2>Competições, atletas e classificações num só lugar.</h2>
            <p>Plataforma de gestão da Associação de Ginástica de São Vicente para administradores, juízes e equipa técnica.</p>
        </div>
        <p class="login-brand-foot">&copy; <?php echo date("Y"); ?> AGSV · Sistema de Gestão de Competições</p>
    </section>

    <!-- Área do formulário -->
    <main class="login-panel">
        <button type="button" class="icon-button login-theme" data-theme-toggle aria-label="Alternar tema escuro" title="Alternar tema">
            <i class="fa-solid fa-moon theme-icon-light" aria-hidden="true"></i>
            <i class="fa-solid fa-sun theme-icon-dark" aria-hidden="true"></i>
        </button>

        <div class="login-card">
            <img src="<?php echo BASE_URL; ?>CSS/images/agsv_logo.png" alt="AGSV" class="login-mobile-logo" width="640" height="521">

            <div class="login-heading">
                <span class="login-kicker">Acesso à plataforma</span>
                <h1>Entrar</h1>
                <p>Introduz os teus dados para entrar.</p>
            </div>

            <?php if ($erro != ""): ?>
                <div class="login-alert" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
            <?php endif; ?>

            <?php mostrarFlash(); ?>

            <form method="post" action="" class="login-form">
                <div class="login-field">
                    <label for="user_name">Utilizador ou email</label>
                    <div class="login-input-wrap">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        <input
                            type="text"
                            id="user_name"
                            name="user_name"
                            placeholder="teu@email.com"
                            autocomplete="username"
                            value="<?php echo htmlspecialchars($_POST["user_name"] ?? ""); ?>"
                            <?php echo $erro != "" ? 'aria-invalid="true"' : ""; ?>
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="login-field">
                    <label for="password">Palavra-passe</label>
                    <div class="login-input-wrap">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="A tua palavra-passe"
                            autocomplete="current-password"
                            <?php echo $erro != "" ? 'aria-invalid="true"' : ""; ?>
                            required
                        >
                        <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Mostrar palavra-passe" title="Mostrar palavra-passe">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary login-submit">
                    <span>Entrar</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <p class="login-help">Problemas a entrar? Contacta o administrador do sistema.</p>
            <div class="login-links">
                <a class="login-back" href="index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao site público</a>
            </div>
        </div>
    </main>
</div>

</body>
</html>
