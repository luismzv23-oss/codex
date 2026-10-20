<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Ingresá a Codex ERP y conectá la gestión de tu empresa.">
    <meta name="theme-color" content="#080b15">
    <title>Iniciar sesión | Codex ERP</title>
    <link rel="icon" href="<?= esc(base_url('favicon.ico')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/landing.css')) ?>?v=1">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/login.css')) ?>?v=1">
    <script defer src="<?= esc(base_url('assets/js/login.js')) ?>?v=1"></script>
    <script defer src="<?= esc(base_url('assets/vendor/alpinejs/csp-3.17.4.min.js')) ?>"></script>
</head>
<body class="login-page" x-data="codexLogin" x-bind:class="motionClass" x-on:pageshow.window="resetSubmission">
    <a class="skip-link" href="#login-form">Saltar al formulario</a>
    <?= $this->renderSection('content') ?>
</body>
</html>
