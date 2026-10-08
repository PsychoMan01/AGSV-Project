<?php
// includes/functions.php
// Pequenas funções auxiliares que vamos usar em várias páginas

// ==========================================================================
// SESSÃO / AUTENTICAÇÃO
// ==========================================================================

// Confirma que existe sessão iniciada, que o utilizador tem o papel pedido
// (ADMIN ou JUDGE) E que essa conta continua ativa na base de dados.
//
// Isto é importante porque, sem esta segunda verificação, uma sessão aberta
// ANTES de o administrador desativar/remover a conta continuaria a
// funcionar normalmente (o browser do juiz já tem os dados na sessão e
// nunca mais volta a passar pelo login). Por isso confirmamos sempre o
// estado atual do utilizador na base de dados, não só o que está na sessão.
function exigirSessao($conn, $papel_exigido) {
    if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != $papel_exigido) {
        header("Location: " . BASE_URL . "PHP_Pages/login.php");
        exit;
    }

    $user_id = $_SESSION["user_id"];
    $sql = "SELECT status FROM users WHERE id = ? AND role = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $papel_exigido);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $utilizador = mysqli_fetch_assoc($resultado);

    // Se o utilizador já não existir, ou já não tiver este papel, ou tiver
    // sido desativado (status != 'A'), a sessão é imediatamente encerrada.
    if (!$utilizador || $utilizador["status"] != "A") {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
        setFlash("error", "A tua conta foi desativada ou já não tem acesso. Fala com o administrador.");
        header("Location: " . BASE_URL . "PHP_Pages/login.php");
        exit;
    }
}

// Atalhos usados no topo de cada página protegida (substituem o antigo
// "if (!isset($_SESSION['user_id']) || ...)" repetido em todas as páginas).
function requireAdmin($conn) {
    exigirSessao($conn, "ADMIN");
}

function requireActiveJudge($conn) {
    exigirSessao($conn, "JUDGE");
}

// Sessão exigida para páginas que qualquer utilizador com conta pode ver,
// seja ele ADMIN, JUDGE ou USER (ex: a página de perfil). Segue o mesmo
// princípio das funções acima: confirma sempre o estado atual da conta na
// base de dados, não só o que está guardado na sessão.
function requireLogin($conn) {
    if (!isset($_SESSION["user_id"])) {
        header("Location: " . BASE_URL . "PHP_Pages/login.php");
        exit;
    }

    $user_id = $_SESSION["user_id"];
    $sql = "SELECT status FROM users WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $utilizador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$utilizador || $utilizador["status"] != "A") {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
        setFlash("error", "A tua conta foi desativada ou já não tem acesso. Fala com o administrador.");
        header("Location: " . BASE_URL . "PHP_Pages/login.php");
        exit;
    }
}

// Sessão exigida para entrar na área administrativa (painel de gestão).
// Ao contrário de requireAdmin(), aceita tanto o tipo técnico ADMIN como o
// tipo USER — desde a v5, um utilizador USER também pode ter acesso a esta
// área, mas só às páginas para as quais lhe tenham sido atribuídos menus
// (ver requireTransaction()/temPermissaoTransacao()). O JUDGE nunca entra
// aqui: tem o seu próprio fluxo em requireActiveJudge().
function requireAdminArea($conn) {
    if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"] ?? "", array("ADMIN", "USER"), true)) {
        header("Location: " . BASE_URL . "PHP_Pages/login.php");
        exit;
    }

    $user_id = $_SESSION["user_id"];
    $sql = "SELECT status, role FROM users WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $utilizador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$utilizador || $utilizador["status"] != "A" || !in_array($utilizador["role"], array("ADMIN", "USER"), true)) {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
        setFlash("error", "A tua conta foi desativada ou já não tem acesso. Fala com o administrador.");
        header("Location: " . BASE_URL . "PHP_Pages/login.php");
        exit;
    }
}




// Verifica se o utilizador atual tem acesso a uma transação administrativa.
function temPermissaoTransacao($conn, $codigo) {
    if (!isset($_SESSION["user_id"])) {
        return false;
    }

    if (($_SESSION["user_role"] ?? "") === "ADMIN") {
        return true;
    }

    $sql = "SELECT 1
            FROM transactions t
            JOIN user_transactions ut ON ut.transaction_id = t.id
            WHERE t.code = ? AND t.active = 1 AND ut.user_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $codigo, $_SESSION["user_id"]);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    return (bool) mysqli_fetch_assoc($resultado);
}

// Impede acesso direto a uma página quando a transação não está autorizada.
function requireTransaction($conn, $codigo) {
    if (!temPermissaoTransacao($conn, $codigo)) {
        setFlash("error", "Não tens permissão para aceder a esta área.");
        header("Location: " . BASE_URL . "PHP_Pages/index.php");
        exit;
    }
}

