<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { margin: 0 0 6px; }
        .report-title { font-size: 13px; margin: 16px 0 6px; }
        .meta, .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d9d9d9; padding: 4px 6px; }
        th { background: #f3eee8; text-align: left; }
    </style>
</head>
<body>
    <h1>Informe semanal de stock</h1>
    <div class="meta"><?= esc($company['name'] ?? '') ?> · Semana <?= esc($report['week']) ?> · Generado <?= esc($report['generated_at']) ?></div>
    <?= view('inventory/control/_report_body', ['report' => $report]) ?>
</body>
</html>
