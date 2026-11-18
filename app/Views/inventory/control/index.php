<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/kardex-dashboard.css') ?>">
<?php
$q = '?company_id=' . urlencode($companyId);
$labels = ['open' => ['Contando', 'info'], 'counted' => ['Por aprobar', 'warning'], 'approved' => ['Aprobado', 'success'], 'cancelled' => ['Anulado', 'secondary']];
$thisWeek = array_filter($counts, static fn($c) => $c['week'] === $week && in_array($c['status'], ['open', 'counted'], true));
$current = $thisWeek ? reset($thisWeek) : null;
?>
<div class="insight-shell inventory-shell kardex-shell">
<header class="insight-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / CONTROL DE STOCK</div>
        <h1>Control semanal de stock</h1>
        <p>Cada semana se cuenta una parte del catálogo: primero los productos con alertas, luego los de más movimiento y los que más tiempo llevan sin contarse.</p>
        <div class="insight-identity"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span><?= esc($company['name'] ?? '') ?> · Semana <?= esc($week) ?></span></div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= site_url('inventario/control-semanal/informe' . $q) ?>" class="btn btn-outline-dark icon-btn" title="Informe semanal" aria-label="Informe semanal"><i class="bi bi-clipboard-data"></i></a>
        <a href="<?= site_url('inventario' . $q) ?>" class="btn btn-outline-dark icon-btn" title="Volver a Inventario" aria-label="Volver a Inventario"><i class="bi bi-box-seam"></i></a>
    </div>
</header>
<?php if (! empty($companies)): ?>
<form method="get" action="<?= site_url('inventario/control-semanal') ?>" class="kardex-filter-card">
    <div class="insight-filters">
        <?php if (! empty($companies)): ?><label>Empresa activa<select name="company_id" class="form-select" onchange="this.form.submit()"><?php foreach ($companies as $co): ?><option value="<?= esc($co['id']) ?>" <?= $co['id'] === $companyId ? 'selected' : '' ?>><?= esc($co['name']) ?></option><?php endforeach; ?></select></label><?php else: ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
    </div>
</form>
<?php endif; ?>

<div class="insight-analysis-grid mb-4">
    <section class="insight-panel">
        <div class="insight-panel-heading"><div><span class="insight-overline">ESTA SEMANA</span><h2><?= $current ? 'Control ' . esc($current['count_number']) : '¿Qué depósito contamos?' ?></h2>
            <p><?= $current ? 'Estado: ' . esc($labels[$current['status']][0] ?? $current['status']) . ' · ' . (int) $current['items_count'] . ' productos.' : 'El sistema arma la lista de productos a contar al iniciar.' ?></p></div></div>
        <?php if ($current): ?>
            <a class="btn btn-dark" href="<?= site_url('inventario/control-semanal/' . $current['id'] . $q) ?>"><i class="bi bi-arrow-right-circle"></i> Continuar</a>
        <?php else: ?>
            <form method="post" action="<?= site_url('inventario/control-semanal' . $q) ?>" class="d-flex flex-wrap gap-2 align-items-end">
                <?= csrf_field() ?>
                <label class="flex-grow-1">Depósito a contar
                    <select name="warehouse_id" class="form-select" required>
                        <?php foreach ($warehouses as $w): ?><option value="<?= esc($w['id']) ?>"><?= esc($w['name']) ?></option><?php endforeach; ?>
                    </select></label>
                <button class="btn btn-dark" <?= $warehouses ? '' : 'disabled' ?>><i class="bi bi-play-circle"></i> Iniciar control</button>
            </form>
        <?php endif; ?>
    </section>
    <section class="insight-panel">
        <div class="insight-panel-heading"><div><span class="insight-overline">PRIORIDAD</span><h2>¿Qué entra primero?</h2><p>Productos con alertas: entradas sin comprobante, ventas en borrador cobradas o stock negativo.</p></div><span class="insight-tag"><?= (int) $alerts ?> alertas</span></div>
        <?php if ($alerts > 0): ?>
            <p class="mb-0"><strong><?= (int) $alerts ?></strong> producto(s) se incluirán al inicio del próximo control. <a href="<?= site_url('inventario/control-semanal/informe' . $q) ?>">Ver el informe <i class="bi bi-arrow-up-right"></i></a></p>
        <?php else: ?><div class="insight-empty"><i class="bi bi-check-circle"></i><strong>Sin alertas</strong><span>El control seguirá el ciclo normal.</span></div><?php endif; ?>
    </section>
</div>

<section class="insight-panel mb-4">
    <div class="insight-panel-heading"><div><span class="insight-overline">HISTORIAL</span><h2>¿Cómo vienen los controles?</h2><p>Controles realizados y diferencia valorizada al costo promedio.</p></div><span class="insight-tag"><?= count($counts) ?> controles</span></div>
    <?php if (! $counts): ?><div class="insight-empty"><i class="bi bi-clipboard-check"></i><strong>Todavía no hay controles</strong><span>Inicia el primero desde esta pantalla.</span></div><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0" data-codex-pagination="5">
        <thead><tr><th>Control</th><th>Semana</th><th>Estado</th><th>Productos</th><th>Diferencia $</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($counts as $c): [$label, $color] = $labels[$c['status']] ?? [$c['status'], 'secondary']; ?>
            <tr>
                <td><a href="<?= site_url('inventario/control-semanal/' . $c['id'] . $q) ?>"><?= esc($c['count_number']) ?> <i class="bi bi-arrow-up-right"></i></a><div class="small text-secondary"><?= esc($c['warehouse_name'] ?? '') ?></div></td>
                <td><?= esc($c['week']) ?></td>
                <td><span class="badge text-bg-<?= $color ?>"><?= esc($label) ?></span></td>
                <td><?= (int) $c['items_count'] ?></td>
                <td class="<?= (float) $c['difference_value'] < 0 ? 'insight-danger' : '' ?>"><?= number_format((float) $c['difference_value'], 2, ',', '.') ?></td>
                <td class="text-end"><a href="<?= site_url('inventario/control-semanal/' . $c['id'] . '/productos?company_id=' . urlencode($companyId)) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Productos del control" data-popup-subtitle="<?= esc($c['count_number'] . ' · semana ' . $c['week']) ?>" title="Ver productos" aria-label="Ver productos"><i class="bi bi-list-check"></i></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
</div>
<?= $this->endSection() ?>