// Monta a estrutura do menu lateral (sidebar) apresentado em todas as
// páginas. Cada grupo pode ser um link direto (ex: "Home") ou um grupo com
// submenu (ex: "Competições"). Os submenus administrativos só aparecem se o
// utilizador atual tiver mesmo permissão para essa transação (o ADMIN tem
// sempre acesso a tudo, ver temPermissaoTransacao()). Alguns itens (ex:
// "Gerir escalões") aparecem propositadamente em mais do que um grupo,
// porque fazem sentido tanto a partir de "Competições" como de "Atletas".
function obterGruposMenuLateral($conn) {
    $grupos = array();

    // "Home" é sempre visível, mesmo sem sessão iniciada
    $grupos[] = array(
        "chave" => "inicio",
        "label" => "Home",
        "href"  => "PHP_Pages/index.php",
        "itens" => null,
    );

    // Listagem pública de competições — visível a todos, mesmo sem sessão
    // iniciada. Chama-se "Ver Competições" (e não só "Competições") para não
    // se confundir com o grupo de gestão mais abaixo, que tem esse nome.
    $grupos[] = array(
        "chave" => "competicoes_publico",
        "label" => "Ver Competições",
        "href"  => "PHP_Pages/competicoes.php",
        "itens" => null,
    );

    if (!isset($_SESSION["user_id"])) {
        return $grupos;
    }

    $papel = $_SESSION["user_role"] ?? "";

    if ($papel === "JUDGE") {
        $grupos[] = array(
            "chave" => "painel",
            "label" => "Painel",
            "href"  => "PHP_Pages/judge/painel.php",
            "itens" => null,
        );
        return $grupos;
    }

    if (!in_array($papel, array("ADMIN", "USER"), true)) {
        return $grupos;
    }

    // --- Dashboard ---
    $grupos[] = array(
        "chave" => "dashboard",
        "label" => "Dashboard",
        "href"  => "PHP_Pages/admin/painel.php",
        "itens" => null,
    );

    // --- Competições ---
    // "Gerir competições" é agora um link direto (e não um submenu): é o
    // ecrã principal de onde já se acede, por cada competição, aos seus
    // escalões, à atribuição de juízes, às inscrições de atletas e às
    // notas — por isso essas páginas deixaram de ter entrada própria no
    // menu lateral (ver botões de ação na tabela de competicoes.php).
    if (temPermissaoTransacao($conn, "COMPETICOES_GERIR")) {
        $grupos[] = array(
            "chave" => "admin_competicoes",
            "label" => "Gerir competições",
            "href"  => "PHP_Pages/admin/competicoes.php",
            "itens" => null,
        );
    }

    // --- Atletas ---
    $itens_atletas = array(
        array("chave" => "admin_atletas", "label" => "Gerir atletas", "href" => "PHP_Pages/admin/atletas.php", "codigo" => "ATLETAS_GERIR"),
        array("chave" => "admin_clubes",  "label" => "Gerir clubes",  "href" => "PHP_Pages/admin/clubes.php",  "codigo" => "CLUBES_GERIR"),
    );
    $itens_atletas = array_values(array_filter($itens_atletas, function ($item) use ($conn) {
        return temPermissaoTransacao($conn, $item["codigo"]);
    }));
    if (count($itens_atletas) > 0) {
        $grupos[] = array(
            "chave" => "atletas",
            "label" => "Atletas",
            "href"  => null,
            "itens" => $itens_atletas,
        );
    }

    // --- Catálogos ---
    // Dados de base usados tanto pelas competições como pelos atletas
    // (escalões, aparelhos, juízes). Agrupados aqui num único menu em vez
    // de espalhados/duplicados pelos grupos "Competições" e "Atletas".
    $itens_catalogos = array(
        array("chave" => "admin_escaloes",  "label" => "Gerir escalões",  "href" => "PHP_Pages/admin/escaloes.php",  "codigo" => "ESCALOES_GERIR"),
        array("chave" => "admin_aparelhos", "label" => "Gerir aparelhos", "href" => "PHP_Pages/admin/aparelhos.php", "codigo" => "APARELHOS_GERIR"),
        array("chave" => "admin_juizes",    "label" => "Gerir juízes",    "href" => "PHP_Pages/admin/juizes.php",    "codigo" => "JUIZES_GERIR"),
    );
    $itens_catalogos = array_values(array_filter($itens_catalogos, function ($item) use ($conn) {
        return temPermissaoTransacao($conn, $item["codigo"]);
    }));
    if (count($itens_catalogos) > 0) {
        $grupos[] = array(
            "chave" => "catalogos",
            "label" => "Catálogos",
            "href"  => null,
            "itens" => $itens_catalogos,
        );
    }

    // --- Utilizadores (exclusivo do ADMIN, tal como as próprias páginas) ---
    if ($papel === "ADMIN") {
        $grupos[] = array(
            "chave" => "utilizadores",
            "label" => "Utilizadores",
            "href"  => null,
            "itens" => array(
                array("chave" => "admin_utilizadores",    "label" => "Adicionar utilizadores", "href" => "PHP_Pages/admin/utilizadores.php"),
                array("chave" => "admin_parametrizacoes", "label" => "Parametrizações",         "href" => "PHP_Pages/admin/parametrizacoes.php"),
            ),
        );
    }

    return $grupos;
}

// Obtém os menus/transações autorizados para o utilizador atual. O ADMIN vê
// sempre todos os menus ativos; os restantes só veem os que lhes foram
// atribuídos individualmente em Parametrizações.
function obterMenuAutorizado($conn) {
    $menus = array();

    if (!isset($_SESSION["user_id"])) {
        return $menus;
    }

    $isAdmin = ($_SESSION["user_role"] ?? "") === "ADMIN";

    $campos = "m.id AS menu_id, m.name AS menu_name, m.label AS menu_label,
               m.route AS menu_route, m.sort_order,
               t.id AS transaction_id, t.label AS transaction_label,
               t.route AS transaction_route, t.code AS transaction_code,
               t.sort_order AS transaction_sort_order";

    if ($isAdmin) {
        $sql = "SELECT $campos
                FROM menus m
                JOIN transactions t ON t.menu_id = m.id AND t.active = 1
                WHERE m.active = 1
                ORDER BY m.sort_order, t.sort_order, t.label";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $menus;
        }
        mysqli_stmt_execute($stmt);
    } else {
        $sql = "SELECT $campos
                FROM menus m
                JOIN transactions t ON t.menu_id = m.id AND t.active = 1
                JOIN user_transactions ut ON ut.transaction_id = t.id
                WHERE m.active = 1 AND ut.user_id = ?
                ORDER BY m.sort_order, t.sort_order, t.label";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $menus;
        }
        mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
        mysqli_stmt_execute($stmt);
    }

    $resultado = mysqli_stmt_get_result($stmt);

    while ($linha = mysqli_fetch_assoc($resultado)) {
        $id = $linha["menu_id"];
        if (!isset($menus[$id])) {
            $menus[$id] = array(
                "label" => $linha["menu_label"],
                "route" => $linha["menu_route"],
                "items" => array()
            );
        }
        $menus[$id]["items"][] = $linha;
    }

    return $menus;
}

