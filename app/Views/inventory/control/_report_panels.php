<?php
$q = '?company_id=' . urlencode($companyId);
$money = static fn($v) => number_format((float) $v, 2, ',', '.');
$qty = static fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$kardex = static fn($productId) => site_url('inventario/kardex?' . http_build_query(['company_id' => $companyId, 'product_id' => $productId]));
$cov = $report['coverage'];
$sum = $report['summary'];
$statusLabels = ['open' => ['Contando', 'info'], 'counted' => ['Por aprobar', 'warning'], 'approved' => ['Aprobado', 'success']];
$kpis = [
    ['Cobertura del ciclo', $cov['percent'] . '%', $cov['counted_28_days'] . ' de ' . $cov['catalog'] . ' productos en 4 semanas', '#report-coverage', true, $cov['percent'] < 75],
    ['Entradas sin comprobante', count($report['manual_entries']), $qty($sum['manual_units']) . ' unidades ingresadas a mano', '#report-manual', false, count($report['manual_entries']) > 0],
    ['Borradores cobrados', count($report['paid_drafts']), '$ ' . $money($sum['paid_total']) . ' cobrados sin descontar stock', '#report-drafts', false, count($report['paid_drafts']) > 0],
    ['Sin rotación', count($report['no_rotation']), '$ ' . $money($sum['no_rotation_value']) . ' inmovilizados · ' . (int) $report['no_rotation_days'] . ' días', '#report-rotation', false, false],
    ['Stock negativo', count($report['negative_stock']), $qty($sum['negative_units']) . ' unidades por debajo de cero', '#report-negative', false, count($report['negative_stock']) > 0],
    ['Último control', $sum['last_count'] ? '$ ' . $money($sum['last_difference']) : '—', $sum['last_count'] ? 'Diferencia aprobada en ' . $sum['last_count'] : 'Todavía no hay controles aprobados', '#report-counts', false, ($sum['last_difference'] ?? 0) < 0],
];
$empty = static fn($icon, $title, $text) => '<div class="insight-empty"><i class="bi bi-' . $icon . '"></i><strong>' . esc($title) . '</strong><span>' . esc($text) . '</span></div>';
?>
<div class="insight-section-label"><span>RESUMEN · ÚLTIMOS <?= (int) $report['days'] ?> DÍAS</span><span>Generado <?= esc(date('d/m/Y H:i', strtotime($report['generated_at']))) ?></span></div>
<div class="insight-kpis">
    <?php foreach ($kpis as [$label, $value, $hint, $target, $primary, $alert]): ?>
        <a class="insight-kpi <?= $primary ? 'insight-kpi-primary' : '' ?> <?= $alert ? 'insight-kpi-alert' : '' ?>" href="<?= $target ?>">
            <div class="insight-kpi-label"><?= esc($label) ?><i class="bi bi-arrow-up-right" aria-hidden="true"></i></div>
            <strong><?= esc((string) $value) ?></strong>
            <span><?= esc($hint) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="insight-analysis-grid mb-4">
    <section class="insight-panel" id="report-coverage">
        <div class="insight-panel-heading"><div><span class="insight-overline">CICLO DE CONTEO</span><h2>¿Cuánto del catálogo se controló?</h2><p>Meta: contar todo el catálogo al menos una vez cada 4 semanas.</p></div><span class="insight-tag"><?= (int) $cov['percent'] ?>%</span></div>
        <div class="progress mb-2" style="height:12px" role="progressbar" aria-valuenow="<?= (int) $cov['percent'] ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar <?= $cov['percent'] < 75 ? 'bg-warning' : 'bg-success' ?>" style="width:<?= min(100, (int) $cov['percent']) ?>%"></div></div>
        <p class="small text-secondary mb-3"><?= (int) $cov['counted_28_days'] ?> de <?= (int) $cov['catalog'] ?> productos físicos contados y aprobados en los últimos 28 días.</p>
        <div id="report-counts">
        <?php if (! $report['counts']): ?><?= $empty('clipboard-check', 'Sin controles todavía', 'Inicia el control de la semana desde Control semanal.') ?><?php else: ?>
            <div class="table-responsive"><table class="table align-middle mb-0" data-codex-pagination="5">
                <thead><tr><th>Control</th><th>Semana</th><th>Estado</th><th>Productos</th><th>Diferencia $</th><th></th></tr></thead>
                <tbody><?php foreach ($report['counts'] as $c): [$label, $color] = $statusLabels[$c['status']] ?? [$c['status'], 'secondary']; ?>
                    <tr><td><a href="<?= site_url('inventario/control-semanal/' . $c['id'] . $q) ?>"><?= esc($c['count_number']) ?> <i class="bi bi-arrow-up-right"></i></a><div class="small text-secondary"><?= esc($c['warehouse_name'] ?? '') ?></div></td>
                        <td><?= esc($c['week']) ?></td><td><span class="badge text-bg-<?= $color ?>"><?= esc($label) ?></span></td><td><?= (int) $c['items_count'] ?></td>
                        <td class="<?= (float) $c['difference_value'] < 0 ? 'insight-danger' : '' ?>"><?= $money($c['difference_value']) ?></td><td class="text-end"><a href="<?= site_url('inventario/control-semanal/' . $c['id'] . '/productos?company_id=' . urlencode($companyId)) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Productos del control" data-popup-subtitle="<?= esc($c['count_number'] . ' · semana ' . $c['week']) ?>" title="Ver productos" aria-label="Ver productos"><i class="bi bi-list-check"></i></a></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
        </div>
    </section>

    <section class="insight-panel" id="report-manual">
        <div class="insight-panel-heading"><div><span class="insight-overline">ORIGEN DEL STOCK</span><h2>¿Qué entró sin comprobante?</h2><p>Ajustes manuales y stock cargado al editar o crear productos. Las compras deben ingresar por Recepción.</p></div><span class="insight-tag"><?= count($report['manual_entries']) ?> entradas</span></div>
        <?php if (! $report['manual_entries']): ?><?= $empty('check-circle', 'Sin entradas manuales', 'Todo el stock del período ingresó con comprobante.') ?><?php else: ?>
            <div class="table-responsive"><table class="table align-middle mb-0" data-codex-pagination="5">
                <thead><tr><th>Producto</th><th>Cantidad</th><th>Motivo</th><th>Usuario</th></tr></thead>
                <tbody><?php foreach ($report['manual_entries'] as $r): ?>
                    <tr><td><a href="<?= esc($kardex($r['product_id'])) ?>"><?= esc($r['name']) ?> <i class="bi bi-arrow-up-right"></i></a><div class="small text-secondary"><?= esc($r['sku']) ?> · <?= esc(date('d/m/Y H:i', strtotime($r['occurred_at']))) ?></div></td>
                        <td><?= $qty($r['quantity']) ?></td><td><?= esc($r['reason']) ?></td><td><?= esc($r['user_name'] ?? '—') ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>

