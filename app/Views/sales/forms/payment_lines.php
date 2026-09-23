<div class="col-12" data-payment-lines>
    <div class="d-flex justify-content-between align-items-center"><h3 class="h6">Desglose del cobro (<?= esc($currencyCode) ?>)</h3><button type="button" class="btn btn-outline-dark icon-btn" data-add-payment title="Agregar medio" aria-label="Agregar medio"><i class="bi bi-plus-lg"></i></button></div>
    <p class="small text-secondary">La suma debe coincidir con los importes a aplicar. Si incluye una transferencia, el recibo completo quedará pendiente hasta verificarla; no reducirá saldos mientras tanto.</p>
    <div data-payment-rows></div>
    <template data-payment-template>
        <div class="row g-2 mb-3" data-payment-row>
            <label class="col-md-2">Medio<select data-field="payment_method" class="form-select"><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="check">Cheque</option><option value="qr">QR</option></select></label>
            <label class="col-md-2">Importe<input data-field="amount" type="number" step="0.01" min="0.01" class="form-control" required></label>
            <label class="col-md-3">Referencia<input data-field="external_reference" class="form-control" maxlength="120"></label>
            <label class="col-md-2">Pasarela<select data-field="gateway_id" class="form-select"><option value="">Sin pasarela</option><?php foreach ($gateways as $gateway): ?><option value="<?= esc($gateway['id']) ?>"><?= esc($gateway['name']) ?></option><?php endforeach; ?></select></label>
            <label class="col-md-2">Cheque<select data-field="cash_check_id" class="form-select"><option value="">Sin cheque</option><?php foreach ($checks as $check): ?><option value="<?= esc($check['id']) ?>"><?= esc($check['check_number']) ?></option><?php endforeach; ?></select></label>
            <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-outline-danger icon-btn" data-remove-payment title="Quitar medio" aria-label="Quitar medio"><i class="bi bi-trash"></i></button></div>
        </div>
    </template>
    <noscript>Activa JavaScript para ingresar el desglose del cobro.</noscript>
</div>
<script src="<?= base_url('assets/js/receipt-payments.js') ?>" defer></script>