// Lista todas as transações administrativas disponíveis para atribuição a
// utilizadores (usada nos checkboxes de "Novo utilizador" e
// "Parametrizações"). Exclui de propósito UTILIZADORES_GERIR e
// PARAMETRIZACOES_GERIR: gerir utilizadores e as próprias parametrizações
// são poderes exclusivos do tipo técnico ADMIN e nunca podem ser delegados
// por menu, para não abrir uma forma indireta de um USER se autopromover.
function menusAtribuiveis($conn) {
    $sql = "SELECT t.id, t.code, t.label, m.label AS menu_label
            FROM transactions t
            JOIN menus m ON m.id = t.menu_id
            WHERE t.active = 1 AND m.active = 1
              AND t.code NOT IN ('UTILIZADORES_GERIR', 'PARAMETRIZACOES_GERIR')
            ORDER BY m.sort_order, t.sort_order, t.label";
    $resultado = mysqli_query($conn, $sql);
    $lista = array();
    while ($linha = mysqli_fetch_assoc($resultado)) {
        $lista[] = $linha;
    }
    return $lista;
}

// IDs das transações atualmente atribuídas a um utilizador específico.
function transacoesDoUtilizador($conn, $user_id) {
    $sql = "SELECT transaction_id FROM user_transactions WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $ids = array();
    while ($linha = mysqli_fetch_assoc($resultado)) {
        $ids[] = (int) $linha["transaction_id"];
    }
    return $ids;
}

// Substitui por completo os menus atribuídos a um utilizador pela lista de
// IDs de transação indicada (usada tanto em utilizadores.php como em
// parametrizacoes.php, para manter a mesma regra num único sítio).
function guardarMenusDoUtilizador($conn, $user_id, $ids_transacao) {
    $stmt = mysqli_prepare($conn, "DELETE FROM user_transactions WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    $ok = mysqli_stmt_execute($stmt);

    if ($ok && !empty($ids_transacao)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO user_transactions (user_id, transaction_id) VALUES (?, ?)");
        foreach ($ids_transacao as $transaction_id) {
            $transaction_id = (int) $transaction_id;
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $transaction_id);
            $ok = $ok && mysqli_stmt_execute($stmt);
        }
    }

    return $ok;
}

// ==========================================================================
// CSRF (Cross-Site Request Forgery)
// ==========================================================================

// Gera (ou reaproveita) um token único por sessão.
function csrfToken() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

// Campo escondido a incluir dentro de TODOS os formulários POST que alteram dados.
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

// Confirma se o token enviado no formulário bate certo com o da sessão.
function csrfValido() {
    return isset($_POST["csrf_token"])
        && isset($_SESSION["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"]);
}

// Chamar logo a seguir a requireAdmin()/requireActiveJudge(), antes de
// processar qualquer formulário POST. Corta o pedido se o token não bater certo
// (formulário forjado por outro site, ou sessão expirada).
function exigirCsrf() {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && !csrfValido()) {
        setFlash("error", "O teu pedido expirou ou é inválido (proteção contra CSRF). Tenta novamente.");
        $destino = basename($_SERVER["PHP_SELF"]);
        if (!empty($_SERVER["QUERY_STRING"])) {
            $destino .= "?" . $_SERVER["QUERY_STRING"];
        }
        header("Location: " . $destino);
        exit;
    }
}

// Guardar uma mensagem para mostrar depois de um redirect (ex: "Guardado com sucesso!")
function setFlash($tipo, $mensagem) {
    $_SESSION["flash_tipo"] = $tipo;
    $_SESSION["flash_mensagem"] = $mensagem;
}

// Mostrar a mensagem guardada (e depois apagá-la, para não aparecer outra vez)
function mostrarFlash() {
    if (isset($_SESSION["flash_mensagem"])) {
        $tipo = htmlspecialchars($_SESSION["flash_tipo"]);
        $mensagem = htmlspecialchars($_SESSION["flash_mensagem"]);
        $erro = $_SESSION["flash_tipo"] === "error";
        echo "<div class='alert alert-" . $tipo . "' role='" . ($erro ? "alert" : "status") . "'>"
           . "<i class='fa-solid " . ($erro ? "fa-circle-exclamation" : "fa-circle-check") . "' aria-hidden='true'></i>"
           . "<div class='alert-body'>" . $mensagem . "</div>"
           . "<button type='button' class='alert-close' data-dismiss='alert' aria-label='Fechar aviso'><i class='fa-solid fa-xmark' aria-hidden='true'></i></button>"
           . "</div>";

        unset($_SESSION["flash_tipo"]);
        unset($_SESSION["flash_mensagem"]);
    }
}

// Mostrar os erros de validação de um formulário num único aviso
function mostrarErros($erros) {
    if (empty($erros)) {
        return;
    }
    echo "<div class='alert alert-error' role='alert'>"
       . "<i class='fa-solid fa-circle-exclamation' aria-hidden='true'></i><div class='alert-body'>";
    foreach ($erros as $erro) {
        echo "<p>" . htmlspecialchars($erro) . "</p>";
    }
    echo "</div></div>";
}

