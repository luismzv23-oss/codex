<div class="card border-0 shadow-sm rounded-4 my-4"><div class="card-body p-4">
<h2 class="h5">Pagos a proveedores</h2><p class="text-secondary small">Pendientes de confirmación y últimos pagos registrados del período.</p>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Pago</th><th>Medio</th><th>Importe</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach (($supplierPayments ?? []) as $payment): ?><tr>
<td><?= esc($payment['payment_number']) ?></td><td><?= esc(\App\Libraries\PaymentIntegrityService::label($payment)) ?></td>
<td><?= esc($payment['currency_code']) ?> <?= number_format((float)$payment['amount'],2,',','.') ?></td>
<td><?= ($payment['status'] ?? 'posted')==='pending'?'Pendiente de confirmación':'Registrado' ?></td>
<td><?php if (($payment['status'] ?? 'posted')==='pending' && $context['canManage']): ?>
<a class="btn btn-outline-dark icon-btn" href="<?= site_url('compras/pagos/'.$payment['id'].'/confirmar?company_id='.$selectedCompanyId.'&popup=1') ?>" data-popup="true" data-popup-title="Confirmar pago a proveedor" title="Confirmar pago" aria-label="Confirmar pago"><i class="bi bi-check2-square"></i></a>
<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (empty($supplierPayments)): ?><tr><td colspan="5" class="text-secondary">Sin pagos registrados.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
