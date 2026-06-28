<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">Endosar Cheque</h2>
        <p class="text-secondary mb-3">Registrar el endoso del cheque Nº <strong><?= esc($check['check_number']) ?></strong> (<?= esc($check['bank_name']) ?>) por <strong>$<?= number_format((float) $check['amount'], 2, ',', '.') ?></strong>.</p>
        
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            
            <div class="col-md-6">
                <label class="form-label">Proveedor</label>
                <select name="supplier_id" class="form-select" required>
                    <option value="">Seleccionar proveedor...</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= esc($supplier['id']) ?>"><?= esc($supplier['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Sesión de Caja activa</label>
                <select name="cash_session_id" class="form-select" required>
                    <option value="">Seleccionar caja/sesión...</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= esc($session['id']) ?>"><?= esc($session['register_name']) ?> (Abierta el <?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12">
                <label class="form-label">Notas / Motivo</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Ej. Pago de factura Nº..."></textarea>
            </div>
            
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i> Confirmar Endoso</button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
