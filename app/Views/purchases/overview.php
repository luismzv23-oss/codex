<div class="insight-section-label"><span>PRIORIDADES DE COMPRAS</span><span>Deuda vigente y actividad del período seleccionado</span></div>
<div class="insight-kpis">
<?php foreach ([
    ['Cuentas vencidas','overdue','payables-table','Saldo vigente; vencimiento anterior a hoy'],
    ['Cuentas pendientes','payables_pending','payables-table','Deuda vigente del proveedor seleccionado'],
    ['Órdenes en borrador','orders_draft','orders-table','Órdenes emitidas en el período'],
    ['Pendientes de recepción','orders_approved','orders-table','Aprobadas o recibidas parcialmente en el período'],
    ['Recepciones','receipts','receipts-table','Ingresos registrados en el período'],
    ['Proveedores activos','suppliers','suppliers-table','Base actual del proveedor seleccionado'],
] as [$label,$key,$target,$hint]): ?>
    <a class="insight-kpi <?= $key === 'overdue' && $summary[$key] > 0 ? 'insight-kpi-alert' : '' ?>" href="#<?= esc($target) ?>"><div class="insight-kpi-label"><?= esc($label) ?><i class="bi bi-arrow-down-right"></i></div><strong><?= esc((string)$summary[$key]) ?></strong><span><?= esc($hint) ?></span></a>
<?php endforeach; ?>
</div>
<div class="insight-analysis-grid">
    <section class="insight-panel"><h2>Estado de las órdenes</h2><p class="text-secondary small">Cantidad de órdenes emitidas en el período.</p>
    <?php $maxOrders = max(array_merge([1],array_values($orderStates))); ?>
    <?php foreach ($orderStates as $state=>$count): ?><div class="insight-rank"><div><span><?= esc(['draft'=>'Borrador','approved'=>'Aprobada','received_partial'=>'Recepción parcial','received_total'=>'Recibida','cancelled'=>'Cancelada'][$state] ?? $state) ?></span><strong><?= esc((string)$count) ?></strong></div><div class="insight-bar"><span style="width:<?= $count / $maxOrders * 100 ?>%"></span></div></div><?php endforeach; ?>
    <?php if (!$orderStates): ?><div class="insight-empty"><i class="bi bi-bag"></i><span>No hay órdenes en este período.</span></div><?php endif; ?>
    </section>
    <section class="insight-panel"><h2>Saldo pendiente por moneda</h2><p class="text-secondary small">Deuda vigente, sin limitar por fecha de emisión. No incluye saldos a favor.</p>
    <?php foreach ($summary['balances'] as $currency=>$amount): ?><div class="insight-priority"><div><strong><?= esc($currency) ?></strong><p><?= number_format($amount,2,',','.') ?></p></div><a href="#payables-table" aria-label="Ver cuentas a pagar"><i class="bi bi-arrow-down-right"></i></a></div><?php endforeach; ?>
    <?php if (!$summary['balances']): ?><div class="insight-empty"><i class="bi bi-check-circle"></i><span>No hay saldo pendiente.</span></div><?php endif; ?>
    </section>
</div>
