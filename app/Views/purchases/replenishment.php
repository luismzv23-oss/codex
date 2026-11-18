<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$fmt = static fn($v, int $d = 0): string => number_format((float) $v, $d, ',', '.');
$qty = static fn($v): string => number_format((float) $v, floor((float) $v) == (float) $v ? 0 : 1, ',', '.');
$money = static fn($v): string => '$ ' . number_format((float) $v, 0, ',', '.');
$companyQuery = ! empty($companies) ? ['company_id' => $companyId] : [];
$summary = $run['summary'] ?? [];
$rows = $run['rows'] ?? [];
$reorderRows = array_values(array_filter($rows, static fn(array $r): bool => $r['status'] === 'reponer'));
$excessRows = array_values(array_filter($rows, static fn(array $r): bool => $r['status'] === 'inmovilizado' || $r['excess_value'] > 0));
usort($excessRows, static fn(array $a, array $b): int => max($b['excess_value'], $b['status'] === 'inmovilizado' ? $b['stock_value'] : 0) <=> max($a['excess_value'], $a['status'] === 'inmovilizado' ? $a['stock_value'] : 0));
$priorityLabels = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media'];
$methodLabels = ['holt' => 'Holt (tendencia)', 'croston' => 'Croston (intermitente)', 'promedio' => 'Promedio', 'sin_demanda' => 'Sin demanda'];
$leadLabels = ['observado' => 'observado', 'producto' => 'ficha', 'proveedor' => 'proveedor', 'defecto' => 'por defecto'];
$spark = static function (array $series): string {
    if (count($series) < 2 || max($series) <= 0) {
        return '';
    }
    $max = max($series);
    $step = 64 / (count($series) - 1);
    $points = [];
    foreach (array_values($series) as $i => $v) {
        $points[] = round($i * $step, 1) . ',' . round(18 - ($v / $max) * 16, 1);
    }

    return '<svg class="repl-spark" viewBox="0 0 64 20" aria-hidden="true"><polyline points="' . implode(' ', $points) . '"/></svg>';
};
?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/purchase-replenishment.css') . '?v=' . filemtime(FCPATH . 'assets/css/purchase-replenishment.css') ?>">
<div class="insight-shell repl-shell" id="replenishment"
     data-ai-url="<?= $run ? esc(site_url('compras/reposicion/' . $run['id'] . '/ia' . ($companyQuery ? '?' . http_build_query($companyQuery) : '')), 'attr') : '' ?>"
     data-csrf-name="<?= esc(csrf_token(), 'attr') ?>" data-csrf-hash="<?= esc(csrf_hash(), 'attr') ?>">
<header class="insight-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / COMPRAS / INTELIGENCIA</div>
        <h1>Reposición inteligente</h1>
        <p>Pronóstico de demanda, stock de seguridad y clasificación ABC/XYZ para comprar a tiempo, en la cantidad justa y sin inmovilizar capital.</p>
        <div class="insight-identity"><i class="bi bi-cpu" aria-hidden="true"></i><span><?= esc($context['company']['name']) ?><?= $run ? ' · Análisis del ' . esc(date('d/m/Y H:i', strtotime($run['created_at']))) : '' ?></span></div>
    </div>
    <nav class="purchases-hero-actions d-flex gap-2" aria-label="Navegación">
        <a class="btn btn-outline-light icon-btn" href="<?= site_url('compras' . ($companyQuery ? '?' . http_build_query($companyQuery) : '')) ?>" title="Volver a Compras" aria-label="Volver a Compras"><i class="bi bi-arrow-left"></i></a>
    </nav>
</header>

