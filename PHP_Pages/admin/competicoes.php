<?php
// PHP_Pages/admin/competicoes.php
// Página onde o administrador pode criar, editar e eliminar competições

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Área administrativa: ADMIN tem sempre acesso; um USER só entra se
// lhe tiver sido atribuído o menu correspondente (ver Parametrizações).
requireAdminArea($conn);
requireTransaction($conn, "COMPETICOES_GERIR");

// Todos os pedidos POST desta página alteram dados: exigimos sempre um
// token CSRF válido antes de processar qualquer ação.
exigirCsrf();

$erros = array();

// --- Eliminar uma competição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    $sql = "DELETE FROM competitions WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        setFlash("success", "Competição eliminada.");
    } else {
        setFlash("error", "Não foi possível eliminar esta competição porque já tem dados associados (juízes, atletas inscritos, pontuações, etc).");
    }

    header("Location: competicoes.php");
    exit;
}

// --- Criar ou atualizar uma competição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $id = (int) $_POST["id"];
    $nome = trim($_POST["name"]);
    $local = trim($_POST["location"]);
    $data_inicio = $_POST["start_date"];
    $data_fim = $_POST["end_date"];
    $stream_url = trim($_POST["stream_url"]);
    $tipo = isset($_POST["tipo"]) ? $_POST["tipo"] : "REGIONAL";

    // A checkbox só vem no $_POST se estiver marcada
    // (a coluna "status" é um ENUM('A','I'), por isso guardamos a letra e não um inteiro)
    $ativa = isset($_POST["status"]) ? "A" : "I";

    if ($nome == "") {
        $erros[] = "O nome da competição é obrigatório.";
    }
    if (!in_array($tipo, array("INTERNA", "REGIONAL"), true)) {
        $erros[] = "O tipo de competição tem de ser Interna ou Regional.";
    }
    if ($data_inicio == "" || $data_fim == "") {
        $erros[] = "As datas de início e fim são obrigatórias.";
    } elseif (!validarData($data_inicio) || !validarData($data_fim)) {
        $erros[] = "As datas têm de ser datas válidas.";
    } elseif ($data_fim < $data_inicio) {
        $erros[] = "A data de fim não pode ser anterior à data de início.";
    }

    if (count($erros) == 0) {

        if ($id > 0) {
            // Atualizar uma competição existente
            $sql = "UPDATE competitions
                    SET name = ?, location = ?, start_date = ?, end_date = ?, stream_url = ?, status = ?, tipo = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssi", $nome, $local, $data_inicio, $data_fim, $stream_url, $ativa, $tipo, $id);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Competição atualizada com sucesso.");
            } else {
                setFlash("error", "Não foi possível atualizar a competição. Tenta novamente.");
            }
        } else {
            // Criar uma nova competição
            $sql = "INSERT INTO competitions (name, location, start_date, end_date, stream_url, status, tipo)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssss", $nome, $local, $data_inicio, $data_fim, $stream_url, $ativa, $tipo);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Competição criada com sucesso.");
            } else {
                setFlash("error", "Não foi possível criar a competição. Tenta novamente.");
            }
        }

        header("Location: competicoes.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados dessa competição para pré-preencher o formulário ---
$competicao_a_editar = null;

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT * FROM competitions WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $competicao_a_editar = mysqli_fetch_assoc($resultado);
}

// --- Lista de todas as competições ---
$resultado_lista = mysqli_query($conn, "SELECT * FROM competitions ORDER BY start_date DESC");