<section class="insight-panel mb-4" id="report-drafts">
    <div class="insight-panel-heading"><div><span class="insight-overline">VENTAS SIN CERRAR</span><h2>¿Qué ventas cobradas no descontaron stock?</h2><p>Ventas en borrador con pagos registrados: la mercadería salió pero el sistema la sigue contando. Confírmalas o anúlalas.</p></div><span class="insight-tag"><?= count($report['paid_drafts']) ?> ventas · $ <?= $money($sum['paid_total']) ?></span></div>
    <?php if (! $report['paid_drafts']): ?><?= $empty('check-circle', 'Sin borradores cobrados', 'No hay ventas cobradas pendientes de confirmar en el período.') ?><?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" data-codex-pagination="5">
            <thead><tr><th>Venta</th><th>Cliente</th><th>Fecha</th><th>Cobrado $</th><th>Usuario</th></tr></thead>
            <tbody><?php foreach ($report['paid_drafts'] as $r): ?>
                <tr><td><a href="<?= site_url('ventas/' . $r['id'] . '/editar' . $q) ?>" data-popup="true" data-popup-title="Editar venta" data-popup-subtitle="Confirmar o corregir el borrador."><?= esc($r['sale_number']) ?> <i class="bi bi-arrow-up-right"></i></a></td>
                    <td><?= esc($r['customer'] ?: 'Consumidor final') ?></td><td><?= esc(date('d/m/Y', strtotime($r['issue_date']))) ?></td><td><strong><?= $money($r['paid']) ?></strong></td><td><?= esc($r['user_name'] ?? '—') ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<div class="insight-analysis-grid mb-4">
    <section class="insight-panel" id="report-rotation">
        <div class="insight-panel-heading"><div><span class="insight-overline">CAPITAL INMOVILIZADO</span><h2>¿Qué no se vende?</h2><p>Productos con stock y sin ventas en <?= (int) $report['no_rotation_days'] ?> días. Verifica que existan físicamente.</p></div><span class="insight-tag"><?= count($report['no_rotation']) ?> productos</span></div>
        <?php if (! $report['no_rotation']): ?><?= $empty('check-circle', 'Todo rota', 'Todos los productos con stock tuvieron ventas.') ?><?php else: ?>
            <div class="table-responsive"><table class="table align-middle mb-0" data-codex-pagination="5">
                <thead><tr><th>Producto</th><th>Stock</th><th>Valor $</th></tr></thead>
                <tbody><?php foreach ($report['no_rotation'] as $r): ?>
                    <tr><td><a href="<?= esc($kardex($r['product_id'])) ?>"><?= esc($r['name']) ?> <i class="bi bi-arrow-up-right"></i></a><div class="small text-secondary"><?= esc($r['sku']) ?></div></td>
                        <td><?= $qty($r['stock']) ?><div class="small text-secondary"><?= esc($r['unit'] ?? '') ?></div></td><td><?= $money($r['value']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="insight-panel" id="report-negative">
        <div class="insight-panel-heading"><div><span class="insight-overline">INCONSISTENCIAS</span><h2>¿Dónde hay stock negativo?</h2><p>Suele indicar compras sin recepcionar o ventas duplicadas. Corrígelo con el control semanal.</p></div><span class="insight-tag"><?= count($report['negative_stock']) ?> productos</span></div>
        <?php if (! $report['negative_stock']): ?><?= $empty('check-circle', 'Sin stock negativo', 'Ningún producto tiene existencias por debajo de cero.') ?><?php else: ?>
            <div class="table-responsive"><table class="table align-middle mb-0" data-codex-pagination="5">
                <thead><tr><th>Producto</th><th>Stock</th><th>Valor $</th></tr></thead>
                <tbody><?php foreach ($report['negative_stock'] as $r): ?>
                    <tr><td><a href="<?= esc($kardex($r['product_id'])) ?>"><?= esc($r['name']) ?> <i class="bi bi-arrow-up-right"></i></a><div class="small text-secondary"><?= esc($r['sku']) ?></div></td>
                        <td class="insight-danger"><?= $qty($r['stock']) ?></td><td><?= $money($r['value']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>
