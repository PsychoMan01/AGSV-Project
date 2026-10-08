<?php
// includes/tabela_classificacao.php
// Tabela de classificação de um escalão. Espera $linhas_do_nivel e
// $aparelhos_geral (vindos de obterClassificacaoGeral()).
?>
<div class="table-scroll">
    <table class="data-table data-table--scores">
        <thead>
            <tr>
                <th scope="col">Posição</th>
                <th scope="col">Atleta</th>
                <?php foreach ($aparelhos_geral as $aparelho): ?>
                    <th scope="col" class="cell-num"><?php echo htmlspecialchars($aparelho["name"]); ?></th>
                <?php endforeach; ?>
                <th scope="col" class="cell-num">Pontuação final</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($linhas_do_nivel as $linha): ?>
                <?php $posicao = $linha["posicao"]; ?>
                <tr>
                    <td>
                        <span class="rank<?php echo ($posicao !== null && $posicao <= 3) ? " rank-" . (int) $posicao : ""; ?>">
                            <?php echo $posicao !== null ? (int) $posicao . "º" : "--"; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($linha["athlete_name"]); ?></td>
                    <?php foreach ($aparelhos_geral as $aparelho): ?>
                        <?php $nota = $linha["notas_por_aparelho"][$aparelho["id"]]; ?>
                        <td class="cell-num"><?php echo $nota !== null ? number_format($nota, 3, ",", ".") : "--"; ?></td>
                    <?php endforeach; ?>
                    <td class="cell-num cell-total"><?php echo $linha["ja_pontuado_algures"] ? number_format($linha["pontuacao_final"], 3, ",", ".") : "--"; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
