<?php
$debit = (float)($ivaVentas['totals']['iva'] ?? 0);
$credit = (float)($ivaCompras['totals']['iva'] ?? 0);
$difference = $debit - $credit;
$money = static fn($amount)=>number_format((float)$amount,2,',','.');
$metrics = [
    ['Diferencia de IVA',$money(abs($difference)), $difference > 0 ? 'Débito mayor que crédito' : ($difference < 0 ? 'Crédito mayor que débito' : 'Débito y crédito equilibrados'),'tax-sales'],
    ['IVA Ventas',$money($debit),($ivaVentas['count'] ?? 0).' comprobantes de venta','tax-sales'],
    ['IVA Compras',$money($credit),($ivaCompras['count'] ?? 0).' comprobantes de compra','tax-purchases'],
    ['Retenciones',$money($sicoreSummary['withholdings_total'] ?? 0),($sicoreSummary['withholdings_count'] ?? 0).' aplicadas','tax-sicore'],
    ['Percepciones',$money($sicoreSummary['perceptions_total'] ?? 0),($sicoreSummary['perceptions_count'] ?? 0).' aplicadas','tax-sicore'],
    ['Comprobantes',(string)(($ivaVentas['count'] ?? 0)+($ivaCompras['count'] ?? 0)),'Ventas y compras del período','tax-sales'],
];
?>
<div class="insight-section-label"><span>RESUMEN DEL PERÍODO</span><span><?= esc($filters['from'].' — '.$filters['to']) ?></span></div>
<div class="insight-kpis">
<?php foreach ($metrics as $index=>[$label,$value,$hint,$target]): ?><a class="insight-kpi <?= $index === 0 ? 'insight-kpi-primary' : '' ?>" href="#<?= esc($target) ?>"><div class="insight-kpi-label"><?= esc($label) ?><i class="bi bi-arrow-down-right"></i></div><strong><?= esc($value) ?></strong><span><?= esc($hint) ?></span></a><?php endforeach; ?>
</div>
<section class="insight-panel mb-4"><h2>Débito y crédito de IVA</h2><p class="small text-secondary">Comparación de los importes registrados en los libros del período.</p>
<?php $scale = max(abs($debit),abs($credit),1); foreach (['IVA Ventas'=>$debit,'IVA Compras'=>$credit] as $label=>$amount): ?><div class="insight-rank"><div><span><?= esc($label) ?></span><strong><?= $money($amount) ?></strong></div><div class="insight-bar"><span style="width:<?= abs($amount)/$scale*100 ?>%"></span></div></div><?php endforeach; ?>
<span class="insight-caption">La diferencia compara únicamente el IVA de ambos libros; retenciones y percepciones se muestran por separado.</span>
</section>
