<?php
// PHP_Pages/admin/atletas.php
// Página onde o administrador pode criar, editar e remover atletas.
// Cada atleta pertence a um clube; o escalão (categoria) já não é escolhido
// manualmente aqui — é calculado automaticamente a partir da idade do
// atleta, usando os intervalos min_age/max_age definidos em "Gerir escalões".

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/functions.php";

// Área administrativa: ADMIN tem sempre acesso; um USER só entra se
// lhe tiver sido atribuído o menu correspondente (ver Parametrizações).
requireAdminArea($conn);
requireTransaction($conn, "ATLETAS_GERIR");

// Todos os pedidos POST desta página alteram dados: exigimos sempre um
// token CSRF válido antes de processar qualquer ação.
exigirCsrf();

$erros = array();

// --- Remover um atleta ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "eliminar") {
    $id = (int) $_POST["id"];

    // Se o atleta já tiver inscrições/pontuações associadas, a base de dados
    // vai recusar o apagar (chave estrangeira) — nesse caso avisamos o admin.
    $sql = "DELETE FROM athletes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $ok = mysqli_stmt_execute($stmt);

    if ($ok) {
        setFlash("success", "Atleta removido com sucesso.");
    } else {
        setFlash("error", "Não foi possível remover este atleta porque já tem inscrições ou pontuações associadas.");
    }

    header("Location: atletas.php");
    exit;
}

// --- Criar ou atualizar um atleta ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "guardar") {

    $id = (int) $_POST["id"];
    $nome = trim($_POST["name"]);
    $data_nascimento = $_POST["birth_date"];
    $genero = $_POST["gender"];
    $local_nascimento = trim($_POST["place_of_birth"]);
    $telefone = trim($_POST["phone_number"]);
    $email = trim($_POST["email"]);
    $foto = trim($_POST["photo"]);
    $club_id = (int) $_POST["club_id"];
    // O escalão já não vem do formulário — é sempre calculado a partir da
    // data de nascimento, mais abaixo, depois de validarmos essa data.

    if ($nome == "") {
        $erros[] = "O nome é obrigatório.";
    }
    if ($data_nascimento == "") {
        $erros[] = "A data de nascimento é obrigatória.";
    } elseif (!validarDataNascimento($data_nascimento)) {
        $erros[] = "A data de nascimento é inválida (verifica se não está no futuro nem é uma data impossível).";
    }
    if ($local_nascimento == "") {
        $erros[] = "A naturalidade é obrigatória.";
    }
    if ($telefone == "") {
        $erros[] = "O telefone é obrigatório.";
    } elseif (!validarTelefone($telefone)) {
        $erros[] = "O telefone tem um formato inválido.";
    }
    if ($email == "") {
        $erros[] = "O email é obrigatório.";
    } elseif (!validarEmail($email)) {
        $erros[] = "O email tem um formato inválido.";
    }
    if ($club_id == 0) {
        $erros[] = "Escolhe o clube do atleta.";
    } else {
        // Confirmar que o clube escolhido realmente existe (evita um ID manipulado no pedido)
        $sql_clube = "SELECT id FROM clubs WHERE id = ? LIMIT 1";
        $stmt_clube = mysqli_prepare($conn, $sql_clube);
        mysqli_stmt_bind_param($stmt_clube, "i", $club_id);
        mysqli_stmt_execute($stmt_clube);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_clube))) {
            $erros[] = "O clube escolhido não existe.";
        }
    }
    if (!array_key_exists($genero, generos())) {
        $erros[] = "Escolhe um género válido.";
    }

    if (count($erros) == 0) {

        // O escalão é sempre recalculado a partir da idade atual do atleta
        // (mesmo ao editar, para o caso de a data de nascimento ter sido
        // corrigida). Fica NULL se a idade não couber em nenhum escalão
        // configurado.
        $category_id_param = calcularEscalaoPorIdade($conn, $data_nascimento);

        if ($category_id_param === null) {
            $aviso_escalao = " Aviso: a idade do atleta (" . calcularIdade($data_nascimento) . " anos) não corresponde a nenhum escalão configurado — o atleta ficou sem escalão atribuído. Verifica os intervalos de idade em 'Gerir escalões'.";
        } else {
            $aviso_escalao = "";
        }

        if ($id > 0) {
            $sql = "UPDATE athletes
                    SET name = ?, birth_date = ?, gender = ?, place_of_birth = ?,
                        phone_number = ?, email = ?, photo = ?, club_id = ?, category_id = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssiii", $nome, $data_nascimento, $genero, $local_nascimento,
                $telefone, $email, $foto, $club_id, $category_id_param, $id);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Atleta atualizado com sucesso." . $aviso_escalao);
            } else {
                setFlash("error", "Não foi possível atualizar o atleta. Verifica os dados introduzidos.");
            }
        } else {
            $sql = "INSERT INTO athletes (name, birth_date, gender, place_of_birth, phone_number, email, photo, club_id, category_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssii", $nome, $data_nascimento, $genero, $local_nascimento,
                $telefone, $email, $foto, $club_id, $category_id_param);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                setFlash("success", "Atleta criado com sucesso." . $aviso_escalao);
            } else {
                setFlash("error", "Não foi possível criar o atleta. Verifica os dados introduzidos.");
            }
        }

        header("Location: atletas.php");
        exit;
    }
}

// --- Se estivermos a editar, ir buscar os dados desse atleta para pré-preencher o formulário ---
$atleta_a_editar = null;

if (isset($_GET["action"]) && $_GET["action"] == "editar" && isset($_GET["id"])) {
    $id_editar = (int) $_GET["id"];

    $sql = "SELECT a.*, cat.name AS category_name
            FROM athletes a
            LEFT JOIN categories cat ON cat.id = a.category_id
            WHERE a.id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $atleta_a_editar = mysqli_fetch_assoc($resultado);
}

