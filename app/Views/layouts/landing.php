<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Conectá ventas, inventario, compras, caja y cobranzas con Codex ERP. Información centralizada para acompañar el crecimiento de tu empresa.">
    <meta name="theme-color" content="#080b15">
    <title><?= esc($pageTitle ?? 'Codex ERP') ?></title>
    <link rel="icon" href="<?= esc(base_url('favicon.ico')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/landing.css')) ?>?v=1">
    <script defer src="<?= esc(base_url('assets/js/landing.js')) ?>?v=1"></script>
    <script defer src="<?= esc(base_url('assets/vendor/alpinejs/csp-3.17.4.min.js')) ?>"></script>
</head>
<body class="codex-landing" x-data="codexLanding" x-bind:class="motionClass" x-on:keydown.escape.window="closeMenu" x-on:resize.window.debounce.150ms="resize" >
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?= $this->renderSection('content') ?>
</body>
</html>