// Transformar uma data "2026-08-07" em "07/08/2026"
function formatarData($data) {
    if (empty($data)) {
        return "-";
    }
    // Aceita tanto "AAAA-MM-DD" como "AAAA-MM-DD HH:MM:SS" — só nos
    // interessa a parte da data, por isso cortamos tudo o que vier a seguir
    // a um espaço antes de dividir por "-".
    $so_a_data = explode(" ", $data)[0];
    $partes = explode("-", $so_a_data); // ["2026", "08", "07"]
    if (count($partes) != 3) {
        return $data;
    }
    return $partes[2] . "/" . $partes[1] . "/" . $partes[0];
}

// ==========================================================================
// VALIDAÇÃO (tudo o que só estava validado no HTML passa a ser confirmado
// também aqui no servidor, porque um pedido POST pode ser forjado sem passar
// pelo formulário / pelos atributos "required", "min", "max", "type=email", etc.)
// ==========================================================================

// Confirma se uma string é uma data real no formato "AAAA-MM-DD" (o formato
// que os campos <input type="date"> enviam). Rejeita "2026-02-30", texto
// aleatório, etc.
function validarData($data) {
    if (!is_string($data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return false;
    }
    list($ano, $mes, $dia) = explode("-", $data);
    return checkdate((int) $mes, (int) $dia, (int) $ano);
}

// Confirma se uma data de nascimento é válida: formato correto, não pode ser
// no futuro, e não pode ser de uma idade irrealista. O projeto não definia
// um limite de idade máxima, por isso usamos 100 anos como limite de bom
// senso (documentado no relatório de auditoria).
function validarDataNascimento($data) {
    if (!validarData($data)) {
        return false;
    }
    $hoje = date("Y-m-d");
    if ($data > $hoje) {
        return false; // data no futuro
    }
    $ano_nascimento = (int) substr($data, 0, 4);
    $ano_atual = (int) date("Y");
    if (($ano_atual - $ano_nascimento) > 100) {
        return false; // idade irrealista
    }
    return true;
}

// Calcula a idade (em anos completos) a partir de uma data de nascimento
// válida ("AAAA-MM-DD"), tendo como referência a data de hoje.
function calcularIdade($data_nascimento) {
    $nascimento = new DateTime($data_nascimento);
    $hoje = new DateTime("today");
    return (int) $hoje->diff($nascimento)->y;
}

// Determina o escalão (categories) de um atleta a partir da sua idade atual,
// usando os intervalos min_age/max_age definidos em "Gerir escalões". Um
// min_age ou max_age em branco é tratado como "sem limite" desse lado do
// intervalo. Devolve o id do escalão encontrado, ou null se a idade do
// atleta não couber em nenhum escalão configurado (nesse caso o atleta fica
// sem escalão atribuído, tal como já acontecia antes quando não se escolhia
// nenhum).
function calcularEscalaoPorIdade($conn, $data_nascimento) {
    $idade = calcularIdade($data_nascimento);

    $sql = "SELECT id FROM categories
            WHERE (min_age IS NULL OR ? >= min_age)
              AND (max_age IS NULL OR ? <= max_age)
            ORDER BY min_age ASC
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $idade, $idade);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $linha = mysqli_fetch_assoc($resultado);

    return $linha ? (int) $linha["id"] : null;
}

// Confirma um email com a função nativa do PHP (mais fiável do que uma regex escrita à mão).
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validação simples de telefone: só dígitos, espaços e os símbolos "+ ( ) -",
// com um comprimento razoável. O projeto não definia um formato específico
// (nº de país, indicativo obrigatório, etc.), por isso mantemos esta validação
// genérica em vez de inventar um formato rígido.
function validarTelefone($telefone) {
    return (bool) preg_match('/^[0-9+\-\s()]{5,20}$/', $telefone);
}

// Confirma se um valor enviado por um juiz é um número de pontuação válido:
// tem de ser numérico, não pode ser negativo, e tem de ficar dentro de um
// limite generoso de bom senso. O projeto não definia limites (min/max) para
// as notas em lado nenhum (nem no HTML, nem na base de dados), por isso não
// inventamos uma escala de pontuação nova (ex: 0-10) — apenas bloqueamos
// valores manifestamente inválidos como negativos, texto, ou números
// enormes. Este limite (100) está documentado no relatório de auditoria como
// um ponto que precisa de confirmação com as regras oficiais da modalidade.
function validarNotaJuiz($valor) {
    if (!is_numeric($valor)) {
        return false;
    }
    $numero = (float) $valor;
    if (!is_finite($numero)) {
        return false;
    }
    if ($numero < 0 || $numero > 100) {
        return false;
    }
    return true;
}

// Lista dos papéis que um juiz pode ter, usada para preencher os <select>
// nos formulários de juízes e de atribuições.
function papeis() {
    return array(
        "A"  => "A - Artístico",
        "E"  => "E - Execução",
        "DB" => "DB - Dificuldade de Corpo",
        "DA" => "DA - Dificuldade de Aparelho",
    );
}

// Nome completo de um papel a partir do código (ex: "DB" -> "DB - Dificuldade de Corpo")
function papelLabel($codigo) {
    $lista = papeis();
    return isset($lista[$codigo]) ? $lista[$codigo] : $codigo;
}

// Diz quais os campos de pontuação que um juiz com este papel pode preencher.
// Cada juiz só vê e só pontua os campos do SEU papel:
// - A  (Artístico)            -> A1, A2, A3, A4
// - E  (Execução)              -> E1, E2, E3, E4, penal (as penalizações são
//                                 marcadas por quem avalia a execução)
// - DB (Dificuldade de Corpo)  -> DB1, DB2
// - DA (Dificuldade de Aparelho) -> DA1, DA2
function camposPorPapel($papel) {
    $mapa = array(
        "A"  => array("A1", "A2", "A3", "A4"),
        "E"  => array("E1", "E2", "E3", "E4", "penal"),
        "DB" => array("DB1", "DB2"),
        "DA" => array("DA1", "DA2"),
    );
    return isset($mapa[$papel]) ? $mapa[$papel] : array();
}

