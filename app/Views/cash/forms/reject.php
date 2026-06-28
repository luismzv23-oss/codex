<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">Rechazar Cheque</h2>
        <p class="text-danger mb-3">Estás a punto de registrar el rechazo del cheque Nº <strong><?= esc($check['check_number']) ?></strong> (<?= esc($check['bank_name']) ?>) por <strong>$<?= number_format((float) $check['amount'], 2, ',', '.') ?></strong>.</p>
        
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            
            <div class="col-12">
                <label class="form-label">Sesión de Caja activa (Débito de fondos)</label>
                <select name="cash_session_id" class="form-select" required>
                    <option value="">Seleccionar caja/sesión...</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= esc($session['id']) ?>"><?= esc($session['register_name']) ?> (Abierta el <?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12">
                <label class="form-label">Motivo del rechazo / Notas</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Ej. Sin fondos, firma defectuosa, etc." required></textarea>
            </div>
            
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-danger icon-btn"><i class="bi bi-exclamation-triangle"></i> Registrar Rechazo</button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
