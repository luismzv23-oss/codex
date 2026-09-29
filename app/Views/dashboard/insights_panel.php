<?php
$money = static fn($value) => number_format((float) $value, 2, ',', '.');
$integer = static fn($value) => number_format((float) $value, 0, ',', '.');
$amount = (float) $insights['summary']['amount'];
$count = (int) $insights['summary']['documents'];
$change = $insights['previous'] > 0 ? ($amount / $insights['previous'] - 1) * 100 : null;
$critical = count($insights['critical']);
$trend = $insights['trend'];
$max = max(array_merge([1], array_column($trend, 'amount')));
$points = [];
foreach ($trend as $i => $day) $points[] = (42 + ($i / max(1, count($trend) - 1)) * 870) . ',' . (200 - ($day['amount'] / $max) * 155);
$line = implode(' ', $points);
$periodLabel = date('d/m/Y', strtotime($filters['from'])) . ' — ' . date('d/m/Y', strtotime($filters['to']));
?>
<div class="insight-section-label"><span>01 / RESUMEN EJECUTIVO</span><span><?= esc($periodLabel) ?> · <?= esc($filters['currency']) ?></span></div>
<section class="insight-kpis" aria-label="Seis indicadores principales">
    <a class="insight-kpi insight-kpi-primary" href="#insight-documents"><div class="insight-kpi-label">Ventas emitidas <i class="bi bi-arrow-up-right" aria-hidden="true"></i></div><strong><?= $money($amount) ?></strong><span class="insight-comparison"><?= $change === null ? 'Sin base de comparación anterior' : ($change > 0 ? '↑ ' : ($change < 0 ? '↓ ' : '')) . $money($change) . '% vs. período anterior' ?></span></a>
    <a class="insight-kpi" href="#insight-documents"><div class="insight-kpi-label">Comprobantes <i class="bi bi-receipt" aria-hidden="true"></i></div><strong><?= $integer($count) ?></strong><span>Facturas y tickets del período</span></a>
    <a class="insight-kpi" href="#insight-documents"><div class="insight-kpi-label">Venta promedio <i class="bi bi-bar-chart" aria-hidden="true"></i></div><strong><?= $count ? $money($amount / $count) : '—' ?></strong><span><?= $count ? 'Importe por comprobante' : 'Sin ventas en este período' ?></span></a>
    <a class="insight-kpi" href="#insight-priorities"><div class="insight-kpi-label">Por cobrar <i class="bi bi-wallet2" aria-hidden="true"></i></div><strong><?= $money($insights['balance']) ?></strong><span>Saldo actual · <?= $money($insights['overdue']) ?> vencido</span></a>
    <a class="insight-kpi <?= $critical ? 'insight-kpi-alert' : '' ?>" href="#insight-stock"><div class="insight-kpi-label">Stock en atención <i class="bi bi-box-seam" aria-hidden="true"></i></div><strong><?= $integer($critical) ?></strong><span>Producto / depósito · situación actual</span></a>
    <a class="insight-kpi <?= $insights['fiscal'] ? 'insight-kpi-alert' : '' ?>" href="#insight-priorities"><div class="insight-kpi-label">ARCA por resolver <i class="bi bi-shield-exclamation" aria-hidden="true"></i></div><strong><?= $integer($insights['fiscal']) ?></strong><span>Pendientes o errores sin CAE · actual</span></a>
