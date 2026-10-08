<?php
// includes/head_assets.php
// Conteúdo comum do <head> (usado por header.php, login.php e setup_admin.php).
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f1b33">
    <meta name="description" content="Plataforma da Associação de Ginástica de São Vicente para gestão de competições, atletas e classificações.">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script>
        // Aplica o tema antes de a página ser desenhada (evita o "flash" de tema claro)
        (function () {
            var tema = null;
            try { tema = localStorage.getItem("agsv-theme"); } catch (e) {}
            if (!tema) tema = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            document.documentElement.setAttribute("data-theme", tema);
        })();
    </script>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>CSS/images/agsv_emblem.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>CSS/style.css?v=<?php echo filemtime(__DIR__ . "/../CSS/style.css"); ?>">
    <script src="<?php echo BASE_URL; ?>CSS/app.js?v=<?php echo filemtime(__DIR__ . "/../CSS/app.js"); ?>" defer></script>
