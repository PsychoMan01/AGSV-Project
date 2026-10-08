<?php
// PHP_Pages/profile.php
// Página de perfil: qualquer utilizador com sessão iniciada (ADMIN, JUDGE
// ou USER) pode ver os seus dados e alterar a própria password.

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

requireLogin($conn);

$user_id = $_SESSION["user_id"];

// Ir sempre buscar os dados atuais à base de dados (não confiar só na sessão)
$sql = "SELECT name, user_name, user_email, password, role, funcao, created_at
        FROM users WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$utilizador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$utilizador) {
    // Não deve acontecer (requireLogin() já confirmou que a conta existe e
    // está ativa), mas por segurança nunca continuamos sem estes dados.
    header("Location: " . BASE_URL . "PHP_Pages/login.php");
    exit;
}

$rotulos_tipo = array("ADMIN" => "Administrador", "JUDGE" => "Juiz (técnico)", "USER" => "Utilizador");

$erros = array();

// --- Alterar password ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    exigirCsrf();

    $password_atual = $_POST["password_atual"] ?? "";
    $password_nova  = $_POST["password_nova"] ?? "";
    $password_confirmar = $_POST["password_confirmar"] ?? "";

    if ($password_atual === "" || $password_nova === "" || $password_confirmar === "") {
        $erros[] = "Preenche a password atual e a nova password (duas vezes).";
    } elseif (!password_verify($password_atual, $utilizador["password"])) {
        $erros[] = "A password atual está incorreta.";
    } elseif (strlen($password_nova) < 6) {
        $erros[] = "A nova password tem de ter pelo menos 6 caracteres.";
    } elseif ($password_nova !== $password_confirmar) {
        $erros[] = "A nova password e a confirmação não coincidem.";
    }

    if (count($erros) == 0) {
        $hash = password_hash($password_nova, PASSWORD_DEFAULT);
        $stmt2 = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, "si", $hash, $user_id);

        if (mysqli_stmt_execute($stmt2)) {
            setFlash("success", "Password alterada com sucesso.");
            header("Location: " . BASE_URL . "PHP_Pages/profile.php");
            exit;
        } else {
            $erros[] = "Não foi possível alterar a password. Tenta novamente.";
        }
    }
}

$pageTitle = "O meu perfil - " . APP_NAME;
$pagina_atual = "profile";
require __DIR__ . "/../includes/header.php";
?>

<section class="page-intro">
    <h1>O meu perfil</h1>
    <p>Consulta os dados da tua conta e altera a tua password.</p>
</section>

<?php mostrarFlash(); ?>

<div class="profile-grid">
    <section class="form-card">
        <div class="profile-head">
            <div class="profile-avatar" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($utilizador["name"], 0, 1, "UTF-8"), "UTF-8")); ?></div>
            <div>
                <h2><?php echo htmlspecialchars($utilizador["name"]); ?></h2>
                <p><?php echo htmlspecialchars($rotulos_tipo[$utilizador["role"]] ?? $utilizador["role"]); ?></p>
            </div>
        </div>

        <dl class="detail-list">
            <div><dt>Utilizador</dt><dd><?php echo htmlspecialchars($utilizador["user_name"]); ?></dd></div>
            <div><dt>Email</dt><dd><?php echo htmlspecialchars($utilizador["user_email"]); ?></dd></div>
            <div><dt>Tipo de conta</dt><dd><?php echo htmlspecialchars($rotulos_tipo[$utilizador["role"]] ?? $utilizador["role"]); ?></dd></div>
            <?php if (!empty($utilizador["funcao"])): ?>
                <div><dt>Função</dt><dd><?php echo htmlspecialchars($utilizador["funcao"]); ?></dd></div>
            <?php endif; ?>
            <div><dt>Conta criada em</dt><dd><?php echo formatarData($utilizador["created_at"]); ?></dd></div>
        </dl>
    </section>

    <section class="form-card">
        <h2>Alterar password</h2>

        <?php mostrarErros($erros); ?>

        <form method="post" action="profile.php">
            <?php echo csrfField(); ?>

            <label for="password_atual">Password atual</label>
            <input type="password" id="password_atual" name="password_atual" autocomplete="current-password" required>

            <div class="form-row">
                <div>
                    <label for="password_nova">Nova password</label>
                    <input type="password" id="password_nova" name="password_nova" autocomplete="new-password" required minlength="6" aria-describedby="password-nova-ajuda">
                    <p class="field-help" id="password-nova-ajuda">Pelo menos 6 caracteres.</p>
                </div>
                <div>
                    <label for="password_confirmar">Confirmar nova password</label>
                    <input type="password" id="password_confirmar" name="password_confirmar" autocomplete="new-password" required minlength="6">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key" aria-hidden="true"></i> Guardar nova password</button>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . "/../includes/footer.php"; ?>