<form method="post" action="<?= site_url('compras/reposicion/analizar') ?>" class="insight-filters" id="repl-params">
    <?= csrf_field() ?>
    <?php if (! empty($companies)): ?>
        <label>Empresa<select name="company_id" class="form-select" onchange="location.href='<?= site_url('compras/reposicion') ?>?company_id='+encodeURIComponent(this.value)"><?php foreach ($companies as $option): ?><option value="<?= esc($option['id']) ?>" <?= $option['id'] === $companyId ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label>Depósito<select name="warehouse_id" class="form-select"><option value="">Todos los depósitos</option><?php foreach ($warehouses as $w): ?><option value="<?= esc($w['id']) ?>" <?= ($params['warehouse_id'] ?? null) === $w['id'] ? 'selected' : '' ?>><?= esc($w['name']) ?></option><?php endforeach; ?></select></label>
    <label>Historia de demanda<select name="history_days" class="form-select"><?php foreach (\App\Libraries\ReplenishmentAnalysisService::HISTORY_OPTIONS as $d): ?><option value="<?= $d ?>" <?= (int) $params['history_days'] === $d ? 'selected' : '' ?>><?= $d ?> días</option><?php endforeach; ?></select></label>
    <label title="Probabilidad de no quedarse sin stock durante el plazo de entrega">Nivel de servicio<select name="service_level" class="form-select"><?php foreach (\App\Libraries\ReplenishmentAnalysisService::SERVICE_LEVELS as $s): ?><option value="<?= $s ?>" <?= (float) $params['service_level'] == $s ? 'selected' : '' ?>><?= $fmt($s, $s == floor($s) ? 0 : 1) ?> %</option><?php endforeach; ?></select></label>
    <label title="Cada cuántos días se revisan y emiten compras">Frecuencia de compra (días)<input type="number" min="1" max="60" name="review_days" class="form-control" value="<?= esc((string) $params['review_days']) ?>"></label>
    <label title="Se usa cuando no hay plazo observado ni cargado en el producto">Plazo de entrega por defecto (días)<input type="number" min="1" max="120" name="default_lead_time" class="form-control" value="<?= esc((string) $params['default_lead_time']) ?>"></label>
    <div class="insight-filter-actions"><button class="btn insight-action repl-analyze" title="Ejecutar análisis" aria-label="Ejecutar análisis" data-busy-label="Analizando…"><i class="bi bi-stars"></i></button></div>
</form>

<?php if (! $run): ?>
    <section class="insight-panel"><div class="insight-empty"><i class="bi bi-stars"></i><strong>Todavía no hay análisis para esta empresa</strong>
        <span>Elegí los parámetros y presioná <i class="bi bi-stars"></i> para analizar stock, demanda y órdenes abiertas de todos los productos.</span></div></section>
<?php else: ?>

<div class="insight-section-label"><span>DIAGNÓSTICO</span><span>Demanda del <?= esc(date('d/m/Y', strtotime($run['params']['window_start']))) ?> al <?= esc(date('d/m/Y', strtotime($run['params']['window_end']))) ?> · servicio <?= esc($fmt($run['params']['service_level'], 1)) ?> % · <?= esc($run['warehouse_id'] ? 'depósito filtrado' : 'todos los depósitos') ?></span></div>
<div class="insight-kpis">
    <a class="insight-kpi <?= ($summary['critical'] ?? 0) > 0 ? 'insight-kpi-alert' : '' ?>" href="#repl-orders"><div class="insight-kpi-label">Productos a reponer<i class="bi bi-cart-plus"></i></div><strong><?= $fmt($summary['to_reorder']) ?></strong><span><?= $fmt($summary['critical']) ?> críticos · <?= $fmt($summary['high']) ?> prioridad alta</span></a>
    <a class="insight-kpi insight-kpi-primary" href="#repl-orders"><div class="insight-kpi-label">Inversión sugerida<i class="bi bi-cash-stack"></i></div><strong><?= $money($summary['investment']) ?></strong><span><?= $money($summary['investment_with_tax']) ?> con IVA<?= ($summary['without_cost'] ?? 0) > 0 ? ' · ' . $fmt($summary['without_cost']) . ' sin costo' : '' ?></span></a>
    <a class="insight-kpi <?= $summary['sales_at_risk'] > 0 ? 'insight-kpi-alert' : '' ?>" href="#repl-orders"><div class="insight-kpi-label">Venta en riesgo<i class="bi bi-exclamation-triangle"></i></div><strong><?= $money($summary['sales_at_risk']) ?></strong><span>Faltante esperado durante el plazo de entrega</span></a>
    <a class="insight-kpi" href="#repl-excess"><div class="insight-kpi-label">Capital inmovilizado<i class="bi bi-snow"></i></div><strong><?= $money($summary['immobile_value']) ?></strong><span><?= $fmt($summary['immobile_count']) ?> productos sin ventas en <?= (int) $run['params']['history_days'] ?> días</span></a>
    <a class="insight-kpi" href="#repl-excess"><div class="insight-kpi-label">Sobrestock<i class="bi bi-boxes"></i></div><strong><?= $money($summary['overstock_value']) ?></strong><span><?= $fmt($summary['overstock_count']) ?> productos por encima del nivel razonable</span></a>
    <div class="insight-kpi"><div class="insight-kpi-label">Productos analizados<i class="bi bi-diagram-3"></i></div><strong><?= $fmt($summary['analyzed']) ?></strong><span>ABC: <?= $fmt($summary['abc']['A']) ?> A · <?= $fmt($summary['abc']['B']) ?> B · <?= $fmt($summary['abc']['C']) ?> C</span></div>
