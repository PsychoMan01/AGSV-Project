<?php
// includes/escolher_competicao.php
// Ecrã comum "escolhe a competição" das páginas que trabalham sobre uma
// competição (atribuições, inscrições, notas, escalões da competição).
// Espera: $resultado_competicoes, $titulo_pagina, $descricao_pagina,
// $pagina_destino (ex: "notas.php") e $rotulo_acao (ex: "Ver notas").
?>
<section class="page-intro">
    <a class="back-link" href="painel.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    <h1><?php echo htmlspecialchars($titulo_pagina); ?></h1>
    <p><?php echo htmlspecialchars($descricao_pagina); ?></p>
</section>

<?php mostrarFlash(); ?>

<section class="competitions-list">
    <?php if (mysqli_num_rows($resultado_competicoes) == 0): ?>
        <div class="empty-state">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <h3>Ainda não existem competições registadas</h3>
            <p>É preciso criar uma competição antes de continuar.</p>
            <a class="btn btn-primary" href="competicoes.php"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Criar competição</a>
        </div>
    <?php else: ?>
        <h2>Escolhe a competição<span class="count-pill"><?php echo mysqli_num_rows($resultado_competicoes); ?></span></h2>
        <div class="table-scroll">
            <table class="data-table data-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Início</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($comp = mysqli_fetch_assoc($resultado_competicoes)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comp["name"]); ?></td>
                            <td class="num" data-label="Início"><?php echo formatarData($comp["start_date"]); ?></td>
                            <td data-label="Estado"><span class="badge <?php echo $comp["status"] == "A" ? "badge-active" : "badge-inactive"; ?>"><?php echo $comp["status"] == "A" ? "Ativa" : "Inativa"; ?></span></td>
                            <td class="actions-cell">
                                <div class="actions-wrap">
                                    <a class="btn btn-small" href="<?php echo $pagina_destino; ?>?competition_id=<?php echo (int) $comp["id"]; ?>">
                                        <?php echo htmlspecialchars($rotulo_acao); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
