<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $salesLive = false; ?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="h4 mb-1">Recibo <?= esc($receipt['receipt_number'] ?? '') ?></h2>
                <p class="text-secondary mb-0">Detalle del recibo y comprobantes aplicados.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (($receipt['status'] ?? '') === 'voided'): ?>
                    <span class="badge bg-secondary fs-6 py-2 px-3">Anulado</span>
                <?php elseif (in_array($receipt['status'] ?? '', ['pending', 'partial'], true)): ?><span class="badge bg-warning text-dark">Pendiente de verificación</span>
                <?php else: ?>
                    <span class="badge bg-success fs-6 py-2 px-3">Aplicado</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (in_array($receipt['status'] ?? '', ['pending', 'partial'], true)): ?>
            <p class="alert alert-warning">Los medios confirmados ya fueron aplicados. Verifica las transferencias pendientes con su evidencia bancaria.</p>
            <?php if ($context['canManage'] && (int) ($receipt['processing_version'] ?? 1) < 2): ?>
            <form method="post" action="<?= site_url('ventas/cobranzas/' . $receipt['id'] . '/confirmar?company_id=' . $selectedCompanyId) ?>" class="d-flex gap-2 mb-3">
                <?= csrf_field() ?>
                <label class="flex-grow-1">Evidencia de verificación<input name="confirmation_note" class="form-control" required maxlength="500" placeholder="Referencia bancaria y comprobación realizada"></label>
                <button class="btn btn-outline-success icon-btn align-self-end" title="Confirmar cobro verificado" aria-label="Confirmar cobro verificado"><i class="bi bi-check2-circle"></i></button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php $paymentDetails = json_decode($receipt['payment_details'] ?? 'null', true) ?: []; ?>
        <?php if ($paymentDetails): ?>
        <div class="table-responsive mb-3"><table class="table"><thead><tr><th>Medio</th><th>Importe</th><th>Estado / referencia</th><th>Acci?n</th></tr></thead><tbody>
        <?php foreach ($paymentDetails as $line): ?>
        <tr><td><?= esc(['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia','check'=>'Cheque','qr'=>'QR'][$line['payment_method']] ?? $line['payment_method']) ?></td>
        <td><?= esc(number_format($line['amount'], 2, ',', '.') . ' ' . $receipt['currency_code']) ?></td>
        <td><?= esc($line['external_reference'] ?: $line['reference']) ?><div class="small text-secondary"><?= !empty($line['bank_settled_at']) ? 'Acreditado en banco' : (!empty($line['posted_at']) ? 'Recibido' : 'Pendiente') ?></div></td><td>
        <?php if ($context['canManage'] && $receipt['status'] !== 'voided' && (int) ($receipt['processing_version'] ?? 1) >= 2): ?>
        <?php $pending = empty($line['posted_at']); $canSettle = !$pending && empty($line['bank_settled_at']) && in_array($line['payment_method'], ['card','check','qr'], true); ?>
        <?php if ($pending || $canSettle): ?>
        <form method="post" class="d-flex gap-2" action="<?= site_url('ventas/cobranzas/' . $receipt['id'] . ($pending ? '/confirmar' : '/medios/' . $line['id'] . '/acreditar') . '?company_id=' . $selectedCompanyId . '&popup=1') ?>">
        <?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= esc($line['id']) ?>">
        <input name="<?= $pending ? 'confirmation_note' : 'evidence' ?>" class="form-control" aria-label="Evidencia bancaria" placeholder="Referencia bancaria" maxlength="500" required>
        <button class="btn btn-outline-success icon-btn" title="<?= $pending ? 'Confirmar transferencia' : 'Acreditar en banco' ?>" aria-label="<?= $pending ? 'Confirmar transferencia' : 'Acreditar en banco' ?>"><i class="bi bi-check2-circle"></i></button></form>
        <?php endif; endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
        <?php if (! empty($receipt['reversal_reason'])): ?><p>Motivo de anulación: <?= esc($receipt['reversal_reason']) ?></p><?php endif; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="border rounded-4 p-3">
                    <div class="small text-secondary">Fecha</div>
                    <div class="fw-semibold"><?= esc(! empty($receipt['issue_date']) ? date('d/m/Y H:i', strtotime($receipt['issue_date'])) : '-') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded-4 p-3">
                    <div class="small text-secondary">Cliente</div>
                    <div class="fw-semibold"><?= esc($customer['name'] ?? 'Sin cliente') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded-4 p-3">
                    <div class="small text-secondary">Metodo de pago</div>
                    <div class="fw-semibold">
                        <?php
                        $methodLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'check' => 'Cheque', 'qr' => 'QR', 'mixed' => 'Mixto', 'credit' => 'Saldo a favor'];
                        echo esc($methodLabels[$receipt['payment_method'] ?? ''] ?? ($receipt['payment_method'] ?? '-'));
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded-4 p-3">
                    <div class="small text-secondary">Importe recibido</div>
                    <div class="fw-semibold fs-5"><?= number_format((float) ($receipt['received_amount'] ?? $receipt['total_amount'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <?php if (! empty($receipt['reference']) || ! empty($receipt['external_reference'])): ?>
            <div class="row g-3 mb-4">
                <?php if (! empty($receipt['reference'])): ?>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="small text-secondary">Referencia</div>
                            <div class="fw-semibold"><?= esc($receipt['reference']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (! empty($receipt['external_reference'])): ?>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3">
                            <div class="small text-secondary">Referencia externa</div>
                            <div class="fw-semibold"><?= esc($receipt['external_reference']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (! empty($receipt['notes'])): ?>
            <div class="border rounded-4 p-3 mb-4">
                <div class="small text-secondary">Notas</div>
                <div><?= esc($receipt['notes']) ?></div>
            </div>
        <?php endif; ?>

        <h3 class="h5 mb-3">Aplicaciones del recibo</h3>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Comprobante</th><th>Cliente</th><th>Previsto / aplicado</th></tr></thead>
                <tbody>
                <?php foreach ($receiptItems as $item): ?>
                    <tr>
                        <td><?= esc($item['document_number'] ?? '-') ?></td>
                        <td><?= esc($item['customer_name'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= number_format((float) ($item['applied_amount'] ?? 0), 2, ',', '.') ?> / <?= number_format((float) ($item['settled_amount'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($receiptItems === []): ?><tr><td colspan="3" class="text-secondary">Sin comprobantes aplicados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($availableAdvance > 0 && $context['canManage']): ?>
        <section class="mt-4"><h3 class="h5">Saldo a favor: <?= number_format($availableAdvance, 2, ',', '.') . ' ' . esc($receipt['currency_code']) ?></h3>
        <p class="text-secondary">Aplicarlo cancela deuda sin volver a mover fondos de caja.</p>
        <form method="post" action="<?= site_url('ventas/cobranzas/' . $receipt['id'] . '/aplicar?company_id=' . $selectedCompanyId . '&popup=1') ?>">
        <?= csrf_field() ?><input type="hidden" name="request_key" value="<?= esc(app_uuid()) ?>">
        <div data-collection-items><label>Buscar comprobante<input class="form-control" data-collection-search></label>
        <table class="table"><thead><tr><th>Comprobante</th><th>Saldo</th><th>Aplicar</th></tr></thead><tbody>
        <?php foreach ($receivables as $row): ?><tr data-collection-row><td><?= esc($row['document_number']) ?><input type="hidden" name="items_receivable_id[]" value="<?= esc($row['id']) ?>"></td><td><?= number_format($row['balance_amount'], 2, ',', '.') ?></td><td><input type="number" class="form-control" name="items_applied_amount[]" min="0" max="<?= esc(min($availableAdvance, (float) $row['balance_amount'])) ?>" step="0.01" value="0.00" aria-label="Importe a aplicar"></td></tr><?php endforeach; ?>
        </tbody></table><div class="d-flex justify-content-end gap-2"><button type="button" data-collection-prev class="btn btn-outline-dark icon-btn" title="Anterior" aria-label="Anterior"><i class="bi bi-chevron-left"></i></button><span data-collection-page></span><button type="button" data-collection-next class="btn btn-outline-dark icon-btn" title="Siguiente" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></button></div></div>
        <button class="btn btn-dark icon-btn" title="Aplicar saldo a favor" aria-label="Aplicar saldo a favor"><i class="bi bi-check-lg"></i></button>
        </form>
        <details class="mt-3"><summary>Reintegrar saldo disponible</summary><p class="text-secondary">Registra la salida real de fondos, con caja abierta y comprobante del reintegro.</p>
        <form method="post" class="row g-2" action="<?= site_url('ventas/cobranzas/' . $receipt['id'] . '/reintegrar?company_id=' . $selectedCompanyId . '&popup=1') ?>">
        <?= csrf_field() ?><input type="hidden" name="request_key" value="<?= esc(app_uuid()) ?>">
        <label class="col-md-3">Importe<input class="form-control" type="number" name="amount" min="0.01" max="<?= esc($availableAdvance) ?>" step="0.01" required></label>
        <label class="col-md-3">Medio<select name="payment_method" class="form-select"><option value="cash">Efectivo</option><option value="transfer">Transferencia realizada</option></select></label>
        <label class="col-md-5">Motivo y comprobante<input class="form-control" name="reason" maxlength="250" required></label>
        <div class="col-md-1 align-self-end"><button class="btn btn-outline-danger icon-btn" title="Registrar reintegro" aria-label="Registrar reintegro"><i class="bi bi-arrow-return-left"></i></button></div>
        </form></details>
        </section><script src="<?= base_url('assets/js/collection-form.js') ?>" defer></script>
        <?php endif; ?>
        <div class="d-flex gap-2 pt-3">
            <?php if ($context['canManage'] && ($receipt['status'] ?? '') !== 'voided' && ($receipt['receipt_kind'] ?? '') !== 'return_credit' && (float) ($receipt['refunded_amount'] ?? 0) === 0.0): ?>
                <form method="post" action="<?= site_url('ventas/cobranzas/' . $receipt['id'] . '/anular' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" onsubmit="const reason = prompt('Motivo del reverso de fondos y aplicaciones:'); if (!reason || !reason.trim()) return false; this.elements.reason.value = reason; return confirm('Se registrarán contramovimientos de fondos y contabilidad. ¿Continuar?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="reason"><button type="submit" class="btn btn-outline-danger icon-btn" title="Anular recibo" aria-label="Anular recibo"><i class="bi bi-x-circle"></i></button>
                </form>
            <?php endif; ?>
            <?php if ($isPopup): ?>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            <?php else: ?>
                <a href="<?= site_url('ventas/cobranzas' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Volver" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
