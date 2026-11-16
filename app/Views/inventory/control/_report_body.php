<?php
$money = static fn($v) => number_format((float) $v, 2, ',', '.');
$qty = static fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$cov = $report['coverage'];
$sections = [
    ['Controles recientes', ['Control', 'Semana', 'Estado', 'Productos', 'Diferencia $'], array_map(static fn($r) => [$r['count_number'], $r['week'], $r['status'], (int) $r['items_count'], $money($r['difference_value'])], $report['counts'])],
    ['Entradas de stock sin comprobante (últimos ' . (int) $report['days'] . ' días)', ['Fecha', 'SKU', 'Producto', 'Cantidad', 'Motivo', 'Usuario'], array_map(static fn($r) => [$r['occurred_at'], $r['sku'], $r['name'], $qty($r['quantity']), $r['reason'], $r['user_name'] ?? ''], $report['manual_entries'])],
    ['Ventas en borrador con pago registrado (no descontaron stock)', ['Venta', 'Fecha', 'Cobrado $', 'Usuario'], array_map(static fn($r) => [$r['sale_number'], $r['issue_date'], $money($r['paid']), $r['user_name'] ?? ''], $report['paid_drafts'])],
    ['Con stock y sin ventas en ' . (int) $report['no_rotation_days'] . ' días', ['SKU', 'Producto', 'Stock'], array_map(static fn($r) => [$r['sku'], $r['name'], $qty($r['stock'])], $report['no_rotation'])],
    ['Stock negativo', ['SKU', 'Producto', 'Stock'], array_map(static fn($r) => [$r['sku'], $r['name'], $qty($r['stock'])], $report['negative_stock'])],
];
?>
<p><strong>Cobertura del ciclo:</strong> <?= (int) $cov['counted_28_days'] ?> de <?= (int) $cov['catalog'] ?> productos contados en las últimas 4 semanas (<?= (int) $cov['percent'] ?>%).</p>
<?php foreach ($sections as [$title, $headers, $rows]): ?>
    <h2 class="report-title"><?= esc($title) ?> <small>(<?= count($rows) ?>)</small></h2>
    <?php if (! $rows): ?><p class="muted">Sin hallazgos.</p><?php else: ?>
    <table class="table table-sm">
        <thead><tr><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $v): ?><td><?= esc((string) $v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    </table>
    <?php endif; ?>
<?php endforeach; ?>
