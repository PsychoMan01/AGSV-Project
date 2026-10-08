<?php
// PHP_Pages/admin/utilizadores.php
// Gestão centralizada de utilizadores: criar, editar e ativar/desativar contas.
//
// Desde a v5, o "Tipo de conta" (ADMIN/JUDGE/USER) é só um tipo TÉCNICO que
// define o fluxo de login (painel admin, painel do juiz, ou nenhum acesso
// especial). A função/cargo da pessoa (ex: "Secretário(a)", "Coordenador")
// é um texto livre, sem qualquer efeito nos acessos. Quem decide a que
// páginas administrativas um utilizador do tipo USER acede são os menus
// escolhidos no popup "Associar Menu" (por conta, na lista abaixo) — nunca
// o texto da função nem o tipo de conta.
//
// O ADMIN tem sempre acesso total (não precisa de menus atribuídos).
// O JUDGE nunca usa menus: tem o seu próprio painel de pontuação. O papel de
// pontuação (A/E/DB/DA) já não se define aqui, na criação da conta — só faz
// sentido no contexto de uma competição, por isso é escolhido depois, ao
// atribuir o juiz a uma competição em "Atribuir Juízes".
// Para contas JUDGE é mantido automaticamente o registo correspondente em judges.
//
// A mudança de password de uma conta já criada passa a fazer-se pelo popup
// "Reset Password" na lista, não pelo formulário principal (esse só pede
// password ao CRIAR uma conta nova).

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

requireAdmin($conn);
requireTransaction($conn, "UTILIZADORES_GERIR");
exigirCsrf();

$erros = array();
$menus_disponiveis = menusAtribuiveis($conn);
$ids_menus_disponiveis = array_map(function ($m) { return (int) $m["id"]; }, $menus_disponiveis);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = isset($_POST["acao"]) ? $_POST["acao"] : "";

    if ($acao === "alternar_estado") {
        $user_id = (int)($_POST["user_id"] ?? 0);
        if ($user_id === (int)$_SESSION["user_id"]) {
            setFlash("error", "Não podes desativar a tua própria conta.");
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET status = IF(status='A','I','A') WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            setFlash("success", "Estado do utilizador atualizado.");
        }
        header("Location: utilizadores.php");
        exit;
    }

    if ($acao === "reset_password") {
        $user_id = (int)($_POST["user_id"] ?? 0);
        $nova_password = $_POST["nova_password"] ?? "";

        if (mb_strlen($nova_password) < 6) {
            setFlash("error", "A nova password tem de ter pelo menos 6 caracteres.");
        } else {
            $hash = password_hash($nova_password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $hash, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                setFlash("success", "Password alterada com sucesso.");
            } else {
                setFlash("error", "Não foi possível alterar a password.");
            }
        }
        header("Location: utilizadores.php");
        exit;
    }

    if ($acao === "guardar_menus") {
        $user_id = (int)($_POST["user_id"] ?? 0);

        // Confirmar que o alvo é mesmo uma conta USER — Associar Menu não se
        // aplica a ADMIN (acesso total) nem a JUDGE (painel de pontuação).
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
        header("Location: utilizadores.php");
        exit;
    }

    if ($acao === "guardar") {
        $user_id = (int)($_POST["user_id"] ?? 0);
        $nome = trim($_POST["name"] ?? "");
        $username = trim($_POST["user_name"] ?? "");
        $email = trim($_POST["user_email"] ?? "");
        $password = $_POST["password"] ?? "";
        $role = $_POST["role"] ?? "USER";
        $funcao = $role === "JUDGE" ? "" : trim($_POST["funcao"] ?? "");

        if ($nome === "") $erros[] = "O nome é obrigatório.";
        if ($username === "") $erros[] = "O nome de utilizador é obrigatório.";
        if ($email === "" || !validarEmail($email)) $erros[] = "Indica um email válido.";
        if (!in_array($role, array("ADMIN", "JUDGE", "USER"), true)) $erros[] = "O tipo de conta selecionado é inválido.";
        if ($role !== "JUDGE" && $funcao === "") $erros[] = "A função/cargo é obrigatória (ex: Secretário(a), Coordenador).";
        if ($user_id === 0 && $password === "") $erros[] = "A password é obrigatória para criar um utilizador.";

        if (!$erros) {
            // Unicidade de username/email, permitindo manter o próprio registo.
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE (user_name = ? OR user_email = ?) AND id <> ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "ssi", $username, $email, $user_id);
            mysqli_stmt_execute($stmt);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                $erros[] = "O nome de utilizador ou email já está em uso.";
            }
        }

        if (!$erros) {
            mysqli_begin_transaction($conn);
            $ok = true;

            if ($user_id > 0) {
                // A password já não se altera aqui — usa o popup "Reset Password"
                // na lista de utilizadores para isso.
                $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, user_name=?, user_email=?, role=?, funcao=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "sssssi", $nome, $username, $email, $role, $funcao, $user_id);
                $ok = mysqli_stmt_execute($stmt);

                if ($ok && $role === "JUDGE") {
                    // Garantir que existe um registo em judges para este utilizador
                    // (sem papel — esse escolhe-se depois, por competição, em "Atribuir Juízes").
                    $stmt = mysqli_prepare($conn, "SELECT id FROM judges WHERE user_id=? LIMIT 1");
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    mysqli_stmt_execute($stmt);
                    $juiz = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

                    if (!$juiz) {
                        $stmt = mysqli_prepare($conn, "INSERT INTO judges (user_id) VALUES (?)");
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        $ok = mysqli_stmt_execute($stmt);
                    }
                } elseif ($ok && $role !== "JUDGE") {
                    // Se deixou de ser juiz, remove apenas o vínculo de perfil.
                    // A eliminação pode ser recusada se existirem atribuições.
                    $stmt = mysqli_prepare($conn, "DELETE FROM judges WHERE user_id=?");
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    $ok = mysqli_stmt_execute($stmt);
                }

                // Se deixou de ser USER, os menus atribuídos deixam de fazer
                // sentido (ADMIN tem acesso total, JUDGE usa o painel de
                // pontuação) — limpa para não ficarem atribuições "fantasma".
                // Se continua/passou a ser USER, os menus não são tocados
                // aqui: gerem-se à parte, no popup "Associar Menu".
                if ($ok && $role !== "USER") {
                    $ok = guardarMenusDoUtilizador($conn, $user_id, array());
                }
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (name,user_name,user_email,password,role,funcao,status) VALUES (?,?,?,?,?,?,'A')");
                mysqli_stmt_bind_param($stmt, "ssssss", $nome, $username, $email, $hash, $role, $funcao);
                $ok = mysqli_stmt_execute($stmt);
                $novo_id = $ok ? mysqli_insert_id($conn) : 0;

                if ($ok && $role === "JUDGE") {
                    $stmt = mysqli_prepare($conn, "INSERT INTO judges (user_id) VALUES (?)");
                    mysqli_stmt_bind_param($stmt, "i", $novo_id);
                    $ok = mysqli_stmt_execute($stmt);
                }
                // Contas USER novas começam sem menus atribuídos — usa
                // "Associar Menu" na lista para lhe dar acesso às páginas.
            }

            if ($ok) {
                mysqli_commit($conn);
                setFlash("success", $user_id > 0 ? "Utilizador atualizado com sucesso." : "Utilizador criado com sucesso.");
                header("Location: utilizadores.php");
                exit;
            }

            mysqli_rollback($conn);
            $erros[] = "Não foi possível guardar o utilizador. Verifica os dados e as relações existentes.";
        }
    }
}

