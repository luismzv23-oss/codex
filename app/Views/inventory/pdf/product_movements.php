<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { margin: 0 0 8px; }
        .meta { margin-bottom: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d9d9d9; padding: 5px 7px; vertical-align: top; }
        th { background: #f3eee8; text-align: left; }
    </style>
</head>
<body>
    <h1>Movimientos del producto</h1>
    <div class="meta">Empresa: <?= esc($company['name'] ?? '') ?> | Producto: <?= esc($product['sku'] . ' - ' . $product['name']) ?> | Generado: <?= esc($generatedAt) ?></div>
    <table>
        <thead><tr><th>Fecha</th><th>Movimiento</th><th>Cantidad</th><th>Origen</th><th>Destino</th><th>Documento</th><th>Costo</th><th>Responsable</th><th>Motivo</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td>
                    <td><?= esc(ucfirst($row['movement_type'])) ?><?= ! empty($row['adjustment_mode']) ? ' - ' . esc($row['adjustment_mode']) : '' ?></td>
                    <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                    <td><?= esc($row['source_name'] ?: '-') ?></td>
                    <td><?= esc($row['destination_name'] ?: '-') ?></td>
                    <td><?= esc($row['source_document'] ?: '-') ?></td>
                    <td><?= $row['unit_cost'] !== null ? esc(number_format((float) $row['unit_cost'], 4, ',', '.')) : '-' ?></td>
                    <td><?= esc($row['user_name']) ?></td>
                    <td><?= esc($row['reason'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
