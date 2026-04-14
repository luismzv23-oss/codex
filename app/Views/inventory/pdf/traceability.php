<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1, h2 { margin: 0 0 8px; }
        .meta { margin-bottom: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d9d9d9; padding: 5px 7px; vertical-align: top; }
        th { background: #f3eee8; text-align: left; }
    </style>
</head>
<body>
    <h1>Trazabilidad del producto</h1>
    <div class="meta">Empresa: <?= esc($company['name'] ?? '') ?> | Producto: <?= esc($product['sku'] . ' - ' . $product['name']) ?> | Generado: <?= esc($generatedAt) ?></div>
    <h2>Stock por deposito</h2>
    <table>
        <thead><tr><th>Deposito</th><th>Stock</th><th>Reservado</th><th>Disponible</th><th>Minimo</th></tr></thead>
        <tbody>
            <?php foreach ($stockByWarehouse as $row): ?>
                <tr>
                    <td><?= esc($row['name']) ?></td>
                    <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                    <td><?= number_format((float) ($row['reserved_quantity'] ?? 0), 2, ',', '.') ?></td>
                    <td><?= number_format((float) ($row['available_quantity'] ?? 0), 2, ',', '.') ?></td>
                    <td><?= number_format((float) $row['min_stock'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h2>Reservas activas</h2>
    <table>
        <thead><tr><th>Deposito</th><th>Cantidad</th><th>Referencia</th><th>Responsable</th><th>Fecha</th></tr></thead>
        <tbody>
            <?php foreach ($reservations as $row): ?>
                <tr>
                    <td><?= esc($row['warehouse_name']) ?></td>
                    <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                    <td><?= esc($row['reference'] ?: '-') ?></td>
                    <td><?= esc($row['reserved_by_name']) ?></td>
                    <td><?= esc(date('d/m/Y H:i', strtotime($row['reserved_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h2>Movimientos</h2>
    <table>
        <thead><tr><th>Fecha</th><th>Movimiento</th><th>Cantidad</th><th>Origen</th><th>Destino</th><th>Documento</th><th>Lote / Serie</th><th>Responsable</th><th>Motivo</th></tr></thead>
        <tbody>
            <?php foreach ($movements as $row): ?>
                <tr>
                    <td><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td>
                    <td><?= esc(ucfirst($row['movement_type'])) ?><?= ! empty($row['adjustment_mode']) ? ' - ' . esc($row['adjustment_mode']) : '' ?></td>
                    <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                    <td><?= esc($row['source_name'] ?: '-') ?></td>
                    <td><?= esc($row['destination_name'] ?: '-') ?></td>
                    <td><?= esc($row['source_document'] ?: '-') ?></td>
                    <td><?= esc($row['lot_number'] ?: '-') ?> / <?= esc($row['serial_number'] ?: '-') ?></td>
                    <td><?= esc($row['user_name']) ?></td>
                    <td><?= esc($row['reason'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