$editar = null;
if (isset($_GET["editar"])) {
    $id = (int)$_GET["editar"];
    $stmt = mysqli_prepare($conn, "SELECT u.* FROM users u WHERE u.id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// Permite abrir já o formulário de "Novo utilizador" com o tipo de conta
// pré-selecionado (ex: o botão "+ Adicionar Juiz" em Gerir Juízes usa
// utilizadores.php?role=JUDGE#form-utilizador).
$tipo_pre_selecionado = null;
if (!$editar && isset($_GET["role"]) && in_array($_GET["role"], array("ADMIN", "JUDGE", "USER"), true)) {
    $tipo_pre_selecionado = $_GET["role"];
}
// O formulário também fica visível quando a gravação falhou, para mostrar os erros no contexto
$mostrar_formulario = $editar || $tipo_pre_selecionado || count($erros) > 0;

// Valor de um campo do formulário: dados da conta em edição ou, depois de um
// erro de validação ao criar, o que tinha sido escrito (para não se perder).
function valorFormularioUtilizador($editar, $campo) {
    if ($editar) {
        return $editar[$campo] ?? "";
    }
    if (($_POST["acao"] ?? "") === "guardar") {
        return $_POST[$campo] ?? "";
    }
    return "";
}

// Lista de utilizadores, guardada num array (em vez de um result do mysqli)
// porque é percorrida duas vezes mais abaixo: uma para a tabela e outra para
// gerar os popups "Associar Menu" / "Reset Password" de cada linha.
$resultado_lista = mysqli_query($conn, "SELECT u.id,u.name,u.user_name,u.user_email,u.role,u.funcao,u.status,u.created_at
                              FROM users u
                              ORDER BY u.name");
$lista_utilizadores = array();
while ($linha = mysqli_fetch_assoc($resultado_lista)) {
    $lista_utilizadores[] = $linha;
}

// Menus já atribuídos a cada utilizador USER, para pré-marcar as checkboxes
// no popup "Associar Menu" sem precisar de uma query por utilizador.
$menus_por_utilizador = array();
$res_menus_todos = mysqli_query($conn, "SELECT user_id, transaction_id FROM user_transactions");
while ($linha = mysqli_fetch_assoc($res_menus_todos)) {
    $menus_por_utilizador[(int) $linha["user_id"]][] = (int) $linha["transaction_id"];
}

$rotulos_tipo = array("ADMIN" => "Administrador", "JUDGE" => "Juiz (técnico)", "USER" => "Utilizador");

$pageTitle = "Gerir Utilizadores - " . APP_NAME;
$pagina_atual = "admin_utilizadores";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Gestão de Utilizadores</h1>
    <p>Cria e edita contas, e usa os ícones de cada linha para autorizar acessos ou repor a password.</p>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<section class="competitions-list">
    <div class="list-header-bar">
        <h2>Utilizadores registados<span class="count-pill"><?php echo count($lista_utilizadores); ?></span></h2>
        <button type="button" class="btn btn-primary" data-reveal="form-utilizador">
            <i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Novo utilizador
        </button>
    </div>

    <div class="table-scroll">
        <table class="data-table data-table--stack">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">Utilizador</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="sr-only">Operações</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lista_utilizadores as $u): ?>
                <?php $uid = (int) $u["id"]; $ativo = $u["status"] === "A"; ?>
                <tr>
                    <td><?php echo htmlspecialchars($u["name"]); ?></td>
                    <td data-label="Utilizador"><?php echo htmlspecialchars($u["user_name"]); ?></td>
                    <td class="cell-muted" data-label="E-mail"><?php echo htmlspecialchars($u["user_email"]); ?></td>
                    <td data-label="Tipo"><span class="badge badge-tipo badge-tipo-<?php echo strtolower($u["role"]); ?>"><?php echo htmlspecialchars($rotulos_tipo[$u["role"]] ?? $u["role"]); ?></span></td>
                    <td data-label="Estado"><span class="badge <?php echo $ativo ? "badge-active" : "badge-inactive"; ?>"><?php echo $ativo ? "Ativo" : "Inativo"; ?></span></td>
                    <td class="actions-cell">
                        <div class="actions-wrap">
                            <a class="icon-btn" title="Editar" aria-label="Editar <?php echo htmlspecialchars($u["name"]); ?>" href="utilizadores.php?editar=<?php echo $uid; ?>#form-utilizador">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            </a>

                            <?php if ($u["role"] === "USER"): ?>
                                <button type="button" class="icon-btn" title="Associar menu" aria-label="Associar menu a <?php echo htmlspecialchars($u["name"]); ?>" data-modal-open="modal-menu-<?php echo $uid; ?>">
                                    <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>

                            <button type="button" class="icon-btn" title="Repor password" aria-label="Repor a password de <?php echo htmlspecialchars($u["name"]); ?>" data-modal-open="modal-reset-<?php echo $uid; ?>">
                                <i class="fa-solid fa-key" aria-hidden="true"></i>
                            </button>

                            <?php if ($uid !== (int) $_SESSION["user_id"]): ?>
                                <form method="post" action="utilizadores.php" class="inline-form"
                                      <?php if ($ativo): ?>data-confirm="Desativar a conta de <?php echo htmlspecialchars($u["name"]); ?>? A pessoa deixa de conseguir entrar até a conta ser reativada." data-confirm-label="Desativar"<?php endif; ?>>
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="acao" value="alternar_estado">
                                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                    <button type="submit" class="icon-btn <?php echo $ativo ? "icon-btn-danger is-on" : ""; ?>" title="<?php echo $ativo ? "Desativar" : "Ativar"; ?>" aria-label="<?php echo ($ativo ? "Desativar " : "Ativar ") . htmlspecialchars($u["name"]); ?>">
                                        <i class="fa-solid <?php echo $ativo ? "fa-toggle-on" : "fa-toggle-off"; ?>" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php $role_formulario = $editar ? $editar["role"] : (($_POST["acao"] ?? "") === "guardar" ? ($_POST["role"] ?? null) : $tipo_pre_selecionado); ?>
<section class="form-card" id="form-utilizador"<?php echo $mostrar_formulario ? "" : " hidden"; ?>>
    <div class="section-head">
        <div>
            <h2><?php echo $editar ? "Editar utilizador" : "Novo utilizador"; ?></h2>
            <?php if ($editar): ?><p><?php echo htmlspecialchars($editar["name"]); ?></p><?php endif; ?>
        </div>
    </div>
    <form method="post" action="utilizadores.php<?php echo $editar ? "?editar=" . (int) $editar["id"] . "#form-utilizador" : "#form-utilizador"; ?>">
        <?php echo csrfField(); ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="user_id" value="<?php echo $editar ? (int) $editar["id"] : 0; ?>">

        <div class="form-row">
            <div>
                <label for="name">Nome</label>
                <input type="text" id="name" name="name" required autocomplete="off" value="<?php echo htmlspecialchars(valorFormularioUtilizador($editar, "name")); ?>">
            </div>
            <div>
                <label for="user_email">E-mail</label>
                <input type="email" id="user_email" name="user_email" required autocomplete="off" value="<?php echo htmlspecialchars(valorFormularioUtilizador($editar, "user_email")); ?>">
            </div>
        </div>

        <div class="form-row">
            <div>
                <label for="user_name">Nome de utilizador</label>
                <input type="text" id="user_name" name="user_name" required autocomplete="off" value="<?php echo htmlspecialchars(valorFormularioUtilizador($editar, "user_name")); ?>">
            </div>
            <div>
                <label for="role">Tipo de conta</label>
                <select id="role" name="role" required data-funcao-target="campo-funcao">
                    <?php foreach ($rotulos_tipo as $codigo => $rotulo): ?>
                        <option value="<?php echo $codigo; ?>" <?php echo $role_formulario === $codigo ? "selected" : ""; ?>><?php echo $rotulo; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div id="campo-funcao">
                <label for="funcao">Função / cargo</label>
                <input type="text" id="funcao" name="funcao" required placeholder="Ex: Secretário(a), Coordenador, Diretor Técnico..." value="<?php echo htmlspecialchars(valorFormularioUtilizador($editar, "funcao")); ?>">
            </div>
            <?php if (!$editar): ?>
            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
            </div>
            <?php endif; ?>
        </div>

        <p class="hint">
            O tipo de conta define só o fluxo de login (Administrador: acesso total; Juiz: painel de pontuação — o papel de
            pontuação escolhe-se depois, por competição, em "Atribuir Juízes"; Utilizador: acesso pelos menus escolhidos em
            "Associar menu", na lista).
            <?php if ($editar): ?>Para alterar a password desta conta, usa o ícone "Repor password" na lista.<?php endif; ?>
        </p>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Gravar</button>
            <a class="btn" href="utilizadores.php">Cancelar</a>
        </div>
    </form>
</section>

<?php foreach ($lista_utilizadores as $u): ?>
    <?php $uid = (int) $u["id"]; ?>
    <?php if ($u["role"] === "USER"): ?>
        <div class="modal-overlay" id="modal-menu-<?php echo $uid; ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="modal-menu-titulo-<?php echo $uid; ?>">
            <div class="modal-box">
                <div class="modal-header">
                    <h2 id="modal-menu-titulo-<?php echo $uid; ?>">Associar menu</h2>
                    <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
                </div>
                <p class="modal-subtitle"><?php echo htmlspecialchars($u["name"]); ?> · escolhe as áreas a que esta conta tem acesso.</p>
                <form method="post" action="utilizadores.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="acao" value="guardar_menus">
                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">

                    <div class="menu-tree">
                        <?php $menus_deste_utilizador = $menus_por_utilizador[$uid] ?? array(); ?>
                        <?php foreach ($menus_disponiveis as $menu): ?>
                            <label class="menu-tree-item">
                                <input type="checkbox" name="menus[]" value="<?php echo (int) $menu["id"]; ?>"
                                    <?php echo in_array((int) $menu["id"], $menus_deste_utilizador, true) ? "checked" : ""; ?>>
                                <?php echo htmlspecialchars($menu["label"]); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn" data-modal-close>Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal-overlay" id="modal-reset-<?php echo $uid; ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="modal-reset-titulo-<?php echo $uid; ?>">
        <div class="modal-box modal-box--small">
            <div class="modal-header">
                <h2 id="modal-reset-titulo-<?php echo $uid; ?>">Repor password</h2>
                <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
            </div>
            <p class="modal-subtitle"><?php echo htmlspecialchars($u["name"]); ?></p>
            <form method="post" action="utilizadores.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="acao" value="reset_password">
                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">

                <label for="nova_password_<?php echo $uid; ?>">Nova password</label>
                <input type="password" id="nova_password_<?php echo $uid; ?>" name="nova_password" minlength="6" required autocomplete="new-password">
                <p class="field-help">Pelo menos 6 caracteres.</p>

                <div class="form-actions">
                    <button type="button" class="btn" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn btn-primary">Alterar</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . "/../../includes/footer.php"; ?>
