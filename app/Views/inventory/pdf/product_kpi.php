<?php use App\Libraries\InventoryProductKpi; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1, h2 { margin: 0 0 8px; }
        h2 { font-size: 12px; margin-top: 6px; }
        .meta { margin-bottom: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d9d9d9; padding: 5px 7px; vertical-align: top; }
        th { background: #f3eee8; text-align: left; }
        td.num, th.num { text-align: right; }
        .section td { background: #faf7f3; font-weight: bold; }
        .total td { font-weight: bold; background: #f3eee8; }
    </style>
</head>
<body>
    <?php $period = $kpi['period']; ?>
    <h1>Analisis KPI del producto</h1>
    <div class="meta">
        Empresa: <?= esc($company['name'] ?? '') ?> | Producto: <?= esc($product['sku'] . ' - ' . $product['name']) ?> | Unidad: <?= esc($product['unit']) ?><br>
        Periodo: <?= esc(date('d/m/Y', strtotime($period['from']))) ?> al <?= esc(date('d/m/Y', strtotime($period['to']))) ?> (<?= (int) $period['days'] ?> dias) | Generado: <?= esc($generatedAt) ?>
    </div>

    <h2>Indicadores totalizados</h2>
    <table>
        <thead><tr><th>Indicador</th><th class="num">Valor</th></tr></thead>
        <tbody>
            <?php $current = null; ?>
            <?php foreach (InventoryProductKpi::rows($kpi) as [$section, $label, $value, $kind]): ?>
                <?php if ($section !== $current): $current = $section; ?>
                    <tr class="section"><td colspan="2"><?= esc($section) ?></td></tr>
                <?php endif; ?>
                <tr><td><?= esc($label) ?></td><td class="num"><?= esc(InventoryProductKpi::format($value, $kind)) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Detalle por deposito</h2>
    <table>
        <thead><tr><th>Deposito</th><th class="num">Stock</th><th class="num">Reservado</th><th class="num">Disponible</th><th class="num">Entradas</th><th class="num">Salidas</th><th class="num">Valor</th></tr></thead>
        <tbody>
            <?php foreach ($kpi['warehouses'] as $row): ?>
                <tr>
                    <td><?= esc($row['name']) ?> <span style="color:#888"><?= esc($row['code']) ?></span></td>
                    <td class="num"><?= InventoryProductKpi::format($row['quantity'], 'qty') ?></td>
                    <td class="num"><?= InventoryProductKpi::format($row['reserved'], 'qty') ?></td>
                    <td class="num"><?= InventoryProductKpi::format($row['available'], 'qty') ?></td>
                    <td class="num"><?= InventoryProductKpi::format($row['in'], 'qty') ?></td>
                    <td class="num"><?= InventoryProductKpi::format($row['out'], 'qty') ?></td>
                    <td class="num"><?= InventoryProductKpi::format($row['value'], 'money') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($kpi['warehouses'] === []): ?><tr><td colspan="7">No hay stock ni movimientos para este producto.</td></tr><?php endif; ?>
            <?php $t = $kpi['totals']; ?>
            <tr class="total">
                <td>TOTAL</td>
                <td class="num"><?= InventoryProductKpi::format($t['quantity'], 'qty') ?></td>
                <td class="num"><?= InventoryProductKpi::format($t['reserved'], 'qty') ?></td>
                <td class="num"><?= InventoryProductKpi::format($t['available'], 'qty') ?></td>
                <td class="num"><?= InventoryProductKpi::format($t['in'], 'qty') ?></td>
                <td class="num"><?= InventoryProductKpi::format($t['out'], 'qty') ?></td>
                <td class="num"><?= InventoryProductKpi::format($t['value'], 'money') ?></td>
            </tr>
        </tbody>
    </table>
    <div class="meta">Las transferencias internas se cuentan como entrada/salida en cada deposito, pero no alteran el total del producto. Rotacion = salidas del periodo / stock promedio ((inicial + final) / 2).</div>
</body>
</html>
