<?php
$series = array_values($report['daily_series'] ?? []);
$maxCount = max(array_merge([1], array_map(static fn($row)=>(int)$row['orders_count'], $series)));
$points = [];
foreach ($series as $index=>$row) {
    $points[] = (30 + $index * 700 / max(1,count($series)-1)) . ',' . (170 - (int)$row['orders_count'] * 140 / $maxCount);
}
?>
<section class="insight-panel mb-4"><h2>Actividad diaria</h2><p class="small text-secondary">Cantidad de ventas por fecha registrada. Consulta los importes en el detalle de la serie diaria.</p>
<?php if (!$series): ?><div class="insight-empty"><i class="bi bi-graph-up"></i><span>No hay ventas en el período.</span></div><?php else: ?>
<svg viewBox="0 0 760 210" class="insight-chart" role="img" aria-label="Evolución de la cantidad de ventas en las fechas registradas">
    <path d="M30 30V170H730" fill="none" stroke="#e5e9f2" />
    <polyline points="<?= esc(implode(' ',$points)) ?>" fill="none" stroke="#7665bf" stroke-width="3" />
    <?php foreach ($series as $index=>$row): $xy=explode(',',$points[$index]); ?><circle cx="<?= esc($xy[0]) ?>" cy="<?= esc($xy[1]) ?>" r="3" fill="#7665bf"><title><?= esc($row['report_date'].' · '.$row['orders_count'].' ventas') ?></title></circle><?php endforeach; ?>
    <text x="30" y="198"><?= esc($series[0]['report_date']) ?></text><text x="730" y="198" text-anchor="end"><?= esc($series[count($series)-1]['report_date']) ?></text>
    <text x="10" y="25"><?= esc((string)$maxCount) ?></text>
</svg>
<?php endif; ?></section>