</div>

<div class="insight-analysis-grid">
    <section class="insight-panel repl-ai">
        <div class="insight-panel-heading">
            <div><span class="insight-overline">INFORME EJECUTIVO</span><h2>Lectura del análisis con IA</h2><p>Modelo configurado: <?= esc($aiLabel) ?>. Si no responde, se usan las reglas del motor.</p></div>
            <button type="button" class="btn btn-dark btn-sm repl-ai-btn" id="repl-ai-btn" data-has-summary="<?= empty($run['ai_summary']) ? '0' : '1' ?>">
                <i class="bi bi-stars me-1"></i><span><?= empty($run['ai_summary']) ? 'Generar informe' : 'Regenerar' ?></span></button>
        </div>
        <div id="repl-ai-output" class="repl-ai-output" aria-live="polite" <?= empty($run['ai_summary']) ? 'hidden' : '' ?>><?= esc((string) ($run['ai_summary'] ?? '')) ?></div>
        <small id="repl-ai-meta" class="insight-caption"><?= ! empty($run['ai_summary']) ? 'Generado ' . esc(date('d/m/Y H:i', strtotime($run['ai_generated_at']))) . ' · ' . esc($run['ai_provider']) : '' ?></small>
        <h3 class="repl-subtitle">Hallazgos del motor</h3>
        <ul class="repl-insights">
            <?php foreach ($run['insights'] as $insight): ?>
                <li class="repl-insight-<?= esc($insight['level']) ?>"><i class="bi bi-<?= ['danger' => 'exclamation-octagon', 'warning' => 'exclamation-triangle', 'success' => 'check-circle', 'info' => 'lightbulb'][$insight['level']] ?? 'dot' ?>" aria-hidden="true"></i><span><?= esc($insight['text']) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <section class="insight-panel">
        <span class="insight-overline">COMPRA SUGERIDA POR PROVEEDOR</span><h2>Agrupación de pedidos</h2>
        <?php $suppliersSummary = $summary['suppliers'] ?? []; $maxSupplier = max(array_merge([1], array_column($suppliersSummary, 'amount'))); ?>
        <?php foreach (array_slice($suppliersSummary, 0, 8, true) as $supplier): ?>
            <div class="insight-rank"><div><span><?= esc($supplier['name']) ?> <small><?= $fmt($supplier['lines']) ?> ítems<?= $supplier['critical'] ? ' · ' . $fmt($supplier['critical']) . ' críticos' : '' ?></small></span><strong><?= $money($supplier['amount']) ?></strong></div><div class="insight-bar"><span style="width:<?= round($supplier['amount'] / $maxSupplier * 100, 1) ?>%"></span></div></div>
        <?php endforeach; ?>
        <?php if (($summary['without_supplier'] ?? 0) > 0): ?>
            <div class="insight-notice mt-3"><i class="bi bi-person-x me-1"></i><?= $fmt($summary['without_supplier']) ?> productos a reponer no tienen proveedor: elegilo en la tabla antes de generar las órdenes, o cargá el proveedor habitual en la ficha del producto.</div>
        <?php endif; ?>
        <?php if (! $suppliersSummary && ! ($summary['without_supplier'] ?? 0)): ?><div class="insight-empty"><i class="bi bi-check2-circle"></i><span>No hay compras sugeridas.</span></div><?php endif; ?>
        <?php if ($run['orders']): ?>
            <h3 class="repl-subtitle">Órdenes generadas desde este análisis</h3>
            <ul class="list-unstyled small mb-0"><?php foreach ($run['orders'] as $order): ?><li><i class="bi bi-file-earmark-text me-1"></i><?= esc($order['order_number']) ?> · <?= $fmt($order['lines']) ?> líneas · <?= $money($order['total']) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </section>
</div>

