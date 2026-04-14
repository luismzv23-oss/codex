<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<main id="app" class="landing-shell">
    <div class="container py-5">
        <div class="row min-vh-100 align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow">CodeIgniter 4 + API interna</span>
                <h1 class="display-3 fw-bold mt-3">Proyecto base de Codex listo para operar.</h1>
                <p class="lead text-secondary mt-4">
                    La portada principal se mantiene activa con verificacion de API, y el sistema ya
                    incorpora autenticacion, usuarios, multiempresa y configuracion central tanto en web como en API.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <span class="badge text-bg-light">CodeIgniter 4.4</span>
                    <span class="badge text-bg-light">PHP 8.0+</span>
                    <span class="badge text-bg-light">MySQL</span>
                    <span class="badge text-bg-light">Bootstrap 5</span>
                    <span class="badge text-bg-light">RBAC</span>
                    <span class="badge text-bg-light">REST API</span>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="<?= esc($loginUrl) ?>" class="btn btn-dark btn-lg">Iniciar sesion</a>
                    <?php if ($isAuthenticated): ?>
                        <a href="<?= esc($dashboardUrl) ?>" class="btn btn-outline-dark btn-lg">Ir al dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <section class="status-card shadow-lg">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <p class="text-uppercase small text-secondary mb-2">Estado del sistema</p>
                            <h2 class="h3 mb-1">Core y API activos</h2>
                            <p class="text-secondary mb-0">Portada, autenticacion, RBAC y modulos Core disponibles.</p>
                        </div>
                        <span class="status-dot"></span>
                    </div>

                    <hr class="my-4">

                    <dl class="row mb-0 small">
                        <dt class="col-5 text-secondary">Proyecto</dt>
                        <dd class="col-7">codex</dd>
                        <dt class="col-5 text-secondary">Base URL</dt>
                        <dd class="col-7"><?= esc(site_url('/')) ?></dd>
                        <dt class="col-5 text-secondary">Login</dt>
                        <dd class="col-7"><?= esc($loginUrl) ?></dd>
                        <dt class="col-5 text-secondary">API health</dt>
                        <dd class="col-7"><?= esc($apiHealthUrl) ?></dd>
                    </dl>

                    <div class="api-console mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <strong>Verificacion API</strong>
                            <span class="small text-secondary">{{ api.statusLabel }}</span>
                        </div>
                        <pre class="mb-0">{{ api.payload }}</pre>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<?= $this->endSection() ?>
