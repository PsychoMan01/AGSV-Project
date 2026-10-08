<?php
// PHP_Pages/admin/parametrizacoes.php
// Área onde o administrador escolhe um utilizador e ativa/desativa,
// individualmente, os menus a que esse utilizador tem acesso.
//
// Só faz sentido para contas do tipo USER: o ADMIN tem sempre acesso total
// e o JUDGE usa o painel de pontuação (não navega pelos menus). Por isso o
// seletor de utilizadores só lista contas USER.
//
// A alteração aqui feita reflete-se de imediato: o menu apresentado a esse
// utilizador (includes/header.php) é montado sempre a partir da tabela
// user_transactions, e cada página administrativa confirma no servidor,
// a cada pedido, se o utilizador tem a permissão (ver requireTransaction()
// em includes/functions.php) — nunca é apenas uma ocultação visual.

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

requireAdmin($conn);
requireTransaction($conn, "PARAMETRIZACOES_GERIR");
exigirCsrf();

$menus_disponiveis = menusAtribuiveis($conn);
$ids_menus_disponiveis = array_map(function ($m) { return (int) $m["id"]; }, $menus_disponiveis);

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "guardar_menus") {
    $user_id = (int) ($_POST["user_id"] ?? 0);

    // Confirmar que o alvo é mesmo uma conta USER — parametrizações não se
    // aplicam a ADMIN (acesso total) nem a JUDGE (painel de pontuação).
    $stmt = mysqli_prepare($conn, "SELECT id, role FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $alvo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$alvo || $alvo["role"] !== "USER") {
        setFlash("error", "Só é possível alterar os menus de contas do tipo Utilizador.");
    } else {
        $menus_enviados = isset($_POST["menus"]) && is_array($_POST["menus"])
            ? array_map("intval", $_POST["menus"])
            : array();
        $menus_selecionados = array_values(array_intersect($menus_enviados, $ids_menus_disponiveis));

        if (guardarMenusDoUtilizador($conn, $user_id, $menus_selecionados)) {
            setFlash("success", "Menus atualizados com sucesso.");
        } else {
            setFlash("error", "Não foi possível guardar os menus deste utilizador.");
        }
    }

    header("Location: parametrizacoes.php?utilizador=" . $user_id);
    exit;
}

$utilizadores = mysqli_query($conn, "SELECT id, name, user_name, funcao, status
                                     FROM users WHERE role = 'USER' ORDER BY name");
$lista_utilizadores = array();
while ($linha = mysqli_fetch_assoc($utilizadores)) {
    $lista_utilizadores[] = $linha;
}

$utilizador_selecionado = null;
$menus_do_utilizador = array();
if (isset($_GET["utilizador"])) {
    $id = (int) $_GET["utilizador"];
    foreach ($lista_utilizadores as $u) {
        if ((int) $u["id"] === $id) {
            $utilizador_selecionado = $u;
            break;
        }
    }
    if ($utilizador_selecionado) {
        $menus_do_utilizador = transacoesDoUtilizador($conn, $id);
    }
}

$pageTitle = "Parametrizações - " . APP_NAME;
$pagina_atual = "admin_parametrizacoes";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Parametrizações</h1>
    <p>Escolhe um utilizador e ativa ou desativa os menus a que ele tem acesso.</p>
</section>

<?php mostrarFlash(); ?>

<div class="params-layout">
    <aside class="params-sidebar">
        <h2 id="params-utilizadores">Utilizadores</h2>
        <?php if (empty($lista_utilizadores)): ?>
            <p class="hint">Ainda não existe nenhum utilizador do tipo "Utilizador". Cria um em <a href="utilizadores.php">Adicionar utilizadores</a>.</p>
        <?php else: ?>
            <ul class="params-user-list" aria-labelledby="params-utilizadores">
                <?php foreach ($lista_utilizadores as $u): ?>
                    <li>
                        <a href="parametrizacoes.php?utilizador=<?php echo (int) $u["id"]; ?>"
                           <?php $selecionado = $utilizador_selecionado && (int) $utilizador_selecionado["id"] === (int) $u["id"]; ?>
                           class="params-user-link<?php echo $selecionado ? " is-active" : ""; ?>"<?php echo $selecionado ? ' aria-current="true"' : ""; ?>>
                            <span class="params-user-name"><?php echo htmlspecialchars($u["name"]); ?></span>
                            <span class="params-user-meta">
                                <?php echo htmlspecialchars($u["funcao"] ?: $u["user_name"]); ?>
                                <?php if ($u["status"] !== "A"): ?> · Inativo<?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>

    <div class="params-content">
        <?php if (!$utilizador_selecionado): ?>
            <section class="form-card">
                <div class="empty-state">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    <h3>Nenhum utilizador selecionado</h3>
                    <p>Seleciona um utilizador na lista para ver e alterar os menus a que tem acesso.</p>
                </div>
            </section>
        <?php else: ?>
            <section class="form-card">
                <div class="section-head">
                    <div>
                        <h2>Menus de <?php echo htmlspecialchars($utilizador_selecionado["name"]); ?></h2>
                        <p><?php echo htmlspecialchars($utilizador_selecionado["funcao"] ?: "Sem função definida"); ?> · <?php echo htmlspecialchars($utilizador_selecionado["user_name"]); ?></p>
                    </div>
                    <?php if ($utilizador_selecionado["status"] !== "A"): ?><span class="badge badge-inactive">Conta inativa</span><?php endif; ?>
                </div>

                <form method="post" action="parametrizacoes.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="acao" value="guardar_menus">
                    <input type="hidden" name="user_id" value="<?php echo (int) $utilizador_selecionado["id"]; ?>">

                    <div class="menu-tree">
                        <?php foreach ($menus_disponiveis as $menu): ?>
                            <label class="menu-tree-item">
                                <input type="checkbox" name="menus[]" value="<?php echo (int) $menu["id"]; ?>"
                                    <?php echo in_array((int) $menu["id"], $menus_do_utilizador, true) ? "checked" : ""; ?>>
                                <?php echo htmlspecialchars($menu["label"]); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar alterações</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
