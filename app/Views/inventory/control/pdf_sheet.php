<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { margin: 0 0 8px; }
        .meta { margin-bottom: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 7px 8px; }
        th { background: #f3eee8; text-align: left; }
        .box { width: 90px; }
        .sign { margin-top: 40px; }
    </style>
</head>
<body>
    <h1>Planilla de conteo <?= esc($count['count_number']) ?></h1>
    <div class="meta">Empresa: <?= esc($company['name'] ?? '') ?> · Depósito: <?= esc($count['warehouse_name'] ?? '') ?> · Semana <?= esc($count['week']) ?><br>
        Contar físicamente y anotar la cantidad. El stock del sistema no se informa (conteo a ciegas).</div>
    <table>
        <thead><tr><th>#</th><th>SKU</th><th>Producto</th><th>Unidad</th><th class="box">Contado</th><th>Observaciones</th></tr></thead>
        <tbody>
        <?php foreach ($count['items'] as $n => $i): ?>
            <tr><td><?= $n + 1 ?></td><td><?= esc($i['sku']) ?></td><td><?= esc($i['name']) ?></td><td><?= esc($i['unit'] ?? '') ?></td><td></td><td></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="sign">Contó: ______________________ &nbsp;&nbsp; Fecha: ____/____/______ &nbsp;&nbsp; Firma: ______________________</p>
</body>
</html>
