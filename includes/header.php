<?php
if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}
if (!isset($pagina_atual)) {
    $pagina_atual = "";
}

function nav_activo($pagina, $atual) {
    return $pagina === $atual ? ' class="activo" aria-current="page"' : '';
}

// Ícone de cada entrada do menu, pela chave (a etiqueta pode mudar sem partir o ícone)
function agsv_menu_icone($chave) {
    $icones = array(
        "inicio"                => "fa-house",
        "competicoes_publico"   => "fa-trophy",
        "painel"                => "fa-gavel",
        "dashboard"             => "fa-chart-pie",
        "admin_competicoes"     => "fa-calendar-check",
        "atletas"               => "fa-users",
        "admin_atletas"         => "fa-person-running",
        "admin_clubes"          => "fa-building-flag",
        "catalogos"             => "fa-layer-group",
        "admin_escaloes"        => "fa-layer-group",
        "admin_aparelhos"       => "fa-dumbbell",
        "admin_juizes"          => "fa-gavel",
        "utilizadores"          => "fa-user-shield",
        "admin_utilizadores"    => "fa-user-plus",
        "admin_parametrizacoes" => "fa-sliders",
    );
    return $icones[$chave] ?? "fa-circle-dot";
}

$grupos_menu_lateral = obterGruposMenuLateral($conn);
$role_atual = $_SESSION["user_role"] ?? null;
$nome_atual = $_SESSION["user_name"] ?? null;
$rotulos_role = array("ADMIN" => "Administrador", "JUDGE" => "Juiz", "USER" => "Utilizador");

// Título curto para a barra superior (sem o sufixo " - APP_NAME") e a secção atual
$titulo_curto = preg_replace('/\s+-\s+' . preg_quote(APP_NAME, '/') . '$/u', '', $pageTitle);
$secao_atual = "AGSV";
foreach ($grupos_menu_lateral as $grupo) {
    if ($grupo["itens"] !== null && in_array($pagina_atual, array_column($grupo["itens"], "chave"), true)) {
        $secao_atual = $grupo["label"];
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . "/head_assets.php"; ?>
</head>
<body class="app-body">
<a class="skip-link" href="#conteudo">Saltar para o conteúdo</a>

<button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="sidebar">
    <span class="hamburger" aria-hidden="true"><span></span><span></span><span></span></span>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="brand-area">
            <a class="brand" href="<?php echo BASE_URL; ?>PHP_Pages/index.php" aria-label="AGSV - página inicial">
                <span class="brand-mark"><img src="<?php echo BASE_URL; ?>CSS/images/agsv_emblem.png" alt="" width="30" height="30"></span>
                <span class="brand-copy"><strong>AGSV</strong><small>Ginástica de São Vicente</small></span>
            </a>
        </div>

        <div class="sidebar-caption" id="nav-titulo">Navegação</div>
        <nav class="side-nav" aria-labelledby="nav-titulo">
            <ul class="nav-menu">
                <?php foreach ($grupos_menu_lateral as $grupo): ?>
                    <?php if ($grupo["itens"] === null): ?>
                        <li>
                            <a href="<?php echo BASE_URL . $grupo['href']; ?>"<?php echo nav_activo($grupo['chave'], $pagina_atual); ?>>
                                <i class="fa-solid <?php echo agsv_menu_icone($grupo['chave']); ?>" aria-hidden="true"></i>
                                <span><?php echo htmlspecialchars($grupo["label"]); ?></span>
                            </a>
                        </li>
                    <?php else: ?>
                        <?php $grupo_ativo = in_array($pagina_atual, array_column($grupo["itens"], "chave"), true); ?>
                        <li class="nav-group">
                            <details<?php echo $grupo_ativo ? " open" : ""; ?>>
                                <summary<?php echo $grupo_ativo ? ' class="activo"' : ""; ?>>
                                    <span><i class="fa-solid <?php echo agsv_menu_icone($grupo['chave']); ?>" aria-hidden="true"></i><?php echo htmlspecialchars($grupo["label"]); ?></span>
                                    <i class="fa-solid fa-chevron-down nav-chevron" aria-hidden="true"></i>
                                </summary>
                                <ul class="nav-submenu">
                                    <?php foreach ($grupo["itens"] as $item): ?>
                                        <li>
                                            <a href="<?php echo BASE_URL . $item['href']; ?>"<?php echo nav_activo($item['chave'], $pagina_atual); ?>>
                                                <i class="fa-solid <?php echo agsv_menu_icone($item['chave']); ?>" aria-hidden="true"></i>
                                                <span><?php echo htmlspecialchars($item["label"]); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <?php if (isset($_SESSION["user_id"])): ?>
                <div class="user-panel">
                    <div class="user-avatar" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($nome_atual ?? 'U', 0, 1, 'UTF-8'), 'UTF-8')); ?></div>
                    <div class="user-meta">
                        <strong><?php echo htmlspecialchars($nome_atual ?? ""); ?></strong>
                        <span><?php echo htmlspecialchars($rotulos_role[$role_atual] ?? 'Utilizador'); ?></span>
                    </div>
                    <button type="button" class="icon-button" id="userMenuButton" aria-haspopup="true" aria-expanded="false" aria-controls="dropdown-menu" aria-label="Opções da conta" title="Opções da conta">
                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown-menu" id="dropdown-menu">
                        <a href="<?php echo BASE_URL; ?>PHP_Pages/profile.php"><i class="fa-regular fa-user" aria-hidden="true"></i> O meu perfil</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-danger" href="<?php echo BASE_URL; ?>PHP_Pages/logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Terminar sessão</a>
                    </div>
                </div>
            <?php else: ?>
                <a class="btn btn-primary sidebar-login" href="<?php echo BASE_URL; ?>PHP_Pages/login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Entrar</a>
            <?php endif; ?>
        </div>
    </aside>

    <div class="content-wrap">
        <header class="topbar">
            <div class="topbar-context">
                <span class="topbar-kicker"><?php echo htmlspecialchars($secao_atual); ?></span>
                <strong><?php echo htmlspecialchars($titulo_curto); ?></strong>
            </div>
            <div class="topbar-actions">
                <?php if ($nome_atual): ?><span class="online-status"><span></span> Sessão ativa</span><?php endif; ?>
                <button type="button" class="icon-button" data-theme-toggle aria-label="Alternar tema escuro" title="Alternar tema">
                    <i class="fa-solid fa-moon theme-icon-light" aria-hidden="true"></i>
                    <i class="fa-solid fa-sun theme-icon-dark" aria-hidden="true"></i>
                </button>
            </div>
        </header>
        <main class="container page-shell" id="conteudo">
