<?php
$d=$dashboard;
$labels=['total'=>'Movimientos','products'=>'Productos con actividad','ingreso'=>'Ingresos','egreso'=>'Egresos','transferencia'=>'Transferencias','ajuste'=>'Ajustes'];
$max=max([1,...array_values($d['days'])]);$points=[];$i=0;
foreach($d['days'] as $n){$points[]=round(35+$i++*720/max(1,count($d['days'])-1),1).','.round(170-140*$n/$max,1);}
if(count($points)===1){$points[]='755,'.round(170-140*reset($d['days'])/$max,1);}
?>
<div class="insight-section-label"><span>RESUMEN DE LOS FILTROS APLICADOS</span><span>Actualizado <?= esc($d['updated']) ?></span></div>
<div class="insight-kpis">
<?php foreach($labels as $key=>$label): ?><a class="insight-kpi <?= $key==='total'?'insight-kpi-primary':'' ?> <?= $key==='ajuste' && $d[$key]>0?'insight-kpi-alert':'' ?>" href="<?= $key==='products'?'#kardex-products':'#kardex-movements' ?>"><div class="insight-kpi-label"><?= $label ?><i class="bi bi-arrow-up-right"></i></div><strong><?= number_format($d[$key],0,',','.') ?></strong><span><?= $key==='products'?'Productos distintos del período':($key==='ajuste'?'Operaciones para revisar':'Operaciones registradas') ?></span></a><?php endforeach; ?>
</div>
<div class="insight-analysis-grid mb-4">
    <section class="insight-panel">
        <div class="insight-panel-heading"><div><span class="insight-overline">RITMO OPERATIVO</span><h2>Actividad del Kardex</h2><p>Cantidad de operaciones <?= ($d['monthly']??false)?'por mes':'por día' ?>, sin mezclar unidades de productos.</p></div></div>
        <?php if(!$d['total']): ?><div class="insight-empty"><i class="bi bi-journal-text"></i><strong>Sin movimientos en esta selección</strong><span>Cambia las fechas o amplía los filtros.</span></div><?php else: ?>
        <svg class="insight-chart" viewBox="0 0 800 200" role="img" aria-label="Actividad del período; valores exactos en el detalle inferior."><text x="0" y="32" font-size="12" fill="#66748b"><?= $max ?></text><text x="10" y="174" font-size="12" fill="#66748b">0</text><path d="M35 30H755 M35 100H755 M35 170H755" stroke="#e5e9f2" fill="none"/><polyline points="<?= esc(implode(' ',$points)) ?>" fill="none" stroke="#6354db" stroke-width="3" stroke-linejoin="round"/></svg>
        <div class="d-flex justify-content-between small text-secondary"><span><?= esc(array_key_first($d['days'])) ?></span><span><?= esc(array_key_last($d['days'])) ?></span></div>
        <details class="mt-3"><summary>Ver cantidades por fecha</summary><div class="inventory-daily"><table class="table"><thead><tr><th>Fecha</th><th>Operaciones</th></tr></thead><tbody><?php foreach($d['days'] as $date=>$n): ?><tr><td><?= esc($date) ?></td><td><?= $n ?></td></tr><?php endforeach; ?></tbody></table></div></details>
        <?php endif; ?>
    </section>
    <section class="insight-panel">
        <div class="insight-panel-heading"><div><span class="insight-overline">TIPOS DE MOVIMIENTO</span><h2>¿Qué operaciones predominan?</h2><p>Comparación de ingresos, egresos, transferencias y ajustes.</p></div></div>
        <?php foreach(['ingreso','egreso','transferencia','ajuste'] as $type): ?><div class="kardex-type"><div class="d-flex justify-content-between"><span><?= $labels[$type] ?></span><strong><?= $d[$type] ?></strong></div><div class="kardex-bar" aria-hidden="true"><span style="width:<?= round(100*$d[$type]/max(1,$d['total']),2) ?>%"></span></div></div><?php endforeach; ?>
        <p class="small text-secondary mt-4">Las transferencias cuentan una vez por operación. Consulta cantidades y costos en el detalle por producto.</p>
    </section>
</div>
