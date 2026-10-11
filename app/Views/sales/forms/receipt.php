<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $salesLive = false; ?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">Nuevo recibo</h2>
        <p class="text-secondary mb-0">Cobra facturas pendientes o registra un anticipo para aplicar después.</p>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3 mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="request_key" value="<?= esc(old('request_key', app_uuid())) ?>">
            <label class="col-md-4">Destino del cobro<select name="receipt_kind" class="form-select" data-receipt-kind><option value="invoice">Facturas pendientes</option><option value="advance">Anticipo / saldo a favor</option></select></label>
            <label class="col-md-8">Cliente<select name="customer_id" class="form-select" data-collection-customer required><option value="">Selecciona un cliente</option><?php foreach ($customers as $customer): ?><option value="<?= esc($customer['id']) ?>"><?= esc($customer['name']) ?></option><?php endforeach; ?></select></label>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4"><label class="form-label">Fecha</label><input type="datetime-local" name="issue_date" class="form-control" value="<?= esc(date('Y-m-d\TH:i')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Referencia</label><input type="text" name="reference" class="form-control"></div>
            <?php include __DIR__ . '/payment_lines.php'; ?>
            <div class="col-12"><label class="form-label">Notas</label><input type="text" name="notes" class="form-control"></div>
            <div class="col-12" data-collection-items>
                <label class="form-label">Buscar comprobante<input class="form-control" data-collection-search></label>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Comprobante</th><th>Cliente</th><th>Saldo</th><th>Aplicar</th></tr></thead>
                        <tbody>
                        <?php foreach ($receivables as $receivable): ?>
                            <tr data-collection-row data-customer="<?= esc($receivable['customer_id']) ?>">
                                <td><?= esc($receivable['document_number']) ?><input type="hidden" name="items_receivable_id[]" value="<?= esc($receivable['id']) ?>"></td>
                                <td><?= esc($receivable['customer_name'] ?: '-') ?></td>
                                <td><?= number_format((float) ($receivable['balance_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td><input type="number" step="0.01" min="0" max="<?= esc(number_format((float) ($receivable['balance_amount'] ?? 0), 2, '.', '')) ?>" name="items_applied_amount[]" class="form-control" value="0.00"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end align-items-center gap-2 mt-3"><button type="button" data-collection-prev class="btn btn-outline-dark icon-btn" title="Anterior" aria-label="Anterior"><i class="bi bi-chevron-left"></i></button><span data-collection-page aria-live="polite"></span><button type="button" data-collection-next class="btn btn-outline-dark icon-btn" title="Siguiente" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></button></div>
            </div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn" title="Registrar cobro" aria-label="Registrar cobro"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<script src="<?= base_url('assets/js/collection-form.js') ?>" defer></script>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