$pageTitle = "Gerir Competições - " . APP_NAME;
$pagina_atual = "admin_competicoes";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Gerir Competições</h1>
    <p>Cria e edita competições e, a partir de cada uma, gere os escalões, os juízes, as inscrições e as notas.</p>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $competicao_a_editar ? "Editar competição" : "Nova competição"; ?></h2>

    <form method="post" action="competicoes.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?php echo $competicao_a_editar ? $competicao_a_editar['id'] : 0; ?>">

        <div class="form-row">
            <div>
                <label for="name">Nome</label>
                <input type="text" id="name" name="name" required
                       value="<?php echo $competicao_a_editar ? htmlspecialchars($competicao_a_editar['name']) : ''; ?>">
            </div>
            <div>
                <label for="location">Local <span class="label-optional">(opcional)</span></label>
                <input type="text" id="location" name="location"
                       value="<?php echo $competicao_a_editar ? htmlspecialchars($competicao_a_editar['location'] ?? "") : ''; ?>">
            </div>
        </div>

        <?php
        $tipo_atual = $competicao_a_editar ? $competicao_a_editar["tipo"] : "REGIONAL";
        ?>
        <label for="tipo">Tipo de competição</label>
        <select id="tipo" name="tipo" required>
            <option value="INTERNA" <?php echo $tipo_atual == "INTERNA" ? "selected" : ""; ?>>Interna (dentro do próprio clube)</option>
            <option value="REGIONAL" <?php echo $tipo_atual == "REGIONAL" ? "selected" : ""; ?>>Regional (entre clubes)</option>
        </select>

        <div class="form-row">
            <div>
                <label for="start_date">Data de início</label>
                <input type="date" id="start_date" name="start_date" required
                       value="<?php echo $competicao_a_editar ? $competicao_a_editar['start_date'] : ''; ?>">
            </div>
            <div>
                <label for="end_date">Data de fim</label>
                <input type="date" id="end_date" name="end_date" required
                       value="<?php echo $competicao_a_editar ? $competicao_a_editar['end_date'] : ''; ?>">
            </div>
        </div>

        <label for="stream_url">URL de transmissão <span class="label-optional">(opcional)</span></label>
        <input type="text" id="stream_url" name="stream_url" placeholder="https://" inputmode="url"
               value="<?php echo $competicao_a_editar ? htmlspecialchars($competicao_a_editar['stream_url'] ?? "") : ''; ?>">

        <label class="checkbox-label">
            <?php
            $marcada = true; // por defeito, uma competição nova começa ativa
            if ($competicao_a_editar && $competicao_a_editar["status"] != "A") {
                $marcada = false;
            }
            ?>
            <input type="checkbox" name="status" value="1" <?php echo $marcada ? "checked" : ""; ?>>
            Competição ativa
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?php echo $competicao_a_editar ? "Guardar alterações" : "Criar competição"; ?>
            </button>

            <?php if ($competicao_a_editar): ?>
                <a class="btn" href="competicoes.php">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="competitions-list">
    <?php
    // Permissões calculadas uma vez (e não uma vez por linha da tabela)
    $pode_escaloes = temPermissaoTransacao($conn, "COMPETICAO_ESCALOES_GERIR");
    $pode_atribuicoes = temPermissaoTransacao($conn, "ATRIBUICOES_GERIR");
    $pode_inscricoes = temPermissaoTransacao($conn, "INSCRICOES_GERIR");
    $pode_notas = temPermissaoTransacao($conn, "NOTAS_GERIR");
    ?>
    <h2>Competições existentes<span class="count-pill"><?php echo mysqli_num_rows($resultado_lista); ?></span></h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Ainda não existem competições registadas.</p>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Local</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Início</th>
                        <th scope="col">Fim</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($comp = mysqli_fetch_assoc($resultado_lista)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comp["name"]); ?></td>
                            <td class="cell-muted" data-label="Local"><?php echo htmlspecialchars($comp["location"] ?? ""); ?></td>
                            <td data-label="Tipo"><?php echo $comp["tipo"] == "INTERNA" ? "Interna" : "Regional"; ?></td>
                            <td class="num" data-label="Início"><?php echo formatarData($comp["start_date"]); ?></td>
                            <td class="num" data-label="Fim"><?php echo formatarData($comp["end_date"]); ?></td>
                            <td data-label="Estado"><span class="badge <?php echo $comp["status"] == "A" ? "badge-active" : "badge-inactive"; ?>"><?php echo $comp["status"] == "A" ? "Ativa" : "Inativa"; ?></span></td>
                            <td class="actions-cell actions-cell--wide">
                                <div class="actions-wrap">
                                    <a class="btn btn-small" href="competicoes.php?action=editar&id=<?php echo $comp['id']; ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</a>

                                    <?php if ($pode_escaloes): ?>
                                        <a class="btn btn-small btn-ghost" href="competicao_escaloes.php?competition_id=<?php echo $comp['id']; ?>"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Escalões</a>
                                    <?php endif; ?>

                                    <?php if ($pode_atribuicoes): ?>
                                        <a class="btn btn-small btn-ghost" href="atribuicoes.php?competition_id=<?php echo $comp['id']; ?>"><i class="fa-solid fa-gavel" aria-hidden="true"></i> Juízes</a>
                                    <?php endif; ?>

                                    <?php if ($pode_inscricoes): ?>
                                        <a class="btn btn-small btn-ghost" href="inscricoes.php?competition_id=<?php echo $comp['id']; ?>"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Atletas</a>
                                    <?php endif; ?>

                                    <?php if ($pode_notas): ?>
                                        <a class="btn btn-small btn-ghost" href="notas.php?competition_id=<?php echo $comp['id']; ?>"><i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i> Notas</a>
                                    <?php endif; ?>

                                    <form method="post" action="competicoes.php" class="inline-form" data-confirm="Eliminar esta competição? Esta ação não pode ser desfeita.">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $comp['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger-soft"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Eliminar</button>
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
