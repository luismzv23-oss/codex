<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
$q = '?company_id=' . urlencode($companyId);
$labels = ['open' => ['Contando', 'primary'], 'counted' => ['Por aprobar', 'warning'], 'approved' => ['Aprobado', 'success'], 'cancelled' => ['Anulado', 'secondary']];
$thisWeek = array_filter($counts, static fn($c) => $c['week'] === $week && $c['status'] !== 'cancelled');
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Control semanal de stock</h1>
        <p class="text-muted mb-0">Cada semana se cuenta una parte del catálogo (prioridad: alertas, más movimiento, más tiempo sin contar). Semana <?= esc($week) ?>.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-dark" href="<?= site_url('inventario/control-semanal/informe' . $q) ?>"><i class="bi bi-clipboard-data"></i> Informe semanal</a>
        <a class="btn btn-outline-dark" href="<?= site_url('inventario' . $q) ?>"><i class="bi bi-box-seam"></i> Inventario</a>
    </div>
</div>

<?php if ($alerts > 0): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <?= (int) $alerts ?> producto(s) con alertas (entradas sin comprobante, borradores cobrados o stock negativo) entrarán primero en el próximo control.</div>
<?php endif; ?>

<div class="card mb-4"><div class="card-body">
    <?php if ($thisWeek): ?>
        <?php $current = reset($thisWeek); ?>
        <p class="mb-2">Ya hay un control esta semana: <strong><?= esc($current['count_number']) ?></strong> (<?= esc($labels[$current['status']][0] ?? $current['status']) ?>).</p>
        <a class="btn btn-primary" href="<?= site_url('inventario/control-semanal/' . $current['id'] . $q) ?>">Continuar</a>
    <?php else: ?>
        <form method="post" action="<?= site_url('inventario/control-semanal' . $q) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-5"><label class="form-label">Depósito a contar</label>
                <select name="warehouse_id" class="form-select" required>
                    <?php foreach ($warehouses as $w): ?><option value="<?= esc($w['id']) ?>"><?= esc($w['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-4"><button class="btn btn-primary" <?= $warehouses ? '' : 'disabled' ?>><i class="bi bi-play-circle"></i> Iniciar control de la semana</button></div>
        </form>
    <?php endif; ?>
</div></div>

<h2 class="h5">Historial</h2>
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Control</th><th>Semana</th><th>Depósito</th><th>Estado</th><th class="text-end">Productos</th><th class="text-end">Diferencia $</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($counts as $c): [$label, $color] = $labels[$c['status']] ?? [$c['status'], 'secondary']; ?>
        <tr>
            <td><?= esc($c['count_number']) ?></td><td><?= esc($c['week']) ?></td><td><?= esc($c['warehouse_name'] ?? '') ?></td>
            <td><span class="badge bg-<?= $color ?>"><?= esc($label) ?></span></td>
            <td class="text-end"><?= (int) $c['items_count'] ?></td>
            <td class="text-end <?= (float) $c['difference_value'] < 0 ? 'text-danger' : '' ?>"><?= number_format((float) $c['difference_value'], 2, ',', '.') ?></td>
            <td class="text-end"><a href="<?= site_url('inventario/control-semanal/' . $c['id'] . $q) ?>" class="btn btn-sm btn-outline-dark">Ver</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $counts): ?><tr><td colspan="7" class="text-muted text-center">Todavía no hay controles.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?= $this->endSection() ?>