// Lista dos campos "numeráveis" de cada papel (sem o "penal", que não é
// atribuído a um número — ver mais abaixo). É a partir desta lista que
// sabemos, por exemplo, que o papel "A" tem 4 juízes possíveis (A1 a A4) e
// que o papel "DB" só tem 2 (DB1, DB2).
function camposNumeraveisPorPapel($papel) {
    $mapa = array(
        "A"  => array("A1", "A2", "A3", "A4"),
        "E"  => array("E1", "E2", "E3", "E4"),
        "DB" => array("DB1", "DB2"),
        "DA" => array("DA1", "DA2"),
    );
    return isset($mapa[$papel]) ? $mapa[$papel] : array();
}

// Quantos juízes (números) cabem num determinado papel. Ex: papel "A" -> 4,
// papel "DB" -> 2. Usado para limitar o número que o administrador pode
// atribuir (ex: rejeitar "DB3", que não existe).
function numeroMaximoPorPapel($papel) {
    return count(camposNumeraveisPorPapel($papel));
}

// Dado um papel e um número de juiz (ex: "A", 2), devolve o nome do campo
// que esse juiz específico deve preencher (ex: "A2"). Devolve null se o
// número não existir para aquele papel (ex: "DB", 3).
function campoPorNumero($papel, $numero) {
    $campos = camposNumeraveisPorPapel($papel);
    $indice = ((int) $numero) - 1;
    return isset($campos[$indice]) ? $campos[$indice] : null;
}

// Etiqueta curta mostrada ao juiz para identificar a sua atribuição
// (ex: papel "A", número 2 -> "A2"). É isto que aparece no ecrã do juiz,
// em vez de ele ver todos os campos A1, A2, A3, A4 ao mesmo tempo.
function campoNumeroLabel($papel, $numero) {
    $campo = campoPorNumero($papel, $numero);
    return $campo !== null ? $campo : ($papel . $numero);
}

// Lista dos géneros disponíveis para um atleta, usada para preencher o <select>
// no formulário de atletas.
function generos() {
    return array(
        "F"    => "Feminino",
        "M"    => "Masculino",
        "N/A"  => "Não aplicável",
    );
}

// Nome completo de um género a partir do código (ex: "F" -> "Feminino")
function generoLabel($codigo) {
    $lista = generos();
    return isset($lista[$codigo]) ? $lista[$codigo] : $codigo;
}

// ==========================================================================
// CLASSIFICAÇÕES / RANKING
// ==========================================================================

// Devolve, para um escalão+aparelho+competição, a lista das atribuições de
// juízes que são "obrigatórias" (ou seja, os juízes que o administrador
// realmente atribuiu a essa combinação). É esta lista que diz quais notas
// têm de existir para uma classificação poder ser considerada completa.
function juizesObrigatoriosPorEntrada($conn, $competition_id, $category_id, $equipment_id) {
    $sql = "SELECT judge_id, papel, numero_juiz FROM judge_assignments
            WHERE competition_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $competition_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    $lista = array();
    while ($linha = mysqli_fetch_assoc($resultado)) {
        $lista[] = $linha;
    }
    return $lista;
}

