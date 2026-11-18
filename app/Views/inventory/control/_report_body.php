<?php
$money = static fn($v) => number_format((float) $v, 2, ',', '.');
$qty = static fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$cov = $report['coverage'];
$sum = $report['summary'] ?? [];
$sections = [
    ['Controles recientes', ['Control', 'Semana', 'Depósito', 'Estado', 'Productos', 'Diferencia $'], array_map(static fn($r) => [$r['count_number'], $r['week'], $r['warehouse_name'] ?? '', $r['status'], (int) $r['items_count'], $money($r['difference_value'])], $report['counts'])],
    ['Entradas de stock sin comprobante', ['Fecha', 'SKU', 'Producto', 'Cantidad', 'Motivo', 'Usuario'], array_map(static fn($r) => [date('d/m/Y H:i', strtotime($r['occurred_at'])), $r['sku'], $r['name'], $qty($r['quantity']), $r['reason'], $r['user_name'] ?? ''], $report['manual_entries'])],
    ['Ventas en borrador con pago registrado', ['Venta', 'Fecha', 'Cliente', 'Cobrado $', 'Usuario'], array_map(static fn($r) => [$r['sale_number'], date('d/m/Y', strtotime($r['issue_date'])), $r['customer'] ?? '', $money($r['paid']), $r['user_name'] ?? ''], $report['paid_drafts'])],
    ['Con stock y sin ventas en ' . (int) $report['no_rotation_days'] . ' días', ['SKU', 'Producto', 'Stock', 'Valor $'], array_map(static fn($r) => [$r['sku'], $r['name'], $qty($r['stock']), $money($r['value'] ?? 0)], $report['no_rotation'])],
    ['Stock negativo', ['SKU', 'Producto', 'Stock', 'Valor $'], array_map(static fn($r) => [$r['sku'], $r['name'], $qty($r['stock']), $money($r['value'] ?? 0)], $report['negative_stock'])],
];
?>
<table class="summary">
    <tr><th>Cobertura del ciclo</th><th>Entradas sin comprobante</th><th>Borradores cobrados</th><th>Sin rotación</th><th>Stock negativo</th></tr>
    <tr>
        <td><?= (int) $cov['percent'] ?>% (<?= (int) $cov['counted_28_days'] ?>/<?= (int) $cov['catalog'] ?>)</td>
        <td><?= count($report['manual_entries']) ?> · <?= $qty($sum['manual_units'] ?? 0) ?> u.</td>
        <td><?= count($report['paid_drafts']) ?> · $ <?= $money($sum['paid_total'] ?? 0) ?></td>
        <td><?= count($report['no_rotation']) ?> · $ <?= $money($sum['no_rotation_value'] ?? 0) ?></td>
        <td><?= count($report['negative_stock']) ?></td>
    </tr>
</table>
<?php foreach ($sections as [$title, $headers, $rows]): ?>
    <h2 class="report-title"><?= esc($title) ?> <small>(<?= count($rows) ?>)</small></h2>
    <?php if (! $rows): ?><p class="muted">Sin hallazgos.</p><?php else: ?>
    <table>
        <thead><tr><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $v): ?><td><?= esc((string) $v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    </table>
    <?php endif; ?>
<?php endforeach; ?>
