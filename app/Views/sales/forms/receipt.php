<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">Nuevo recibo</h2>
        <p class="text-secondary mb-0">Aplica un cobro a comprobantes pendientes del mismo cliente.</p>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3 mt-1">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4"><label class="form-label">Fecha</label><input type="datetime-local" name="issue_date" class="form-control" value="<?= esc(date('Y-m-d\TH:i')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Metodo</label><select name="payment_method" class="form-select"><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="check">Cheque</option><option value="qr">QR</option><option value="mixed">Mixto</option></select></div>
            <div class="col-md-3"><label class="form-label">Gateway</label><select name="gateway_id" class="form-select"><option value="">Sin gateway</option><?php foreach ($gateways as $gateway): ?><option value="<?= esc($gateway['id']) ?>"><?= esc($gateway['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Cheque</label><select name="cash_check_id" class="form-select"><option value="">Sin cheque</option><?php foreach ($checks as $check): ?><option value="<?= esc($check['id']) ?>"><?= esc($check['check_number'] . ' / ' . $check['bank_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Referencia</label><input type="text" name="reference" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Referencia externa</label><input type="text" name="external_reference" class="form-control" placeholder="Operacion bancaria / adquirente"></div>
            <div class="col-12"><label class="form-label">Notas</label><input type="text" name="notes" class="form-control"></div>
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Comprobante</th><th>Cliente</th><th>Saldo</th><th>Aplicar</th></tr></thead>
                        <tbody>
                        <?php foreach ($receivables as $receivable): ?>
                            <tr>
                                <td><?= esc($receivable['document_number']) ?><input type="hidden" name="items_receivable_id[]" value="<?= esc($receivable['id']) ?>"></td>
                                <td><?= esc($receivable['customer_name'] ?: '-') ?></td>
                                <td><?= number_format((float) ($receivable['balance_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td><input type="number" step="0.01" min="0" max="<?= esc(number_format((float) ($receivable['balance_amount'] ?? 0), 2, '.', '')) ?>" name="items_applied_amount[]" class="form-control" value="0.00"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