<form method="post" action="<?= site_url('compras/reposicion/' . $run['id'] . '/ordenes') ?>" id="repl-orders-form" class="insight-panel repl-orders" onsubmit="return confirm('Se crearán órdenes de compra en borrador, una por proveedor. ¿Continuar?');">
    <?= csrf_field() ?>
    <?php if ($companyQuery): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
    <div class="insight-panel-heading" id="repl-orders">
        <div><span class="insight-overline">SUGERENCIAS DE COMPRA</span><h2>Qué comprar, cuánto y a quién</h2><p>Ordenado por prioridad, clase ABC y días de cobertura. Podés ajustar cantidad, proveedor y costo antes de generar las órdenes.</p></div>
        <div class="repl-toolbar">
            <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por prioridad">
                <?php foreach (['' => 'Todas', 'critica' => 'Críticas', 'alta' => 'Alta', 'media' => 'Media', 'sin-proveedor' => 'Sin proveedor'] as $key => $label): ?>
                    <button type="button" class="btn btn-outline-secondary <?= $key === '' ? 'active' : '' ?>" data-repl-filter="<?= esc($key) ?>"><?= esc($label) ?></button>
                <?php endforeach; ?>
            </div>
            <input type="search" class="form-control form-control-sm" id="repl-search" placeholder="Buscar producto o SKU" aria-label="Buscar producto">
        </div>
    </div>
    <div class="repl-table-scroll">
        <table class="repl-table">
            <thead><tr>
                <th><input type="checkbox" class="form-check-input" id="repl-select-all" aria-label="Seleccionar todos los visibles"></th>
                <th>Producto</th><th title="ABC: peso en el consumo valorizado · XYZ: regularidad de la demanda">Clase</th>
                <th class="text-end">Disponible</th><th class="text-end">En camino</th><th>Demanda semanal</th>
                <th class="text-end" title="Días que cubre el stock disponible / plazo de entrega">Cobertura / plazo</th>
                <th class="text-end" title="Punto de pedido (incluye stock de seguridad)">Punto pedido</th>
                <th>Cantidad</th><th>Proveedor</th><th>Costo unit.</th><th class="text-end">Subtotal</th><th>Prioridad</th>
            </tr></thead>
            <tbody>
            <?php foreach ($reorderRows as $r): $pid = esc($r['product_id'], 'attr'); ?>
                <tr data-priority="<?= esc($r['priority']) ?>" data-search="<?= esc(mb_strtolower($r['name'] . ' ' . $r['sku'] . ' ' . $r['category']), 'attr') ?>" class="repl-row repl-row-<?= esc($r['priority']) ?>">
                    <td><input type="checkbox" class="form-check-input repl-check" name="selected[]" value="<?= $pid ?>" aria-label="Incluir <?= esc($r['name'], 'attr') ?>" <?= $r['priority'] !== 'media' && $r['supplier_id'] ? 'checked' : '' ?>></td>
                    <td class="repl-product">
                        <strong><?= esc($r['name']) ?></strong><small><?= esc($r['sku'] ?: 'Sin SKU') ?><?= $r['category'] ? ' · ' . esc($r['category']) : '' ?></small>
                        <details><summary>¿Por qué?</summary><ul><?php foreach ($r['reasons'] as $reason): ?><li><?= esc($reason) ?></li><?php endforeach; ?></ul></details>
                    </td>
                    <td><span class="repl-class repl-abc-<?= esc($r['abc']) ?>"><?= esc($r['abc']) ?></span><span class="repl-class repl-xyz"><?= esc($r['xyz']) ?></span></td>
                    <td class="text-end <?= $r['available'] <= 0 ? 'insight-danger' : '' ?>"><?= $qty($r['available']) ?><?php if ($r['reserved'] > 0): ?><small><?= $qty($r['reserved']) ?> reserv.</small><?php endif; ?></td>
                    <td class="text-end"><?= $r['on_order'] > 0 ? $qty($r['on_order']) : '—' ?></td>
                    <td><div class="repl-demand"><?= $spark($r['series']) ?><span><?= $qty(round($r['weekly_forecast'], 1)) ?> u<?php if (abs($r['trend_pct']) >= 15): ?> <i class="bi bi-arrow-<?= $r['trend_pct'] > 0 ? 'up-right text-success' : 'down-right text-danger' ?>" title="Tendencia <?= esc($fmt($r['trend_pct'])) ?> %"></i><?php endif; ?></span></div><small><?= esc($methodLabels[$r['forecast_method']] ?? $r['forecast_method']) ?></small></td>
                    <td class="text-end"><?= $r['coverage_days'] === null ? '—' : $qty(round($r['coverage_days'])) . ' d' ?><small><?= (int) $r['lead_time'] ?> d · <?= esc($leadLabels[$r['lead_time_source']] ?? '') ?></small></td>
                    <td class="text-end"><?= $qty(round($r['reorder_point'], 1)) ?><small>SS <?= $qty(round($r['safety_stock'], 1)) ?></small></td>
                    <td><input type="number" min="0" step="1" class="form-control form-control-sm repl-qty" name="quantity[<?= $pid ?>]" value="<?= esc((string) (int) $r['suggested_qty']) ?>" aria-label="Cantidad a comprar"></td>
                    <td><select class="form-select form-select-sm repl-supplier" name="supplier_id[<?= $pid ?>]" aria-label="Proveedor">
                        <option value="">Elegir…</option>
                        <?php foreach ($suppliers as $s): ?><option value="<?= esc($s['id']) ?>" <?= $r['supplier_id'] === $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach; ?>
                    </select></td>
                    <td><input type="number" min="0" step="0.01" class="form-control form-control-sm repl-cost <?= ! empty($r['missing_cost']) ? 'is-warning' : '' ?>" name="unit_cost[<?= $pid ?>]" value="<?= esc(number_format((float) $r['unit_cost'], 2, '.', '')) ?>" aria-label="Costo unitario" <?= ! empty($r['missing_cost']) ? 'title="El producto no tiene costo cargado"' : '' ?>></td>
                    <td class="text-end repl-subtotal">—</td>
                    <td><span class="repl-badge repl-badge-<?= esc($r['priority']) ?>"><?= esc($priorityLabels[$r['priority']] ?? '') ?></span><?php if ($r['stockout_date'] && $r['priority'] === 'critica'): ?><small><?= $r['available'] <= 0 ? 'Sin stock' : 'Quiebre ' . esc(date('d/m', strtotime($r['stockout_date']))) ?></small><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $reorderRows): ?><tr><td colspan="13"><div class="insight-empty"><i class="bi bi-check2-circle"></i><strong>Sin compras necesarias</strong><span>Con los parámetros elegidos, el stock y las órdenes abiertas cubren la demanda esperada.</span></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($reorderRows): ?>
    <div class="repl-footer">
        <div><strong id="repl-selected-count">0</strong> productos seleccionados · <strong id="repl-selected-total">$ 0</strong> + IVA · <span id="repl-selected-suppliers">0</span> órdenes</div>
        <?php if ($context['canManage']): ?>
            <div class="d-flex gap-2 align-items-center">
                <select name="warehouse_id" class="form-select form-select-sm" aria-label="Depósito de recepción"><?php foreach ($warehouses as $w): ?><option value="<?= esc($w['id']) ?>" <?= ($run['warehouse_id'] ?? '') === $w['id'] ? 'selected' : '' ?>><?= esc($w['name']) ?></option><?php endforeach; ?></select>
                <button class="btn btn-dark btn-sm text-nowrap" id="repl-create-orders"><i class="bi bi-cart-check me-1"></i>Crear órdenes borrador</button>
            </div>
        <?php else: ?><span class="text-secondary small">Necesitás acceso de gestión en Compras para generar órdenes.</span><?php endif; ?>
    </div>
    <?php endif; ?>
