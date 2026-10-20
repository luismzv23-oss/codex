<?= $this->extend('layouts/login') ?>
<?= $this->section('content') ?>
<?php
$loginErrors = (array) (session()->getFlashdata('errors') ?? []);
$loginError = session()->getFlashdata('error');
$loginMessage = session()->getFlashdata('message');
?>
<header class="login-header wrap">
    <a class="brand" href="<?= esc(site_url('/')) ?>" aria-label="Codex ERP, volver al inicio"><span class="brand-symbol" aria-hidden="true">C<span>↗</span></span><span>codex<span class="brand-erp">ERP</span></span></a>
    <a class="login-back" href="<?= esc(site_url('/')) ?>"><span aria-hidden="true">←</span> Volver al inicio</a>
</header>
<main class="login-main wrap">
    <section class="login-story" aria-labelledby="welcome-title">
        <p class="eyebrow"><span class="tiny-dot" aria-hidden="true"></span> UNA VISIÓN MÁS CLARA DE TU NEGOCIO</p>
        <h1 id="welcome-title">Tu empresa<br> conectada<br> <span class="gradient-text">empieza acá.</span></h1>
        <p class="login-intro">Cada día, una oportunidad para avanzar. Reuní la gestión de tu empresa en un mismo lugar y enfocá tu energía en lo que viene.</p>
        <div class="login-scene" aria-hidden="true">
            <div class="login-orbit orbit-a"></div><div class="login-orbit orbit-b"></div>
            <svg class="login-connections" viewBox="0 0 480 200" fill="none"><path d="M240 100L70 50M240 100L400 45M240 100L90 155M240 100L390 155" stroke="currentColor" stroke-dasharray="4 6"/><circle cx="240" cy="100" r="69" stroke="currentColor"/></svg>
            <div class="login-core"><span>C<span>↗</span></span><strong>codex</strong><small>TU GESTIÓN, CONECTADA</small></div>
            <span class="login-node login-node-sales"><i>↗</i> Ventas</span><span class="login-node login-node-stock"><i>◇</i> Inventario</span><span class="login-node login-node-buy"><i>⌑</i> Compras</span><span class="login-node login-node-cash"><i>◎</i> Caja y cobranzas</span>
        </div>
        <div class="login-story-foot"><span aria-hidden="true">✧</span><p>Más orden para tu presente.<br><strong>Más perspectiva para tu próximo paso.</strong></p></div>
    </section>
    <section class="login-panel" aria-labelledby="form-title">
        <div class="login-panel-top"><span class="eyebrow">TU ESPACIO DE TRABAJO</span><span class="login-panel-mark" aria-hidden="true">↗</span></div>
        <h2 id="form-title">Qué bueno verte<br>de nuevo.</h2>
        <p class="login-panel-description">Ingresá con tu usuario o correo para continuar.</p>
        <?php if ($loginMessage): ?><div class="login-notice notice-success" role="status"><?= esc($loginMessage) ?></div><?php endif; ?>
        <?php if ($loginError || $loginErrors): ?>
            <div class="login-notice notice-error" role="alert" id="login-errors"><strong>No pudimos iniciar sesión.</strong><?php if ($loginError): ?><p><?= esc($loginError) ?></p><?php endif; ?><?php if ($loginErrors): ?><ul><?php foreach ($loginErrors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
        <?php endif; ?>
        <form id="login-form" class="login-form" method="post" action="<?= esc(site_url('login')) ?>" x-on:submit="submit" x-bind:aria-busy="submitting" <?php if ($loginError || $loginErrors): ?>aria-describedby="login-errors"<?php endif; ?>>
            <?= csrf_field() ?>
            <div class="login-field"><label for="login-identity">Usuario o correo</label><div class="input-shell"><span class="field-symbol" aria-hidden="true">@</span><input id="login-identity" type="text" name="login" value="<?= esc(old('login')) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required <?php if (isset($loginErrors['login'])): ?>aria-invalid="true" aria-describedby="login-errors"<?php endif; ?> placeholder="Tu usuario o correo"></div></div>
            <div class="login-field"><label for="login-password">Contraseña</label><div class="input-shell password-shell"><svg class="field-symbol lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg><input id="login-password" type="password" x-bind:type="passwordType" name="password" autocomplete="current-password" required <?php if (isset($loginErrors['password'])): ?>aria-invalid="true" aria-describedby="login-errors"<?php endif; ?> placeholder="Tu contraseña"><button type="button" class="password-toggle" x-cloak x-on:click="togglePassword" x-bind:aria-label="passwordLabel" x-bind:aria-pressed="passwordVisible" aria-controls="login-password"><span x-text="passwordAction">Mostrar</span></button></div></div>
            <div class="login-recovery"><a href="<?= esc(site_url('forgot-password')) ?>">¿Olvidaste tu contraseña?</a></div>
            <button type="submit" class="button button-primary login-submit" x-bind:disabled="submitting"><span x-text="submitLabel" aria-live="polite">Ingresar al sistema</span><span class="submit-arrow" aria-hidden="true" x-show="!submitting">↗</span><span class="submit-spinner" aria-hidden="true" x-cloak x-show="submitting"></span></button>
        </form>
        <p class="login-panel-foot"><span aria-hidden="true">◇</span> Ventas, inventario y finanzas. Una misma visión.</p>
    </section>
</main>
<footer class="login-footer wrap"><span>CODEX ERP</span><p>Una empresa más conectada. Un futuro con más posibilidades.</p><span class="login-footer-detail" aria-hidden="true">GESTIÓN / CONEXIÓN / CRECIMIENTO</span></footer>
<?= $this->endSection() ?>
