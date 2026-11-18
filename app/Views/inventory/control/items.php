<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<?php
$blind = $count['status'] === 'open';
$items = $count['items'];
$counted = array_filter($items, static fn($i) => $i['counted_at'] !== null);
$money = static fn($v) => number_format((float) $v, 2, ',', '.');
$qty = static fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$short = array_filter($counted, static fn($i) => (float) $i['difference'] < 0);
$excess = array_filter($counted, static fn($i) => (float) $i['difference'] > 0);
$sumValue = static fn($rows) => array_sum(array_map(static fn($i) => (float) $i['difference_value'], $rows));
$statusLabels = ['open' => ['Contando', 'info'], 'counted' => ['Por aprobar', 'warning'], 'approved' => ['Aprobado', 'success'], 'cancelled' => ['Anulado', 'secondary']];
[$statusLabel, $statusColor] = $statusLabels[$count['status']] ?? [$count['status'], 'secondary'];
$reasons = ['alerta' => 'Alerta', 'movimiento' => 'Movimiento', 'rotacion' => 'Ciclo', 'agregado' => 'Agregado'];
?>
<div class="insight-panel">
    <div class="insight-panel-heading">
        <div><span class="insight-overline"><?= esc($count['warehouse_name'] ?? '') ?> · SEMANA <?= esc($count['week']) ?></span>
            <h2><?= esc($count['count_number']) ?> <span class="badge text-bg-<?= $statusColor ?> align-middle fs-6"><?= esc($statusLabel) ?></span></h2>
            <p><?= count($counted) ?> de <?= count($items) ?> productos contados<?= $blind ? ' · conteo a ciegas en curso: el stock del sistema se muestra al cerrar.' : ' · diferencia total $ ' . $money($count['difference_value']) ?></p></div>
        <a class="btn btn-sm btn-outline-dark" href="<?= site_url('inventario/control-semanal/' . $count['id'] . '?company_id=' . urlencode($companyId)) ?>" target="_top"><i class="bi bi-box-arrow-up-right"></i> Abrir control</a>
    </div>

    <?php if (! $blind): ?>
    <div class="row g-2 mb-3 small">
        <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="text-secondary">Con faltante</div><strong class="insight-danger"><?= count($short) ?> · $ <?= $money($sumValue($short)) ?></strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="text-secondary">Con sobrante</div><strong class="text-success"><?= count($excess) ?> · $ <?= $money($sumValue($excess)) ?></strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="text-secondary">Sin diferencia</div><strong><?= count($counted) - count($short) - count($excess) ?></strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="text-secondary">Ajustados</div><strong><?= count(array_filter($items, static fn($i) => ! empty($i['movement_id']))) ?></strong></div></div>
    </div>
    <?php endif; ?>

    <?php if (! $items): ?><div class="insight-empty"><i class="bi bi-inbox"></i><strong>Sin productos</strong><span>Este control no tiene productos.</span></div><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0" data-codex-pagination="5">
        <thead><tr><th>Producto</th><th>Motivo</th><?php if (! $blind): ?><th>Sistema</th><?php endif; ?><th>Contado</th><?php if (! $blind): ?><th>Diferencia</th><th>Valor $</th><th>Causa</th><?php endif; ?><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i):
            $isCounted = $i['counted_at'] !== null; $diff = (float) $i['difference'];
            if (! $isCounted) { $badge = ['Sin contar', 'light text-dark']; }
            elseif ($blind) { $badge = ['Contado', 'info']; }
            elseif (! empty($i['movement_id'])) { $badge = ['Ajustado', 'success']; }
            elseif (abs($diff) < 0.005) { $badge = ['Sin diferencia', 'light text-dark']; }
            elseif ($count['status'] === 'approved') { $badge = ['No aprobado', 'secondary']; }
            else { $badge = ['Pendiente', 'warning']; } ?>
            <tr>
                <td><?= esc($i['name']) ?><div class="small text-secondary"><?= esc($i['sku']) ?> · <?= esc($i['unit'] ?? '') ?></div></td>
                <td><span class="badge text-bg-light"><?= esc($reasons[$i['selection_reason']] ?? $i['selection_reason']) ?></span></td>
                <?php if (! $blind): ?><td><?= $isCounted ? $qty($i['system_quantity']) : '—' ?></td><?php endif; ?>
                <td><?= $isCounted ? $qty($i['counted_quantity']) : '—' ?></td>
                <?php if (! $blind): ?>
                    <td class="<?= $diff < 0 ? 'insight-danger' : ($diff > 0 ? 'text-success fw-semibold' : '') ?>"><?= $isCounted ? $qty($diff) : '—' ?></td>
                    <td><?= $isCounted ? $money($i['difference_value']) : '—' ?></td>
                    <td><?= esc($i['reason'] ?? '') ?: '—' ?></td>
                <?php endif; ?>
                <td><span class="badge text-bg-<?= $badge[1] ?>"><?= esc($badge[0]) ?></span><?php if ((int) $i['moved_after_count']): ?> <span class="badge text-bg-warning" title="Tuvo movimientos después de contarse">movido luego</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
