<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Cierre de caja</h1>
        <p class="text-secondary mb-4">Completa el arqueo real y registra la diferencia contra el saldo esperado.</p>
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Apertura</label>
                <input type="text" class="form-control" value="<?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Saldo esperado</label>
                <?php if ($isAdmin): ?>
                    <input type="text" class="form-control" value="<?= number_format((float) $expectedAmount, 2, ',', '.') ?>" readonly>
                <?php else: ?>
                    <input type="text" class="form-control text-muted fw-semibold bg-light" value="Oculto (Arqueo Ciego)" readonly>
                <?php endif; ?>
                <input type="hidden" id="expected-amount-raw" value="<?= number_format((float) $expectedAmount, 2, '.', '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Saldo real</label>
                <input type="number" step="0.01" name="actual_closing_amount" class="form-control" value="<?= number_format((float) $expectedAmount, 2, '.', '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notas</label>
                <input type="text" name="notes" class="form-control" value="">
            </div>

            <div id="supervisor-auth-fields" class="row g-3 d-none mt-3">
                <div class="col-12">
                    <div class="alert alert-warning mb-0 p-3 rounded-3 border-0 bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-shield-lock-fill"></i> El arqueo ingresado tiene diferencias con el saldo esperado y requiere la autorización de un supervisor.
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Usuario Supervisor</label>
                    <input type="text" name="supervisor_username" id="supervisor-username" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contraseña Supervisor</label>
                    <input type="password" name="supervisor_password" id="supervisor-password" class="form-control">
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="transfer_funds" id="transfer-switch" value="1">
                    <label class="form-check-label fw-semibold" for="transfer-switch">
                        <i class="bi bi-arrow-left-right text-dark"></i> Realizar rendición / transferencia de efectivo al cerrar
                    </label>
                </div>
            </div>

            <div id="transfer-fields" class="row g-3 d-none mt-2">
                <div class="col-md-6">
                    <label class="form-label">Caja de destino</label>
                    <select name="dest_cash_register_id" class="form-select">
                        <option value="">Selecciona caja de destino...</option>
                        <?php foreach (($otherRegisters ?? []) as $reg): ?>
                            <option value="<?= esc($reg['id']) ?>"><?= esc($reg['name']) ?> (<?= esc($reg['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Monto a rendir/transferir</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="transfer_amount" class="form-control" id="transfer-amount" value="<?= number_format((float) $expectedAmount, 2, '.', '') ?>">
                    </div>
                </div>
            </div>

            <div class="col-12 d-flex gap-2 mt-4">
                <button class="btn btn-dark icon-btn" title="Cerrar caja" aria-label="Cerrar caja"><i class="bi bi-check-lg"></i></button>
                <a href="<?= site_url('caja' . (! empty($companyId) ? '?company_id=' . $companyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const switchEl = document.getElementById('transfer-switch');
            const fieldsEl = document.getElementById('transfer-fields');
            if (switchEl && fieldsEl) {
                switchEl.addEventListener('change', function() {
                    if (this.checked) {
                        fieldsEl.classList.remove('d-none');
                    } else {
                        fieldsEl.classList.add('d-none');
                    }
                });
            }

            // Supervisor override control (Blind Audit / Discrepancy detection)
            const actualClosingInput = document.querySelector('input[name="actual_closing_amount"]');
            const expectedAmountRaw = parseFloat(document.getElementById('expected-amount-raw').value) || 0;
            const supervisorAuthFields = document.getElementById('supervisor-auth-fields');
            const supervisorUsername = document.getElementById('supervisor-username');
            const supervisorPassword = document.getElementById('supervisor-password');
            const isAdmin = <?= json_encode($isAdmin) ?>;

            if (actualClosingInput && supervisorAuthFields && !isAdmin) {
                function checkDiscrepancy() {
                    const actualValue = parseFloat(actualClosingInput.value) || 0;
                    if (Math.abs(actualValue - expectedAmountRaw) > 0.01) {
                        supervisorAuthFields.classList.remove('d-none');
                        supervisorUsername.setAttribute('required', 'required');
                        supervisorPassword.setAttribute('required', 'required');
                    } else {
                        supervisorAuthFields.classList.add('d-none');
                        supervisorUsername.removeAttribute('required');
                        supervisorPassword.removeAttribute('required');
                    }
                }
                
                actualClosingInput.addEventListener('input', checkDiscrepancy);
                checkDiscrepancy();
            }
        });
        </script>
    </div>
</div>
<?= $this->endSection() ?>
