<?php
$d = $dashboard; $f = $d['filters'];
$number = static fn($n) => number_format((float)$n, 2, ',', '.');
$link = static fn($p) => site_url('inventario/kardex?' . http_build_query(['company_id'=>$selectedCompanyId,'product_id'=>$p['id']]));
$max = max(1, ...array_map(static fn($day)=>max($day),array_values($d['days'])));
$points = static function($key) use($d,$max) { $out=[];$i=0;foreach($d['days'] as $day){$out[]=round(35+$i++*720/max(1,count($d['days'])-1),1).','.round(170-140*$day[$key]/$max,1);} if(count($out)===1){$out[]='755,'.round(170-140*reset($d['days'])[$key]/$max,1);}return implode(' ',$out); };
?>
<div class="inventory-overview" id="inventory-summary-card">
    <div class="insight-section-label"><span>EXISTENCIAS ACTUALES · <?= esc($context['company']['name']) ?></span><span>Actualizado <?= esc($d['updated']) ?></span></div>
    <div class="insight-kpis">
        <?php foreach ([['out','Sin disponible','Incluye stock reservado','inventory-priorities'],['low','Bajo mínimo','Con disponible mayor a cero','inventory-priorities'],['products','Productos activos','Productos físicos del catálogo','inventory-products-table'],['reserved','Productos reservados','Con unidades comprometidas','inventory-reservations-list'],['value','Valor del inventario','Stock físico × costo del catálogo','inventory-products-table'],['movement_count','Movimientos','Operaciones del período elegido','inventory-activity']] as [$key,$label,$hint,$target]): ?>
        <a class="insight-kpi <?= $key==='out'?'insight-kpi-primary':'' ?> <?= in_array($key,['low']) && $d[$key]>0?'insight-kpi-alert':'' ?>" href="#<?= $target ?>">
            <div class="insight-kpi-label"><?= $label ?><i class="bi bi-arrow-up-right" aria-hidden="true"></i></div>
            <strong><?= $key==='value' ? $number($d[$key]) : number_format($d[$key],0,',','.') ?></strong>
            <span><?= $hint ?><?= $key==='value'?' · '.esc($context['company']['currency_code'] ?? ''):'' ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="insight-analysis-grid">
        <section class="insight-panel" id="inventory-priorities">
            <div class="insight-panel-heading"><div><span class="insight-overline">PRIORIDAD OPERATIVA</span><h2>¿Qué necesita reposición?</h2><p>Disponible = stock físico - reservas. Mínimo definido por producto.</p></div><span class="insight-tag"><?= count($d['alerts']) ?> alertas</span></div>
            <?php if (!$d['alerts']): ?><div class="insight-empty"><i class="bi bi-check-circle"></i><strong>Sin alertas de reposición</strong><span>No hay productos críticos en esta selección.</span></div><?php else: ?>
            <div class="insight-table-scroll"><table class="table align-middle"><thead><tr><th>Producto</th><th>Disponible / mínimo</th><th>Estado</th></tr></thead><tbody>
            <?php foreach(array_slice($d['alerts'],0,5) as $p): ?><tr><td><a href="<?= esc($link($p)) ?>"><?= esc($p['name']) ?> <i class="bi bi-arrow-up-right"></i></a><div class="small text-secondary"><?= esc($p['sku']) ?></div></td><td><?= $number($p['available']) ?> / <?= $number($p['min_stock']) ?><div class="small text-secondary"><?= esc($p['unit']) ?></div></td><td><span class="inventory-state <?= $p['available']<=0?'inventory-state-danger':'' ?>"><?= esc($p['state']) ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if(count($d['alerts'])>5): ?><details><summary>Ver las <?= count($d['alerts'])-5 ?> alertas restantes</summary><ul class="inventory-alert-list"><?php foreach(array_slice($d['alerts'],5) as $p): ?><li><a href="<?= esc($link($p)) ?>"><?= esc($p['name']) ?></a><span><?= $number($p['available']) ?> <?= esc($p['unit']) ?> · <?= esc($p['state']) ?></span></li><?php endforeach; ?></ul></details><?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="insight-panel" id="inventory-activity">
            <div class="insight-panel-heading"><div><span class="insight-overline">ACTIVIDAD DEL PERÍODO</span><h2>¿Cómo se mueve el inventario?</h2><p>Operaciones por día; no suma unidades de distinta medida.</p></div></div>
            <div class="inventory-legend"><span>Ingresos</span><span>Egresos</span><span>·· Otros movimientos</span></div>
            <?php if(!$d['movement_count']): ?><div class="insight-empty"><i class="bi bi-graph-up"></i><strong>Sin movimientos en este período</strong><span>Selecciona otras fechas, depósito o categoría.</span></div><?php else: ?>
            <svg class="insight-chart" viewBox="0 0 800 200" role="img" aria-label="Operaciones diarias. Valores exactos en el detalle inferior."><text x="0" y="32" font-size="12" fill="#66748b"><?= $max ?></text><text x="10" y="174" font-size="12" fill="#66748b">0</text><path d="M35 30H755 M35 100H755 M35 170H755" stroke="#e5e9f2" fill="none"/>
            <?php foreach(['ingreso'=>['#6354db',''],'egreso'=>['#bd5d24','6 4'],'otros'=>['#66748b','2 4']] as $type=>[$color,$dash]): ?><polyline points="<?= esc($points($type)) ?>" stroke="<?= $color ?>" stroke-width="3" stroke-dasharray="<?= $dash ?>" fill="none"/><?php endforeach; ?></svg>
            <?php endif; ?>
            <div class="d-flex justify-content-between small text-secondary"><span><?= esc($f['from']) ?></span><span><?= esc($f['to']) ?></span></div>
            <details class="mt-3"><summary>Ver operaciones por día</summary><div class="insight-table-scroll inventory-daily"><table class="table"><thead><tr><th>Fecha</th><th>Ingresos</th><th>Egresos</th><th>Otros</th></tr></thead><tbody><?php foreach($d['days'] as $date=>$row): ?><tr><td><?= esc($date) ?></td><td><?= $row['ingreso'] ?></td><td><?= $row['egreso'] ?></td><td><?= $row['otros'] ?></td></tr><?php endforeach; ?></tbody></table></div></details>
        </section>
    </div>
</div>
