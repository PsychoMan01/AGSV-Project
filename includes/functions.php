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