</section>
<div class="insight-analysis-grid">
    <section class="insight-panel insight-trend">
        <div class="insight-panel-heading"><div><span class="insight-overline">EVOLUCIÓN COMERCIAL</span><h2>El ritmo de tus ventas</h2><p>Importe diario · <?= esc($filters['currency']) ?></p></div><span class="insight-tag"><span class="insight-series-dot"></span> Ventas</span></div>
        <?php if ($count): ?>
        <svg class="insight-chart" viewBox="0 0 960 240" role="img" aria-label="Ventas diarias de <?= esc($periodLabel) ?>. El detalle está disponible debajo.">
            <defs><linearGradient id="insight-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#6657ed" stop-opacity=".24"/><stop offset="100%" stop-color="#6657ed" stop-opacity="0"/></linearGradient></defs>
            <?php foreach ([0, 1, 2, 3] as $tick): $y = 200 - $tick * 52; ?><line x1="42" x2="912" y1="<?= $y ?>" y2="<?= $y ?>" stroke="#e9ecf4" stroke-dasharray="4 5"/><?php endforeach; ?>
            <?php if (count($trend) > 1): ?><polygon points="42,200 <?= esc($line) ?> 912,200" fill="url(#insight-fill)"/><?php endif; ?>
            <polyline points="<?= esc($line) ?>" fill="none" stroke="#6657ed" stroke-width="3" stroke-linejoin="round"/>
            <?php foreach ($trend as $i => $day): $xy = explode(',', $points[$i]); ?><circle cx="<?= $xy[0] ?>" cy="<?= $xy[1] ?>" r="3" fill="#6657ed"><title><?= esc($day['label']) ?>: <?= $money($day['amount']) ?> <?= esc($filters['currency']) ?></title></circle><?php endforeach; ?>
            <text x="42" y="228"><?= esc($trend[0]['label']) ?></text><text x="912" y="228" text-anchor="end"><?= esc($trend[count($trend) - 1]['label']) ?></text><text x="42" y="22">Máximo diario <?= $money($max) ?></text><text x="920" y="204">0</text>
        </svg>
        <?php else: ?><div class="insight-empty"><i class="bi bi-graph-up" aria-hidden="true"></i><strong>Un período sin ventas registradas</strong><span>Prueba otro rango de fechas o empresa para explorar su actividad.</span></div><?php endif; ?>
        <details id="insight-daily"><summary>Explorar valores diarios <i class="bi bi-chevron-down" aria-hidden="true"></i></summary><div class="insight-table-scroll"><table><thead><tr><th>Fecha</th><th class="text-end">Ventas <?= esc($filters['currency']) ?></th></tr></thead><tbody><?php foreach ($trend as $day): ?><tr><td><?= esc($day['date']) ?></td><td class="text-end"><?= $money($day['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></details>
    </section>
    <section class="insight-panel" id="insight-priorities">
        <div class="insight-panel-heading"><div><span class="insight-overline">TU PRÓXIMO PASO</span><h2>Foco de atención</h2><p>Saldos y pendientes actuales</p></div><i class="bi bi-crosshair" aria-hidden="true"></i></div>
        <?php foreach ([['Stock por revisar', $critical . ' combinaciones de producto y depósito bajo el mínimo.', $critical, 'box-seam', '#insight-stock'], ['Cobranza vencida', $money($insights['overdue']) . ' ' . $filters['currency'] . ' con vencimiento anterior a hoy.', $insights['overdue'], 'wallet2', null], ['Autorización fiscal', $insights['fiscal'] . ' comprobantes pendientes o rechazados sin CAE.', $insights['fiscal'], 'shield-exclamation', null]] as [$label, $description, $value, $icon, $target]): ?>
        <div class="insight-priority"><span class="insight-priority-icon <?= $value > 0 ? 'attention' : 'clear' ?>"><i class="bi bi-<?= esc($icon) ?>" aria-hidden="true"></i></span><div><strong><?= esc($label) ?></strong><p><?= esc($description) ?></p><small><?= $value > 0 ? 'Requiere revisión' : 'Sin pendientes detectados' ?></small></div><?php if ($target): ?><a href="<?= esc($target) ?>" aria-label="Ver stock por revisar"><i class="bi bi-arrow-down-right" aria-hidden="true"></i></a><?php endif; ?></div>
        <?php endforeach; ?>
        <p class="insight-caption">Las alertas actuales no cambian con las fechas. Respetan la empresa; los saldos y ARCA también respetan la moneda.</p>
        <?php if ($filters['company_id']): ?>
        <div class="insight-detail-links"><a href="<?= site_url('ventas/cobranzas') . '?company_id=' . esc($filters['company_id']) ?>">Ver cobranzas <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a href="<?= site_url('ventas') . '?company_id=' . esc($filters['company_id']) ?>">Revisar comprobantes <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
        <?php else: ?><p class="insight-caption">Selecciona una empresa para acceder a sus cobranzas y comprobantes.</p><?php endif; ?>
    </section>
</div>
<div class="insight-secondary-grid">
    <section class="insight-panel"><div class="insight-panel-heading"><div><span class="insight-overline"><?= $superadmin ? 'VISIÓN MULTIEMPRESA' : 'DESEMPEÑO LOCAL' ?></span><h2><?= $superadmin ? 'Empresas en perspectiva' : 'Ventas por sucursal' ?></h2><p>Hasta seis resultados · mismo período y moneda</p></div></div>
        <?php $top = max(array_merge([1], array_column($insights['ranking'], 'amount'))); foreach ($insights['ranking'] as $rank => $row): ?>
        <div class="insight-rank"><div><span><small><?= str_pad((string) ($rank + 1), 2, '0', STR_PAD_LEFT) ?></small> <?= esc($row['name']) ?></span><strong><?= $money($row['amount']) ?></strong></div><div class="insight-bar"><span style="width:<?= max(0, (float) $row['amount'] / $top * 100) ?>%"></span></div><?php if ($superadmin): ?><a class="insight-rank-link" href="<?= site_url('dashboard') . '?' . esc(http_build_query(['from' => $filters['from'], 'to' => $filters['to'], 'currency' => $filters['currency'], 'company_id' => $row['id']])) ?>">Explorar empresa <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><?php endif; ?></div>
        <?php endforeach; if (!$insights['ranking']): ?><p class="insight-caption">No hay ventas para comparar con estos filtros.</p><?php endif; ?>
    </section>
    <section class="insight-panel" id="insight-stock"><div class="insight-panel-heading"><div><span class="insight-overline">DISPONIBILIDAD</span><h2>Inventario que necesita atención</h2><p>Disponibles = existencias − reservas · todas las monedas</p></div><span class="insight-tag"><?= $critical ?> casos</span></div>
        <?php if (!$critical): ?><div class="insight-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i><strong>Sin stock crítico registrado</strong><span>No hay saldos de inventario bajo el mínimo en este alcance.</span></div><?php else: ?>
        <div class="insight-table-scroll"><table><thead><tr><th>Producto / depósito</th><th class="text-end">Disponible</th><th class="text-end">Mínimo</th></tr></thead><tbody><?php foreach (array_slice($insights['critical'], 0, 5) as $row): ?><tr><td><strong><?= esc($row['name']) ?></strong><small><?= esc($row['sku'] . ' · ' . ($superadmin ? $row['company'] . ' · ' : '') . $row['warehouse']) ?></small></td><td class="text-end insight-danger"><?= $money($row['available']) ?></td><td class="text-end"><?= $money($row['minimum']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <span class="insight-caption">Los <?= min(5, $critical) ?> saldos más bajos de <?= $critical ?>.</span>
        <?php if ($filters['company_id']): ?><div class="insight-detail-links"><a href="<?= site_url('inventario') . '?company_id=' . esc($filters['company_id']) ?>">Explorar inventario <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div><?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<section class="insight-panel insight-documents" id="insight-documents"><div class="insight-panel-heading"><div><span class="insight-overline">DEL INDICADOR AL DETALLE</span><h2>Últimos comprobantes del período</h2><p>Hasta ocho operaciones · <?= esc($filters['currency']) ?></p></div></div>
    <div class="insight-table-scroll"><table><thead><tr><th>Comprobante</th><?php if ($superadmin): ?><th>Empresa</th><?php endif; ?><th>Fecha</th><th>Estado</th><th class="text-end">Importe</th></tr></thead><tbody><?php foreach ($insights['recent'] as $row): ?><tr><td><strong><?= esc($row['sale_number']) ?></strong></td><?php if ($superadmin): ?><td><?= esc($row['company']) ?></td><?php endif; ?><td><?= esc(date('d/m/Y H:i', strtotime($row['issue_date']))) ?></td><td><span class="insight-tag"><?= esc(['confirmed' => 'Confirmada', 'returned_partial' => 'Devolución parcial', 'returned_total' => 'Devuelta'][$row['status']] ?? $row['status']) ?></span></td><td class="text-end"><?= $money($row['total']) ?></td></tr><?php endforeach; if (!$insights['recent']): ?><tr><td colspan="5" class="insight-caption">No hay comprobantes en el período seleccionado.</td></tr><?php endif; ?></tbody></table></div>
    <p class="insight-caption">Ventas emitidas: importe original de facturas y tickets confirmados, incluidos impuestos y recargos. Incluye comprobantes con devoluciones; no representa venta neta ni dinero cobrado. Comparación contra los <?= (int) $filters['days'] ?> días anteriores. No se mezclan monedas.</p>
</section>
