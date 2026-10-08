<?php
/**
 * Configuração inicial do administrador.
 *
 * Esta página só permite criar a conta quando ainda não existe nenhum ADMIN
 * ativo/inativo na base de dados. Depois de existir um ADMIN, o acesso fica
 * bloqueado e a gestão passa a ser feita pela área administrativa.
 */
require_once __DIR__ . '/config/db.php';

$erro = '';
$sucesso = '';

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'ADMIN'");
$row = $result ? mysqli_fetch_assoc($result) : null;
$adminExiste = $row && (int)$row['total'] > 0;

if (!$adminExiste && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['name'] ?? '');
    $username = trim($_POST['user_name'] ?? '');
    $email = trim($_POST['user_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($nome === '' || $username === '' || $email === '' || $password === '') {
        $erro = 'Preenche todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Introduz um email válido.';
    } elseif (strlen($password) < 8) {
        $erro = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif ($password !== $passwordConfirm) {
        $erro = 'As palavras-passe não coincidem.';
    } else {
        $check = mysqli_prepare($conn, 'SELECT id FROM users WHERE user_name = ? OR user_email = ? LIMIT 1');
        mysqli_stmt_bind_param($check, 'ss', $username, $email);
        mysqli_stmt_execute($check);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check));

        if ($existing) {
            $erro = 'Já existe uma conta com esse utilizador ou email.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $funcao = 'Administrador';
            $status = 'A';

            $stmt = mysqli_prepare($conn, "INSERT INTO users (name, user_name, user_email, password, role, funcao, status) VALUES (?, ?, ?, ?, 'ADMIN', ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssss', $nome, $username, $email, $hash, $funcao, $status);

            if (mysqli_stmt_execute($stmt)) {
                $sucesso = 'Administrador criado com sucesso. Por segurança, esta página fica bloqueada a partir de agora.';
                $adminExiste = true;
            } else {
                $erro = 'Não foi possível criar o administrador: ' . mysqli_error($conn);
            }
        }
    }
}

$pageTitle = 'Configuração inicial - AGSV';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . "/includes/head_assets.php"; ?>
</head>
<body class="login-page">
<div class="login-layout">
    <section class="login-brand" aria-label="Associação de Ginástica de São Vicente">
        <div class="login-brand-inner">
            <img src="<?php echo htmlspecialchars(BASE_URL); ?>CSS/images/agsv_logo.png"
                 alt="Associação de Ginástica de São Vicente"
                 class="brand-logo" width="640" height="521">
            <h2>Vamos preparar a plataforma.</h2>
            <p>A primeira conta criada tem acesso total e fica responsável por gerir os restantes utilizadores e permissões.</p>
        </div>
        <p class="login-brand-foot">&copy; <?php echo date("Y"); ?> AGSV · Sistema de Gestão de Competições</p>
    </section>

    <main class="login-panel">
        <button type="button" class="icon-button login-theme" data-theme-toggle aria-label="Alternar tema escuro" title="Alternar tema">
            <i class="fa-solid fa-moon theme-icon-light" aria-hidden="true"></i>
            <i class="fa-solid fa-sun theme-icon-dark" aria-hidden="true"></i>
        </button>

        <div class="login-card">
            <img src="<?php echo htmlspecialchars(BASE_URL); ?>CSS/images/agsv_logo.png" alt="AGSV" class="login-mobile-logo" width="640" height="521">

            <div class="login-heading">
                <span class="login-kicker">Configuração inicial</span>
                <h1>Criar administrador</h1>
                <p>Cria a primeira conta com acesso total ao sistema AGSV.</p>
            </div>

            <?php if ($erro !== ''): ?>
                <div class="login-alert" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($sucesso !== ''): ?>
                <div class="login-alert login-alert-success" role="status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($sucesso); ?></span>
                </div>
                <a class="btn btn-primary login-submit" href="<?php echo htmlspecialchars(BASE_URL); ?>PHP_Pages/login.php">
                    Ir para o login
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            <?php elseif ($adminExiste): ?>
                <div class="login-alert" role="alert">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <span>Já existe um administrador configurado. Usa a área de login para entrar no sistema.</span>
                </div>
                <a class="btn btn-primary login-submit" href="<?php echo htmlspecialchars(BASE_URL); ?>PHP_Pages/login.php">
                    Ir para o login
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <form method="post" class="login-form" autocomplete="off">
                    <div class="login-field">
                        <label for="name">Nome completo</label>
                        <div class="login-input-wrap">
                            <i class="fa-regular fa-user" aria-hidden="true"></i>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" autocomplete="name" required>
                        </div>
                    </div>
                    <div class="login-field">
                        <label for="user_name">Nome de utilizador</label>
                        <div class="login-input-wrap">
                            <i class="fa-solid fa-at" aria-hidden="true"></i>
                            <input type="text" id="user_name" name="user_name" value="<?php echo htmlspecialchars($_POST['user_name'] ?? ''); ?>" autocomplete="username" required>
                        </div>
                    </div>
                    <div class="login-field">
                        <label for="user_email">Email</label>
                        <div class="login-input-wrap">
                            <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                            <input type="email" id="user_email" name="user_email" value="<?php echo htmlspecialchars($_POST['user_email'] ?? ''); ?>" autocomplete="email" required>
                        </div>
                    </div>
                    <div class="login-field">
                        <label for="password">Palavra-passe</label>
                        <div class="login-input-wrap">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" aria-describedby="password-ajuda" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Mostrar palavra-passe" title="Mostrar palavra-passe">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="field-help" id="password-ajuda">Pelo menos 8 caracteres.</p>
                    </div>
                    <div class="login-field">
                        <label for="password_confirm">Confirmar palavra-passe</label>
                        <div class="login-input-wrap">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="8" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary login-submit">
                        <span>Criar administrador</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
