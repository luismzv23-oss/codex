<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<div class="insight-shell" id="insight-dashboard">
    <header class="insight-hero">
        <div>
            <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / INTELIGENCIA DE NEGOCIO</div>
            <h1><?= $superadmin ? 'Una visión. Todas tus empresas.' : 'Tu negocio, en perspectiva.' ?></h1>
            <p><?= $superadmin ? 'Compara resultados y detecta dónde enfocar tu atención.' : 'Sigue tus ventas y anticipa lo que necesita tu operación.' ?></p>
            <div class="insight-identity"><i class="bi bi-person-circle" aria-hidden="true"></i> <?= esc($user['name'] ?? '') ?> <span>/ <?= $superadmin ? 'Superadmin · visión global' : 'Administrador · ' . esc($companies[0]['name'] ?? '') ?></span></div>
        </div>
        <div class="insight-hero-status"><span class="insight-live-dot"></span> Actualización cada 60 s<br><small id="insight-updated">Última lectura <?= esc($insights['updated']) ?></small></div>
    </header>
    <form id="insight-filters" class="insight-filters" action="<?= site_url('dashboard') ?>" method="get">
        <?php if ($superadmin): ?>
        <label>Empresa<select name="company_id" class="form-select"><option value="">Todas las empresas</option><?php foreach ($companies as $company): ?><option value="<?= esc($company['id']) ?>" <?= $filters['company_id'] === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <label>Período<select id="insight-period" class="form-select"><option value="custom">Personalizado</option><option value="today">Hoy</option><option value="7">Últimos 7 días</option><option value="30">Últimos 30 días</option><option value="month" selected>Este mes</option></select></label>
        <label>Desde<input class="form-control" type="date" name="from" value="<?= esc($filters['from']) ?>" required></label>
        <label>Hasta<input class="form-control" type="date" name="to" value="<?= esc($filters['to']) ?>" required></label>
        <label>Moneda<select name="currency" class="form-select"><?php foreach ($currencyOptions as $currency): ?><option <?= $currency === $filters['currency'] ? 'selected' : '' ?> value="<?= esc($currency) ?>"><?= esc($currency) ?></option><?php endforeach; ?></select></label>
        <div class="insight-filter-actions">
            <button class="btn insight-action" title="Aplicar filtros y actualizar" aria-label="Aplicar filtros y actualizar"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
            <button type="button" id="insight-pause" class="btn insight-action" title="Pausar actualización automática" aria-label="Pausar actualización automática" aria-pressed="false"><i class="bi bi-pause" aria-hidden="true"></i></button>
        </div>
    </form>
    <div id="insight-notice" role="status" aria-live="polite" class="insight-notice" hidden></div>
    <main id="insight-content" aria-label="Indicadores y análisis">
        <?= view('dashboard/insights_panel', compact('insights', 'filters', 'superadmin')) ?>
    </main>
    <footer class="insight-footer"><span><i class="bi bi-database-check" aria-hidden="true"></i> Datos de operaciones registradas · Importes en <?= esc($filters['currency']) ?>, sin conversión</span><a href="<?= site_url('dashboard/readiness') ?>" title="Diagnóstico del sistema" aria-label="Diagnóstico del sistema"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></a></footer>
</div>
<script src="<?= base_url('assets/js/dashboard-insights.js') ?>" defer></script>
<?= $this->endSection() ?>
