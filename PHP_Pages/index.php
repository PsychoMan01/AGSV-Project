<?php
// PHP_Pages/index.php
// Página pública: mostra a lista de todas as competições

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Ir buscar todas as competições, mais recentes primeiro
$sql = "SELECT id, name, location, start_date, end_date, status, results_published
        FROM competitions
        ORDER BY start_date DESC";

$resultado = mysqli_query($conn, $sql);

$pageTitle = "Competições - " . APP_NAME;
require __DIR__ . "/../includes/header.php";
?>

<section class="page-intro">
    <h1>Competições de Ginástica</h1>
    <p>Consulta as competições agendadas, em curso e os resultados já publicados.</p>
</section>

<?php mostrarFlash(); ?>

<section class="competitions-list">
    <?php if (mysqli_num_rows($resultado) == 0): ?>

        <p>Ainda não existem competições registadas.</p>

    <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Local</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Estado</th>
                    <th>Resultados</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($comp = mysqli_fetch_assoc($resultado)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($comp["name"]); ?></td>
                        <td><?php echo htmlspecialchars($comp["location"]); ?></td>
                        <td><?php echo formatarData($comp["start_date"]); ?></td>
                        <td><?php echo formatarData($comp["end_date"]); ?></td>
                        <td>
                            <?php if ($comp["status"] == "A"): ?>
                                <span class="badge badge-active">Ativa</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Inativa</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($comp["results_published"] == 1): ?>
                                <span class="badge badge-active">Publicados</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Por publicar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-small" href="competicao.php?id=<?php echo $comp['id']; ?>">Ver detalhes</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php endif; ?>
</section>

<?php require __DIR__ . "/../includes/footer.php"; ?>
