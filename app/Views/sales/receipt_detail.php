<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
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
                <?php elseif (($receipt['status'] ?? '') === 'pending'): ?><span class="badge bg-warning text-dark">Pendiente de verificación</span>
                <?php else: ?>
                    <span class="badge bg-success fs-6 py-2 px-3">Aplicado</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($receipt['status'] ?? '') === 'pending'): ?>
            <p class="alert alert-warning">Este recibo no cancela deuda ni registra fondos hasta verificar todos sus medios. Los saldos se comprobarán nuevamente al confirmar.</p>
            <?php if ($context['canManage']): ?>
            <form method="post" action="<?= site_url('ventas/cobranzas/' . $receipt['id'] . '/confirmar?company_id=' . $selectedCompanyId) ?>" class="d-flex gap-2 mb-3">
                <?= csrf_field() ?>
                <label class="flex-grow-1">Evidencia de verificación<input name="confirmation_note" class="form-control" required maxlength="500" placeholder="Referencia bancaria y comprobación realizada"></label>
                <button class="btn btn-outline-success icon-btn align-self-end" title="Confirmar cobro verificado" aria-label="Confirmar cobro verificado"><i class="bi bi-check2-circle"></i></button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php $paymentDetails = json_decode($receipt['payment_details'] ?? 'null', true) ?: []; ?>
        <?php if ($paymentDetails): ?>
        <div class="table-responsive mb-3"><table class="table"><thead><tr><th>Medio</th><th>Importe</th><th>Referencia</th></tr></thead><tbody>
        <?php foreach ($paymentDetails as $line): ?><tr><td><?= esc(['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia','check'=>'Cheque','qr'=>'QR'][$line['payment_method']] ?? $line['payment_method']) ?></td><td><?= esc(number_format($line['amount'], 2, ',', '.') . ' ' . $receipt['currency_code']) ?></td><td><?= esc($line['external_reference'] ?: $line['reference']) ?></td></tr><?php endforeach; ?>
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
                        $methodLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'check' => 'Cheque', 'qr' => 'QR', 'mixed' => 'Mixto'];
                        echo esc($methodLabels[$receipt['payment_method'] ?? ''] ?? ($receipt['payment_method'] ?? '-'));
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded-4 p-3">
                    <div class="small text-secondary">Total cobrado</div>
                    <div class="fw-semibold fs-5"><?= number_format((float) ($receipt['total_amount'] ?? 0), 2, ',', '.') ?></div>
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

        <h3 class="h5 mb-3">Comprobantes aplicados</h3>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Comprobante</th><th>Cliente</th><th>Monto aplicado</th></tr></thead>
                <tbody>
                <?php foreach ($receiptItems as $item): ?>
                    <tr>
                        <td><?= esc($item['document_number'] ?? '-') ?></td>
                        <td><?= esc($item['customer_name'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= number_format((float) ($item['applied_amount'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($receiptItems === []): ?><tr><td colspan="3" class="text-secondary">Sin comprobantes aplicados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex gap-2 pt-3">
            <?php if ($context['canManage'] && ($receipt['status'] ?? '') !== 'voided'): ?>
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
<?= $this->endSection() ?>
