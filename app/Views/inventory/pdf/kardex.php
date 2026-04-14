<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1, h2 { margin: 0 0 8px; }
        .meta { margin-bottom: 16px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th, td { border: 1px solid #d9d9d9; padding: 6px 8px; vertical-align: top; }
        th { background: #f3eee8; text-align: left; }
        .muted { color: #666; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Kardex de Inventario</h1>
    <div class="meta">
        Empresa: <?= esc($company['name'] ?? '') ?><br>
        Generado: <?= esc($generatedAt) ?><br>
        Filtros: <?= esc(implode(' | ', array_filter([
            ! empty($filters['start_date']) ? 'Desde ' . $filters['start_date'] : null,
            ! empty($filters['end_date']) ? 'Hasta ' . $filters['end_date'] : null,
            ! empty($filters['movement_type']) ? 'Movimiento ' . $filters['movement_type'] : null,
            ! empty($filters['source_document']) ? 'Documento ' . $filters['source_document'] : null,
            ! empty($filters['reason']) ? 'Motivo ' . $filters['reason'] : null,
        ])) ?: 'Sin filtros') ?>
    </div>

    <h2>Resumen por producto</h2>
    <table>
        <thead><tr><th>Producto</th><th>Movimientos</th><th>Entradas</th><th>Salidas</th><th>Transferencias</th><th>Stock</th><th>Disponible</th><th>Valor stock</th><th>Costo prom.</th><th>Ultimo movimiento</th></tr></thead>
        <tbody>
            <?php foreach ($summaryRows as $row): ?>
                <tr>
                    <td><?= esc($row['sku'] . ' - ' . $row['product_name']) ?></td>
                    <td><?= esc((string) $row['movement_count']) ?></td>
                    <td><?= number_format((float) $row['total_in'], 2, ',', '.') ?></td>
                    <td><?= number_format((float) $row['total_out'], 2, ',', '.') ?></td>
                    <td><?= number_format((float) $row['total_transfer'], 2, ',', '.') ?></td>
                    <td><?= number_format((float) $row['current_stock'], 2, ',', '.') ?></td>
                    <td><?= number_format((float) $row['available_stock'], 2, ',', '.') ?></td>
                    <td><?= number_format((float) ($row['stock_value'] ?? 0), 2, ',', '.') ?></td>
                    <td><?= number_format((float) ($row['average_cost'] ?? 0), 4, ',', '.') ?></td>
                    <td><?= $row['last_movement_at'] ? esc(date('d/m/Y H:i', strtotime($row['last_movement_at']))) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Movimientos</h2>
    <table>
        <thead><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th>Cantidad</th><th>Origen</th><th>Destino</th><th>Documento</th><th>Costo</th><th>Motivo</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td>
                    <td><?= esc($row['sku'] . ' - ' . $row['product_name']) ?></td>
                    <td><?= esc(ucfirst($row['movement_type'])) ?><?= ! empty($row['adjustment_mode']) ? ' - ' . esc($row['adjustment_mode']) : '' ?></td>
                    <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                    <td><?= esc($row['source_name'] ?: '-') ?></td>
                    <td><?= esc($row['destination_name'] ?: '-') ?></td>
                    <td><?= esc($row['source_document'] ?: '-') ?></td>
                    <td><?= $row['unit_cost'] !== null ? esc(number_format((float) $row['unit_cost'], 4, ',', '.')) : '-' ?></td>
                    <td><?= esc($row['reason'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