</form>

<section class="insight-panel mt-4" id="repl-excess">
    <span class="insight-overline">CAPITAL A LIBERAR</span><h2>Stock inmovilizado y sobrestock</h2>
    <p class="text-secondary small">No conviene volver a comprar estos productos. Evaluá promociones, combos, transferencias entre depósitos o devolución al proveedor.</p>
    <div class="insight-table-scroll repl-excess-scroll">
        <table>
            <thead><tr><th>Producto</th><th>Situación</th><th class="text-end">Stock</th><th class="text-end">Demanda semanal</th><th class="text-end">Último egreso</th><th class="text-end">Excedente</th><th class="text-end">Valor a liberar</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($excessRows, 0, 60) as $r): $value = $r['status'] === 'inmovilizado' ? $r['stock_value'] : $r['excess_value']; ?>
                <tr>
                    <td><strong><?= esc($r['name']) ?></strong><small><?= esc($r['sku'] ?: 'Sin SKU') ?></small></td>
                    <td><span class="repl-badge <?= $r['status'] === 'inmovilizado' ? 'repl-badge-frozen' : 'repl-badge-excess' ?>"><?= $r['status'] === 'inmovilizado' ? 'Sin ventas' : 'Sobrestock' ?></span></td>
                    <td class="text-end"><?= $qty($r['on_hand']) ?></td>
                    <td class="text-end"><?= $qty(round($r['weekly_forecast'], 1)) ?></td>
                    <td class="text-end"><?= $r['days_without_outflow'] !== null ? 'hace ' . (int) $r['days_without_outflow'] . ' d' : 'nunca' ?></td>
                    <td class="text-end"><?= $r['status'] === 'inmovilizado' ? $qty($r['on_hand']) : $qty(round($r['excess_qty'])) ?></td>
                    <td class="text-end"><?= $value > 0 ? $money($value) : '<span class="text-secondary" title="Sin costo cargado">s/costo</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $excessRows): ?><tr><td colspan="7" class="text-secondary">No se detecta stock inmovilizado ni sobrestock.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<div class="insight-analysis-grid mt-4">
    <section class="insight-panel">
        <span class="insight-overline">HISTORIAL</span><h2>Análisis anteriores</h2>
        <div class="insight-table-scroll">
            <table>
                <thead><tr><th>Fecha</th><th>Usuario</th><th class="text-end">A reponer</th><th class="text-end">Inversión</th><th class="text-end">Órdenes</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr <?= $run && $h['id'] === $run['id'] ? 'class="repl-current"' : '' ?>>
                        <td><?= esc(date('d/m/Y H:i', strtotime($h['created_at']))) ?><small><?= esc($h['warehouse_name'] ?: 'Todos los depósitos') ?> · <?= (int) ($h['params']['history_days'] ?? 0) ?> d · <?= esc($fmt($h['params']['service_level'] ?? 0, 1)) ?> %</small></td>
                        <td><?= esc($h['user_name'] ?: 'Automático') ?></td>
                        <td class="text-end"><?= $fmt($h['summary']['to_reorder'] ?? 0) ?></td>
                        <td class="text-end"><?= $money($h['summary']['investment'] ?? 0) ?></td>
                        <td class="text-end"><?= count($h['orders']) ?></td>
                        <td class="text-end"><a href="<?= site_url('compras/reposicion?' . http_build_query($companyQuery + ['run' => $h['id']])) ?>" title="Ver análisis" aria-label="Ver análisis"><i class="bi bi-box-arrow-up-right"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (! $history): ?><tr><td colspan="6" class="text-secondary">Sin análisis previos.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="insight-panel repl-method">
        <span class="insight-overline">METODOLOGÍA</span><h2>Cómo decide el motor</h2>
        <ul>
            <li><strong>Demanda real:</strong> egresos de stock por ventas y remitos (incluye componentes de kits), menos devoluciones y anulaciones, agrupados por semana.</li>
            <li><strong>Pronóstico:</strong> Holt amortiguado (nivel y tendencia) para demanda regular; Croston-SBA para demanda intermitente.</li>
            <li><strong>Plazo de entrega:</strong> el observado entre la emisión de la orden y la recepción; si no hay historia, el de la ficha del producto o el valor por defecto.</li>
            <li><strong>Stock de seguridad:</strong> z × σ × √plazo según el nivel de servicio, nunca menor al stock mínimo del producto.</li>
            <li><strong>Cuándo comprar:</strong> si el disponible más lo que está en camino no alcanza hasta la próxima revisión. Se compra hasta el nivel objetivo, respetando el stock máximo, el lote mínimo y el múltiplo de compra.</li>
            <li><strong>ABC/XYZ:</strong> A = 80 % del consumo valorizado, B = 15 %, C = 5 %. X/Y/Z según el coeficiente de variación semanal (≤ 0,5 / ≤ 1 / &gt; 1).</li>
        </ul>
    </section>
</div>
</div>
<script src="<?= base_url('assets/js/purchase-replenishment.js') . '?v=' . filemtime(FCPATH . 'assets/js/purchase-replenishment.js') ?>" defer></script>
<?= $this->endSection() ?>