// Recalcula a tabela "rankings" de uma competição a partir das pontuações (scores) já submetidas.
//
// Como agora cada juiz só pontua o ÚNICO campo correspondente ao número que
// lhe foi atribuído (ex: o juiz "A2" só preenche A2), a nota final de um
// atleta é construída juntando a parte de cada papel:
//
//   nota final = soma(A) + soma(E) + soma(DB) + soma(DA) - média(penal)
//
// Cada "soma" é a soma dos campos numerados desse papel (A1+A2+A3+A4, etc.),
// usando AVG()+COALESCE() por campo para nunca deixar uma nota válida virar
// NULL só porque os outros campos dessa linha estão vazios (cada juiz só
// preenche o seu próprio campo).
//
// NOVO: uma classificação só é marcada como "final" (com posição atribuída)
// se TODOS os juízes atribuídos a esse escalão/aparelho já tiverem
// submetido a sua nota para aquele atleta. Se faltar algum, a linha fica
// gravada com status = 'parcial' e sem posição, para nunca se publicar um
// resultado como se as notas em falta valessem zero.
//
// Devolve um array com "pendencias" (descrições legíveis do que falta),
// para a página de administração poder mostrar ao utilizador.
function calcularClassificacoes($conn, $competition_id) {

    $pendencias = array();

    mysqli_begin_transaction($conn);

    $sucesso = true;

    // Limpar as classificações anteriores desta competição, para recalcular tudo de novo
    $sql_del = "DELETE FROM rankings WHERE competition_id = ?";
    $stmt_del = mysqli_prepare($conn, $sql_del);
    mysqli_stmt_bind_param($stmt_del, "i", $competition_id);
    $sucesso = $sucesso && mysqli_stmt_execute($stmt_del);

    // Para cada atleta/escalão/aparelho, calcular a nota de cada papel.
    $sql = "SELECT ee.id AS event_entry_id, ee.category_id, ee.equipment_id, ee.athlete_id,
                   COALESCE(AVG(CASE WHEN s.papel = 'DB' THEN s.DB1 END), 0)
                 + COALESCE(AVG(CASE WHEN s.papel = 'DB' THEN s.DB2 END), 0) AS media_db,
                   COALESCE(AVG(CASE WHEN s.papel = 'DA' THEN s.DA1 END), 0)
                 + COALESCE(AVG(CASE WHEN s.papel = 'DA' THEN s.DA2 END), 0) AS media_da,
                   COALESCE(AVG(CASE WHEN s.papel = 'A' THEN s.A1 END), 0)
                 + COALESCE(AVG(CASE WHEN s.papel = 'A' THEN s.A2 END), 0)
                 + COALESCE(AVG(CASE WHEN s.papel = 'A' THEN s.A3 END), 0)
                 + COALESCE(AVG(CASE WHEN s.papel = 'A' THEN s.A4 END), 0) AS media_a,
                   COALESCE(AVG(CASE WHEN s.papel = 'E' THEN s.E1 END), 0)
                 + COALESCE(AVG(CASE WHEN s.papel = 'E' THEN s.E2 END), 0)
                 + COALESCE(AVG(CASE WHEN s.papel = 'E' THEN s.E3 END), 0)
                 + COALESCE(AVG(CASE WHEN s.papel = 'E' THEN s.E4 END), 0) AS media_e,
                   AVG(CASE WHEN s.papel = 'E' THEN s.penal END) AS media_penal
            FROM event_entries ee
            LEFT JOIN scores s ON s.event_entry_id = ee.id AND s.status = 'A'
            WHERE ee.competition_id = ?
            GROUP BY ee.id, ee.category_id, ee.equipment_id, ee.athlete_id";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $competition_id);
    $sucesso = $sucesso && mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    // Nomes para mensagens de pendências mais claras
    $sql_nomes = "SELECT a.id, a.name FROM athletes a";
    $nomes_atletas = array();
    $res_nomes = mysqli_query($conn, $sql_nomes);
    while ($n = mysqli_fetch_assoc($res_nomes)) {
        $nomes_atletas[$n["id"]] = $n["name"];
    }
    $nomes_categorias = array();
    $res_cat = mysqli_query($conn, "SELECT id, name FROM categories");
    while ($n = mysqli_fetch_assoc($res_cat)) {
        $nomes_categorias[$n["id"]] = $n["name"];
    }
    $nomes_aparelhos = array();
    $res_eq = mysqli_query($conn, "SELECT id, name FROM equipments");
    while ($n = mysqli_fetch_assoc($res_eq)) {
        $nomes_aparelhos[$n["id"]] = $n["name"];
    }

    // Juízes obrigatórios por escalão+aparelho (cache para não repetir a mesma
    // consulta para cada atleta do mesmo escalão/aparelho)
    $cache_obrigatorios = array();

    // Juízes que já submeteram nota, por entrada de competição
    $submetidos_por_entrada = array();
    $sql_submetidos = "SELECT s.event_entry_id, s.judge_id
                        FROM scores s
                        JOIN event_entries ee ON ee.id = s.event_entry_id
                        WHERE ee.competition_id = ? AND s.status = 'A'";
    $stmt_sub = mysqli_prepare($conn, $sql_submetidos);
    mysqli_stmt_bind_param($stmt_sub, "i", $competition_id);
    mysqli_stmt_execute($stmt_sub);
    $res_sub = mysqli_stmt_get_result($stmt_sub);
    while ($linha_sub = mysqli_fetch_assoc($res_sub)) {
        $submetidos_por_entrada[$linha_sub["event_entry_id"]][] = (int) $linha_sub["judge_id"];
    }

    // Juntar as notas finais numa lista PHP (para depois ordenar por escalão/aparelho)
    $classificacoes = array();

    while ($linha = mysqli_fetch_assoc($resultado)) {

        $media_db    = $linha["media_db"]    !== null ? (float) $linha["media_db"]    : 0;
        $media_da    = $linha["media_da"]    !== null ? (float) $linha["media_da"]    : 0;
        $media_a     = $linha["media_a"]     !== null ? (float) $linha["media_a"]     : 0;
        $media_e     = $linha["media_e"]     !== null ? (float) $linha["media_e"]     : 0;
        $media_penal = $linha["media_penal"] !== null ? (float) $linha["media_penal"] : 0;

        $nota_final = round($media_db + $media_da + $media_a + $media_e - $media_penal, 3);

        // --- Verificar se esta entrada está completa (todos os juízes
        // atribuídos a este escalão/aparelho já submeteram a nota) ---
        $chave_cache = $linha["category_id"] . "-" . $linha["equipment_id"];
        if (!isset($cache_obrigatorios[$chave_cache])) {
            $cache_obrigatorios[$chave_cache] = juizesObrigatoriosPorEntrada(
                $conn, $competition_id, $linha["category_id"], $linha["equipment_id"]
            );
        }
        $obrigatorios = $cache_obrigatorios[$chave_cache];
        $ja_submeteram = isset($submetidos_por_entrada[$linha["event_entry_id"]])
            ? $submetidos_por_entrada[$linha["event_entry_id"]]
            : array();

        $em_falta = array();
        foreach ($obrigatorios as $atribuicao_obrigatoria) {
            if (!in_array((int) $atribuicao_obrigatoria["judge_id"], $ja_submeteram)) {
                $em_falta[] = campoNumeroLabel($atribuicao_obrigatoria["papel"], $atribuicao_obrigatoria["numero_juiz"]);
            }
        }

        // Se não houver NENHUM juiz atribuído a este escalão/aparelho, também
        // não há como considerar a nota completa (não existem dados suficientes).
        $completo = (count($obrigatorios) > 0) && (count($em_falta) == 0);

        if (!$completo) {
            $nome_atleta = isset($nomes_atletas[$linha["athlete_id"]]) ? $nomes_atletas[$linha["athlete_id"]] : ("Atleta #" . $linha["athlete_id"]);
            $nome_categoria = isset($nomes_categorias[$linha["category_id"]]) ? $nomes_categorias[$linha["category_id"]] : ("Escalão #" . $linha["category_id"]);
            $nome_aparelho = isset($nomes_aparelhos[$linha["equipment_id"]]) ? $nomes_aparelhos[$linha["equipment_id"]] : ("Aparelho #" . $linha["equipment_id"]);

            if (count($obrigatorios) == 0) {
                $pendencias[] = $nome_atleta . " (" . $nome_categoria . " — " . $nome_aparelho . "): não há juízes atribuídos a este escalão/aparelho.";
            } else {
                $pendencias[] = $nome_atleta . " (" . $nome_categoria . " — " . $nome_aparelho . "): faltam os juízes " . implode(", ", $em_falta) . ".";
            }
        }

        $classificacoes[] = array(
            "category_id"  => $linha["category_id"],
            "equipment_id" => $linha["equipment_id"],
            "athlete_id"   => $linha["athlete_id"],
            "nota_final"   => $nota_final,
            "completo"     => $completo,
        );
    }

    // Ordenar por escalão, aparelho e depois pela nota final (da maior para a menor)
    usort($classificacoes, function ($a, $b) {
        if ($a["category_id"] != $b["category_id"]) {
            return $a["category_id"] <=> $b["category_id"];
        }
        if ($a["equipment_id"] != $b["equipment_id"]) {
            return $a["equipment_id"] <=> $b["equipment_id"];
        }
        return $b["nota_final"] <=> $a["nota_final"];
    });

    $sql_insert = "INSERT INTO rankings (competition_id, category_id, equipment_id, athlete_id, final_score, position, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);

    $grupo_atual = null;
    $posicao = 0;

    foreach ($classificacoes as $linha) {

        // A posição só conta as classificações COMPLETAS. As parciais entram
        // na tabela (para o admin ver o que está pendente), mas sem posição,
        // e a página pública nunca as mostra como resultado final.
        if ($linha["completo"]) {
            $chave_grupo = $linha["category_id"] . "-" . $linha["equipment_id"];
            if ($chave_grupo != $grupo_atual) {
                $grupo_atual = $chave_grupo;
                $posicao = 0;
            }
            $posicao++;
            $posicao_bind = $posicao;
            $status_bind = "final";
        } else {
            $posicao_bind = null;
            $status_bind = "parcial";
        }

        mysqli_stmt_bind_param(
            $stmt_insert, "iiiidis",
            $competition_id, $linha["category_id"], $linha["equipment_id"], $linha["athlete_id"],
            $linha["nota_final"], $posicao_bind, $status_bind
        );
        $sucesso = $sucesso && mysqli_stmt_execute($stmt_insert);
    }

    if ($sucesso) {
        mysqli_commit($conn);
    } else {
        mysqli_rollback($conn);
        // Se a transação falhou, o ranking anterior mantém-se tal como estava.
        $pendencias = array("Ocorreu um erro ao recalcular as classificações. O ranking anterior foi mantido.");
    }

    return array(
        "ok"         => $sucesso,
        "pendencias" => $pendencias,
    );
}

