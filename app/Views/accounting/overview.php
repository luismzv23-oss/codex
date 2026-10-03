<?php
$money = static fn($value) => number_format((float)$value, 2, ',', '.');
$periodQuery = http_build_query(['company_id'=>$selectedCompanyId] + $filters);
$metrics = [
    ['Diferencia debe / haber', $money($overview['difference']), 'journal', 'Revisar equilibrio', abs($overview['difference']) >= .01 ? 'text-danger' : 'text-success'],
    ['Borradores pendientes', (string)$overview['draft'], 'journal', 'Pendientes en el período', $overview['draft'] > 0 ? 'text-warning' : ''],
    ['Asientos contabilizados', (string)$overview['posted'], 'journal', 'Registrados en el período', ''],
    ['Ingresos', $money($overview['revenue']['total']), 'income_statement', 'Asientos contabilizados', ''],
    ['Egresos', $money($overview['expenses']['total']), 'income_statement', 'Asientos contabilizados', ''],
    ['Resultado neto', $money($overview['net_income']), 'income_statement', 'Ingresos menos egresos', $overview['net_income'] < 0 ? 'text-danger' : 'text-success'],
];
?>
<div class="insight-section-label"><span>RESUMEN DEL PERÍODO</span><span>Importes contables · <?= esc($context['company']['currency_code'] ?? 'ARS') ?></span></div>
<div class="insight-kpis">
<?php foreach ($metrics as [$label,$value,$target,$hint,$color]): ?>
    <a class="insight-kpi" href="<?= site_url('contabilidad/' . ($target === 'journal' ? 'diario' : 'resultados') . '?' . $periodQuery . ($label === 'Borradores pendientes' ? '&status=draft' : '')) ?>">
        <div class="insight-kpi-label"><?= esc($label) ?><i class="bi bi-arrow-up-right"></i></div><strong class="<?= esc($color) ?>"><?= esc($value) ?></strong><span><?= esc($hint) ?></span>
    </a>
<?php endforeach; ?>
</div>
<section class="insight-panel mb-4" aria-label="Comparación de ingresos y egresos">
    <h2>Ingresos y egresos</h2><p class="text-secondary small">Comparación del período seleccionado, según asientos contabilizados.</p>
    <?php $scale = max(abs($overview['revenue']['total']), abs($overview['expenses']['total']), 1); ?>
    <?php foreach (['revenue'=>'Ingresos','expenses'=>'Egresos'] as $key=>$label): ?>
        <div class="insight-rank"><div><span><?= esc($label) ?></span><strong><?= $money($overview[$key]['total']) ?></strong></div><div class="insight-bar"><span style="width:<?= min(100, abs($overview[$key]['total']) / $scale * 100) ?>%"></span></div></div>
    <?php endforeach; ?>
</section>
<div class="insight-section-label"><span>PLAN DE CUENTAS</span><span>Abre el mayor para consultar los movimientos de una cuenta</span></div>
