<div class="insight-section-label"><span>RESUMEN COMERCIAL</span><span>Comprobantes según filtros · Cuentas pendientes actuales</span></div>
<div class="insight-kpis">
<?php foreach (['receivable_pending'=>'Cuentas pendientes','drafts'=>'Borradores','confirmed'=>'Confirmadas','returned'=>'Con devoluciones','standard'=>'Canal estándar','kiosk'=>'Canal kiosco'] as $key=>$label): ?>
    <a class="insight-kpi <?= $key === 'receivable_pending' && $summary[$key] > 0 ? 'insight-kpi-alert' : '' ?>" href="<?= site_url(($key === 'receivable_pending' ? 'ventas/cobranzas' : 'ventas/diarios') . '?' . http_build_query(['company_id'=>$selectedCompanyId] + ($key === 'receivable_pending' ? [] : $filters))) ?>"><div class="insight-kpi-label"><?= esc($label) ?><i class="bi bi-arrow-up-right"></i></div><strong><?= esc((string)$summary[$key]) ?></strong><span><?= $key === 'receivable_pending' ? 'Saldo pendiente de cobro' : 'Cantidad de comprobantes' ?></span></a>
<?php endforeach; ?>
</div>
<section class="insight-panel mb-4"><h2>Actividad por estado</h2><p class="small text-secondary">Cantidad de comprobantes de todos los canales según los filtros seleccionados.</p>
<?php $stateCounts = array_intersect_key($summary,array_flip(['drafts','confirmed','returned','cancelled'])); $maximum = max(array_merge([1],array_values($stateCounts))); ?>
<?php foreach (['drafts'=>'Borradores','confirmed'=>'Confirmadas','returned'=>'Con devoluciones','cancelled'=>'Canceladas'] as $key=>$label): ?><div class="insight-rank"><div><span><?= esc($label) ?></span><strong><?= esc((string)$summary[$key]) ?></strong></div><div class="insight-bar"><span style="width:<?= $summary[$key] / $maximum * 100 ?>%"></span></div></div><?php endforeach; ?>
</section>