// Monta a "Classificação Geral" de uma competição: uma linha por atleta, com
// uma coluna para cada aparelho usado nesta competição (a nota já submetida
// nesse aparelho — mesmo que ainda faltem juízes — ou "null" se ainda
// nenhum juiz pontuou o atleta nesse aparelho) e a soma dessas notas como
// pontuação final. As posições (1º, 2º, 3º...) são calculadas separadamente
// dentro de cada escalão, da maior para a menor pontuação final, e só
// contam atletas com pelo menos uma nota já submetida.
//
// Esta é uma classificação AO VIVO: como lê sempre da tabela "rankings"
// (que calcularClassificacoes() recalcula automaticamente a cada nota
// submetida, ver submeter_pontuacao.php), atualiza-se sozinha à medida que
// os juízes vão pontuando, mesmo antes de estarem todos completos.
function obterClassificacaoGeral($conn, $competition_id) {

    // Aparelhos usados nesta competição (definem as colunas da tabela)
    $sql_aparelhos = "SELECT DISTINCT eq.id, eq.name
                       FROM event_entries ee
                       JOIN equipments eq ON eq.id = ee.equipment_id
                       WHERE ee.competition_id = ?
                       ORDER BY eq.name";
    $stmt1 = mysqli_prepare($conn, $sql_aparelhos);
    mysqli_stmt_bind_param($stmt1, "i", $competition_id);
    mysqli_stmt_execute($stmt1);
    $res1 = mysqli_stmt_get_result($stmt1);
    $aparelhos = array();
    while ($linha = mysqli_fetch_assoc($res1)) {
        $aparelhos[] = $linha;
    }

    // Atletas inscritos nesta competição, com o respetivo escalão
    $sql_atletas = "SELECT DISTINCT ee.athlete_id, a.name AS athlete_name,
                            ee.category_id, c.name AS category_name
                     FROM event_entries ee
                     JOIN athletes a ON a.id = ee.athlete_id
                     JOIN categories c ON c.id = ee.category_id
                     WHERE ee.competition_id = ?
                     ORDER BY c.name, a.name";
    $stmt2 = mysqli_prepare($conn, $sql_atletas);
    mysqli_stmt_bind_param($stmt2, "i", $competition_id);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    $atletas = array();
    while ($linha = mysqli_fetch_assoc($res2)) {
        $atletas[] = $linha;
    }

    // Notas calculadas (finais ou ainda parciais), indexadas por
    // "athlete_id-equipment_id" para consulta rápida
    $sql_notas = "SELECT athlete_id, equipment_id, final_score
                  FROM rankings
                  WHERE competition_id = ?";
    $stmt3 = mysqli_prepare($conn, $sql_notas);
    mysqli_stmt_bind_param($stmt3, "i", $competition_id);
    mysqli_stmt_execute($stmt3);
    $res3 = mysqli_stmt_get_result($stmt3);
    $notas = array();
    while ($linha = mysqli_fetch_assoc($res3)) {
        $notas[$linha["athlete_id"] . "-" . $linha["equipment_id"]] = (float) $linha["final_score"];
    }

    // Quais atleta+aparelho já têm pelo menos uma nota de algum juiz. É isto,
    // e não a nota em si, que distingue "0,000 pontos" de "ainda ninguém
    // pontuou" (--) — enquanto ninguém pontuou, a nota calculada é sempre 0.
    $sql_pontuados = "SELECT DISTINCT ee.athlete_id, ee.equipment_id
                       FROM event_entries ee
                       JOIN scores s ON s.event_entry_id = ee.id
                       WHERE ee.competition_id = ?";
    $stmt4 = mysqli_prepare($conn, $sql_pontuados);
    mysqli_stmt_bind_param($stmt4, "i", $competition_id);
    mysqli_stmt_execute($stmt4);
    $res4 = mysqli_stmt_get_result($stmt4);
    $ja_pontuados = array();
    while ($linha = mysqli_fetch_assoc($res4)) {
        $ja_pontuados[$linha["athlete_id"] . "-" . $linha["equipment_id"]] = true;
    }

    // Montar uma linha por atleta, com a nota de cada aparelho (ou null se
    // ainda nenhum juiz pontuou o atleta nesse aparelho) e a soma como
    // pontuação final
    $linhas = array();
    foreach ($atletas as $atleta) {
        $notas_por_aparelho = array();
        $soma = 0;
        $ja_pontuado_algures = false;

        foreach ($aparelhos as $aparelho) {
            $chave = $atleta["athlete_id"] . "-" . $aparelho["id"];
            if (isset($ja_pontuados[$chave])) {
                $nota = isset($notas[$chave]) ? $notas[$chave] : 0;
                $notas_por_aparelho[$aparelho["id"]] = $nota;
                $soma += $nota;
                $ja_pontuado_algures = true;
            } else {
                $notas_por_aparelho[$aparelho["id"]] = null;
            }
        }

        $linhas[] = array(
            "athlete_id"         => $atleta["athlete_id"],
            "athlete_name"       => $atleta["athlete_name"],
            "category_id"        => $atleta["category_id"],
            "category_name"      => $atleta["category_name"],
            "notas_por_aparelho" => $notas_por_aparelho,
            "pontuacao_final"    => $soma,
            "ja_pontuado_algures" => $ja_pontuado_algures,
        );
    }

    // Ordenar por escalão e, dentro de cada escalão, pela pontuação final
    // (da maior para a menor)
    usort($linhas, function ($a, $b) {
        if ($a["category_id"] != $b["category_id"]) {
            return $a["category_name"] <=> $b["category_name"];
        }
        return $b["pontuacao_final"] <=> $a["pontuacao_final"];
    });

    // Atribuir a posição (1º, 2º, 3º...) dentro de cada escalão. Um atleta
    // que ainda não tem nenhuma nota não entra na contagem de posições
    // (fica sem posição, só com "--" nas colunas dos aparelhos).
    $escalao_atual = null;
    $posicao = 0;
    foreach ($linhas as &$linha) {
        if ($linha["category_id"] != $escalao_atual) {
            $escalao_atual = $linha["category_id"];
            $posicao = 0;
        }
        if ($linha["ja_pontuado_algures"]) {
            $posicao++;
            $linha["posicao"] = $posicao;
        } else {
            $linha["posicao"] = null;
        }
    }
    unset($linha);

    return array(
        "aparelhos" => $aparelhos,
        "linhas"    => $linhas,
    );
}