// Lista para preencher o <select> de clubes do formulário
$clubes = mysqli_query($conn, "SELECT id, name FROM clubs ORDER BY name");

// --- Lista de todos os atletas ---
$resultado_lista = mysqli_query($conn, "SELECT a.*, c.name AS club_name, cat.name AS category_name
                                         FROM athletes a
                                         LEFT JOIN clubs c ON c.id = a.club_id
                                         LEFT JOIN categories cat ON cat.id = a.category_id
                                         ORDER BY a.name");

$pageTitle = "Gerir Atletas - " . APP_NAME;
$pagina_atual = "admin_atletas";
require __DIR__ . "/../../includes/header.php";
?>

<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1>Gerir Atletas</h1>
    <p>Regista os atletas e o respetivo clube. O escalão é calculado automaticamente pela idade.</p>
</section>

<?php mostrarErros($erros); ?>
<?php mostrarFlash(); ?>

<section class="form-card">
    <h2><?php echo $atleta_a_editar ? "Editar atleta" : "Novo atleta"; ?></h2>

    <?php if (mysqli_num_rows($clubes) == 0): ?>
        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>É preciso existir pelo menos um clube antes de registares atletas. <a href="clubes.php">Cria um clube primeiro.</a></p>
        </div>
    <?php else: ?>

        <form method="post" action="atletas.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="acao" value="guardar">
            <input type="hidden" name="id" value="<?php echo $atleta_a_editar ? $atleta_a_editar['id'] : 0; ?>">

            <label for="name">Nome</label>
            <input type="text" id="name" name="name" required
                   value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['name']) : ''; ?>">

            <div class="form-row">
                <div>
                    <label for="birth_date">Data de nascimento</label>
                    <input type="date" id="birth_date" name="birth_date" required
                           value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['birth_date']) : ''; ?>">
                </div>
                <div>
                    <label for="gender">Género</label>
                    <select id="gender" name="gender" required>
                        <option value="">-- Escolhe --</option>
                        <?php foreach (generos() as $codigo => $rotulo): ?>
                            <option value="<?php echo $codigo; ?>"
                                <?php echo ($atleta_a_editar && $atleta_a_editar["gender"] == $codigo) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($rotulo); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label for="place_of_birth">Naturalidade</label>
            <input type="text" id="place_of_birth" name="place_of_birth" required
                   value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['place_of_birth']) : ''; ?>">

            <div class="form-row">
                <div>
                    <label for="phone_number">Telefone</label>
                    <input type="tel" id="phone_number" name="phone_number" required autocomplete="off"
                           value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['phone_number']) : ''; ?>">
                </div>
                <div>
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['email']) : ''; ?>">
                </div>
            </div>

            <label for="photo">Foto (URL) <span class="label-optional">(opcional)</span></label>
            <input type="text" id="photo" name="photo" placeholder="https://" inputmode="url"
                   value="<?php echo $atleta_a_editar ? htmlspecialchars($atleta_a_editar['photo'] ?? "") : ''; ?>">

            <label for="club_id">Clube</label>
            <select id="club_id" name="club_id" required>
                <option value="">-- Escolhe um clube --</option>
                <?php while ($clube = mysqli_fetch_assoc($clubes)): ?>
                    <option value="<?php echo $clube['id']; ?>"
                        <?php echo ($atleta_a_editar && $atleta_a_editar["club_id"] == $clube["id"]) ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($clube["name"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <p class="hint">
                O escalão já não se escolhe aqui: é calculado automaticamente a partir da
                data de nascimento, usando os intervalos de idade definidos em
                <a href="escaloes.php">Gerir escalões</a>.
                <?php if ($atleta_a_editar): ?>
                    <br>Escalão atual: <strong><?php echo htmlspecialchars($atleta_a_editar["category_name"] ?? "nenhum (idade fora de todos os escalões configurados)"); ?></strong>
                <?php endif; ?>
            </p>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?php echo $atleta_a_editar ? "Guardar alterações" : "Criar atleta"; ?>
                </button>

                <?php if ($atleta_a_editar): ?>
                    <a class="btn" href="atletas.php">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>

    <?php endif; ?>
</section>

<section class="competitions-list">
    <h2>Atletas existentes<span class="count-pill"><?php echo mysqli_num_rows($resultado_lista); ?></span></h2>

    <?php if (mysqli_num_rows($resultado_lista) == 0): ?>

        <div class="empty-state">
            <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
            <p>Ainda não existem atletas registados.</p>
        </div>

    <?php else: ?>

        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Clube</th>
                        <th scope="col">Escalão</th>
                        <th scope="col">Género</th>
                        <th scope="col">Nascimento</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($atleta = mysqli_fetch_assoc($resultado_lista)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($atleta["name"]); ?></td>
                            <td class="cell-muted" data-label="Clube"><?php echo htmlspecialchars($atleta["club_name"] ?? "-"); ?></td>
                            <td data-label="Escalão"><?php echo htmlspecialchars($atleta["category_name"] ?? "-"); ?></td>
                            <td data-label="Género"><?php echo htmlspecialchars(generoLabel($atleta["gender"])); ?></td>
                            <td class="num" data-label="Nascimento"><?php echo formatarData($atleta["birth_date"]); ?></td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <a class="btn btn-small" href="atletas.php?action=editar&id=<?php echo $atleta['id']; ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</a>

                                    <form method="post" action="atletas.php" class="inline-form" data-confirm="Remover este atleta? Esta ação não pode ser desfeita.">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="acao" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $atleta['id']; ?>">
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
