<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1, h2 { margin: 0 0 8px; }
        .meta { margin-bottom: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d9d9d9; padding: 6px 8px; vertical-align: top; }
        th { background: #f3eee8; text-align: left; }
    </style>
</head>
<body>
    <h1>Ficha de producto</h1>
    <div class="meta">Empresa: <?= esc($company['name'] ?? '') ?> | Generado: <?= esc($generatedAt) ?></div>
    <table>
        <tbody>
            <tr><th>SKU</th><td><?= esc($product['sku']) ?></td><th>Producto</th><td><?= esc($product['name']) ?></td></tr>
            <tr><th>Categoria</th><td><?= esc($product['category'] ?: '-') ?></td><th>Marca</th><td><?= esc($product['brand'] ?: '-') ?></td></tr>
            <tr><th>Codigo de barras</th><td><?= esc($product['barcode'] ?: '-') ?></td><th>Tipo</th><td><?= esc($product['product_type'] ?: 'simple') ?></td></tr>
            <tr><th>Unidad</th><td><?= esc($product['unit']) ?></td><th>Stock min / max</th><td><?= number_format((float) $product['min_stock'], 2, ',', '.') ?> / <?= number_format((float) ($product['max_stock'] ?? 0), 2, ',', '.') ?></td></tr>
        </tbody>
    </table>

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
        <thead><tr><th>Deposito</th><th>Cantidad</th><th>Referencia</th><th>Responsable</th></tr></thead>
        <tbody>
            <?php foreach ($reservations as $row): ?>
                <tr>
                    <td><?= esc($row['warehouse_name']) ?></td>
                    <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                    <td><?= esc($row['reference'] ?: '-') ?></td>
                    <td><?= esc($row['reserved_by_name']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