// Devolve a competição ativa cuja data de hoje cai dentro do seu intervalo
// (start_date <= hoje <= end_date), para ser mostrada na Home. Se houver
// mais do que uma a decorrer no mesmo dia, mostra a que começou primeiro.
// Devolve null se não houver nenhuma competição a decorrer hoje.
function obterCompeticaoDeHoje($conn) {
    $sql = "SELECT * FROM competitions
            WHERE status = 'A' AND CURDATE() BETWEEN start_date AND end_date
            ORDER BY start_date ASC
            LIMIT 1";
    $resultado = mysqli_query($conn, $sql);
    return $resultado ? mysqli_fetch_assoc($resultado) : null;
}

// ==========================================================================
// RELATÓRIOS (exportação em CSV)
// ==========================================================================

// Envia os cabeçalhos HTTP necessários para o browser fazer download de um
// ficheiro CSV com o nome indicado, em vez de o mostrar na página.
function iniciarDownloadCsv($nome_ficheiro) {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $nome_ficheiro . "\"");
    // BOM UTF-8: sem isto, acentos (ã, ç, õ, ...) aparecem trocados ao abrir
    // o CSV diretamente no Excel
    echo "\xEF\xBB\xBF";
}

// Exporta diretamente o resultado de uma query (mysqli_result) como CSV,
// usando os nomes das colunas da própria query como cabeçalho.
function exportarCsv($nome_ficheiro, $resultado) {
    iniciarDownloadCsv($nome_ficheiro);

    $saida = fopen("php://output", "w");

    if ($resultado && mysqli_num_rows($resultado) > 0) {
        $campos = mysqli_fetch_fields($resultado);
        $cabecalho = array();
        foreach ($campos as $campo) {
            $cabecalho[] = $campo->name;
        }
        fputcsv($saida, $cabecalho);

        while ($linha = mysqli_fetch_assoc($resultado)) {
            fputcsv($saida, $linha);
        }
    }

    fclose($saida);
}

// Exporta um array associativo (já calculado em PHP, não vindo diretamente
// de uma query) como CSV, usando as chaves da primeira linha como cabeçalho.
function exportarCsvArray($nome_ficheiro, $linhas) {
    iniciarDownloadCsv($nome_ficheiro);

    $saida = fopen("php://output", "w");

    if (count($linhas) > 0) {
        fputcsv($saida, array_keys($linhas[0]));
        foreach ($linhas as $linha) {
            fputcsv($saida, $linha);
        }
    }

    fclose($saida);
}
