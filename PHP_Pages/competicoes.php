<?php
// PHP_Pages/competicoes.php
// Página pública: mostra a lista de todas as competições

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

// Ir buscar todas as competições, mais recentes primeiro
$sql = "SELECT id, name, location, start_date, end_date, status, tipo
        FROM competitions
        ORDER BY start_date DESC";

$resultado = mysqli_query($conn, $sql);

$pageTitle = "Competições - " . APP_NAME;
$pagina_atual = "competicoes_publico";
require __DIR__ . "/../includes/header.php";
?>

<section class="page-intro">
    <h1>Competições de Ginástica</h1>
    <p>Consulta as competições agendadas, em curso e as classificações em direto.</p>
</section>

<?php mostrarFlash(); ?>

<section class="competitions-list">
    <?php if (mysqli_num_rows($resultado) == 0): ?>

        <div class="empty-state">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <h3>Ainda não existem competições registadas</h3>
            <p>Quando a AGSV publicar uma competição, ela aparece nesta lista.</p>
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
                    <?php while ($comp = mysqli_fetch_assoc($resultado)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comp["name"]); ?></td>
                            <td class="cell-muted" data-label="Local"><?php echo htmlspecialchars($comp["location"] ?? ""); ?></td>
                            <td data-label="Tipo"><?php echo $comp["tipo"] == "INTERNA" ? "Interna" : "Regional"; ?></td>
                            <td class="num" data-label="Início"><?php echo formatarData($comp["start_date"]); ?></td>
                            <td class="num" data-label="Fim"><?php echo formatarData($comp["end_date"]); ?></td>
                            <td data-label="Estado">
                                <?php if ($comp["status"] == "A"): ?>
                                    <span class="badge badge-active">Ativa</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativa</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a class="btn btn-small" href="competicao.php?id=<?php echo (int) $comp['id']; ?>">Ver detalhes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</section>

<?php require __DIR__ . "/../includes/footer.php"; ?>
