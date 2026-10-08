<?php
// PHP_Pages/admin/juizes.php
// Página só de consulta: lista os juízes existentes (com ativar/desativar e
// remover). Criar ou editar um juiz faz-se sempre em "Gerir Utilizadores"
// (um juiz é, antes de mais, um utilizador com role = 'JUDGE') — esta página
// tem apenas um atalho que já abre esse formulário com o tipo de conta
// "Juiz (técnico)" pré-selecionado.
// O papel (A/E/DB/DA) também não se escolhe aqui: só faz sentido no contexto de
// uma competição em concreto, por isso é escolhido depois, ao atribuir o
// juiz a uma competição em "Atribuir Juízes".

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Área administrativa: ADMIN tem sempre acesso; um USER só entra se
// lhe tiver sido atribuído o menu correspondente (ver Parametrizações).
requireAdminArea($conn);
requireTransaction($conn, "JUIZES_GERIR");

// Todos os pedidos POST desta página alteram dados: exigimos sempre um
// token CSRF válido antes de processar qualquer ação.
exigirCsrf();

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

// --- Lista de todos os juízes ---
$resultado_lista = mysqli_query($conn, "SELECT u.* FROM users u
                                         JOIN judges j ON j.user_id = u.id
                                         WHERE u.role = 'JUDGE'
                                         ORDER BY u.name");

$pageTitle = "Gerir Juízes - " . APP_NAME;
$pagina_atual = "admin_juizes";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Gerir Juízes</h1>
    <p>Um juiz é um utilizador do tipo "Juiz (técnico)". O papel (A/E/DB/DA) é escolhido ao atribuí-lo a uma competição.</p>
</section>

<?php mostrarFlash(); ?>

<section class="competitions-list">
    <div class="list-header-bar">
        <h2>Juízes existentes<span class="count-pill"><?php echo mysqli_num_rows($resultado_lista); ?></span></h2>
        <a class="btn btn-primary" href="utilizadores.php?role=JUDGE#form-utilizador"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Adicionar juiz</a>
    </div>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Ainda não existem juízes registados.</p>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Utilizador</th>
                        <th scope="col">Email</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($juiz = mysqli_fetch_assoc($resultado_lista)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($juiz["name"]); ?></td>
                            <td data-label="Utilizador"><?php echo htmlspecialchars($juiz["user_name"]); ?></td>
                            <td class="cell-muted" data-label="Email"><?php echo htmlspecialchars($juiz["user_email"]); ?></td>
                            <td data-label="Estado"><span class="badge <?php echo $juiz["status"] == "A" ? "badge-active" : "badge-inactive"; ?>"><?php echo $juiz["status"] == "A" ? "Ativo" : "Inativo"; ?></span></td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <a class="btn btn-small" href="utilizadores.php?editar=<?php echo (int) $juiz['id']; ?>#form-utilizador"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</a>

                                    <form method="post" action="juizes.php" class="inline-form">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="alternar_estado">
                                        <input type="hidden" name="user_id" value="<?php echo $juiz['id']; ?>">
                                        <button type="submit" class="btn btn-small">
                                            <?php echo $juiz["status"] == "A" ? "Desativar" : "Ativar"; ?>
                                        </button>
                                    </form>

                                    <form method="post" action="juizes.php" class="inline-form" data-confirm="Remover este juiz? Esta ação não pode ser desfeita.">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="eliminar">
                                        <input type="hidden" name="user_id" value="<?php echo $juiz['id']; ?>">
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