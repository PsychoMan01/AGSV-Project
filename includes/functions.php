<?php
// includes/functions.php
// Pequenas funções auxiliares que vamos usar em várias páginas

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
        echo "<div class='alert alert-" . $tipo . "'>" . $mensagem . "</div>";

        unset($_SESSION["flash_tipo"]);
        unset($_SESSION["flash_mensagem"]);
    }
}

// Transformar uma data "2026-08-07" em "07/08/2026"
function formatarData($data) {
    if (empty($data)) {
        return "-";
    }
    $partes = explode("-", $data); // ["2026", "08", "07"]
    if (count($partes) != 3) {
        return $data;
    }
    return $partes[2] . "/" . $partes[1] . "/" . $partes[0];
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

// Recalcula a tabela "rankings" de uma competição a partir das pontuações (scores) já submetidas.
//
// Como agora cada juiz só pontua os campos do seu próprio papel (A, E, DB ou DA),
// a nota final de um atleta já não vem de uma única linha de "scores" — ela é
// construída juntando a parte de cada papel:
//
//   nota final = média(DB) + média(DA) + média(A) + média(E) - média(penal)
//
// Onde cada "média" é a média desse papel entre os juízes desse papel que já
// pontuaram o atleta (se não houver juiz desse papel na competição, essa parte
// vale simplesmente 0, para não travar o cálculo).
function calcularClassificacoes($conn, $competition_id) {

    // Limpar as classificações anteriores desta competição, para recalcular tudo de novo
    $sql_del = "DELETE FROM rankings WHERE competition_id = ?";
    $stmt_del = mysqli_prepare($conn, $sql_del);
    mysqli_stmt_bind_param($stmt_del, "i", $competition_id);
    mysqli_stmt_execute($stmt_del);

    // Para cada atleta/escalão/aparelho, calcular a média de cada papel separadamente
    $sql = "SELECT ee.category_id, ee.equipment_id, ee.athlete_id,
                   AVG(CASE WHEN s.papel = 'DB' THEN s.DB1 + s.DB2 END) AS media_db,
                   AVG(CASE WHEN s.papel = 'DA' THEN s.DA1 + s.DA2 END) AS media_da,
                   AVG(CASE WHEN s.papel = 'A'  THEN s.A1 + s.A2 + s.A3 + s.A4 END) AS media_a,
                   AVG(CASE WHEN s.papel = 'E'  THEN s.E1 + s.E2 + s.E3 + s.E4 END) AS media_e,
                   AVG(CASE WHEN s.papel = 'E'  THEN s.penal END) AS media_penal
            FROM event_entries ee
            JOIN scores s ON s.event_entry_id = ee.id AND s.status = 'A'
            WHERE ee.competition_id = ?
            GROUP BY ee.category_id, ee.equipment_id, ee.athlete_id";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $competition_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    // Juntar as notas finais numa lista PHP (para depois ordenar por escalão/aparelho)
    $classificacoes = array();

    while ($linha = mysqli_fetch_assoc($resultado)) {

        $media_db    = $linha["media_db"]    !== null ? (float) $linha["media_db"]    : 0;
        $media_da    = $linha["media_da"]    !== null ? (float) $linha["media_da"]    : 0;
        $media_a     = $linha["media_a"]     !== null ? (float) $linha["media_a"]     : 0;
        $media_e     = $linha["media_e"]     !== null ? (float) $linha["media_e"]     : 0;
        $media_penal = $linha["media_penal"] !== null ? (float) $linha["media_penal"] : 0;

        $nota_final = round($media_db + $media_da + $media_a + $media_e - $media_penal, 3);

        $classificacoes[] = array(
            "category_id"  => $linha["category_id"],
            "equipment_id" => $linha["equipment_id"],
            "athlete_id"   => $linha["athlete_id"],
            "nota_final"   => $nota_final,
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

    $sql_insert = "INSERT INTO rankings (competition_id, category_id, equipment_id, athlete_id, final_score, position)
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);

    $grupo_atual = null;
    $posicao = 0;

    foreach ($classificacoes as $linha) {

        // Sempre que mudamos de escalão/aparelho, a contagem de posições recomeça no 1
        $chave_grupo = $linha["category_id"] . "-" . $linha["equipment_id"];
        if ($chave_grupo != $grupo_atual) {
            $grupo_atual = $chave_grupo;
            $posicao = 0;
        }
        $posicao++;

        mysqli_stmt_bind_param(
            $stmt_insert, "iiiidi",
            $competition_id, $linha["category_id"], $linha["equipment_id"], $linha["athlete_id"],
            $linha["nota_final"], $posicao
        );
        mysqli_stmt_execute($stmt_insert);
    }
}
