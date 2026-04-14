<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Dispositivo de mostrador</h2>
            <p class="text-secondary mb-0">Registrar impresora, lector o periferico operativo para POS/Kiosco.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4">
                <label class="form-label">Canal</label>
                <select name="channel" class="form-select">
                    <option value="standard">Ventas</option>
                    <option value="pos">POS</option>
                    <option value="kiosk">Kiosco</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo</label>
                <select name="device_type" class="form-select">
                    <option value="printer">Impresora</option>
                    <option value="barcode_scanner">Lector</option>
                    <option value="cash_drawer">Cajon</option>
                    <option value="pos_terminal">Terminal</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Activo</label>
                <select name="active" class="form-select">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nombre</label>
                <input type="text" name="device_name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Codigo</label>
                <input type="text" name="device_code" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Ancho de papel</label>
                <input type="text" name="paper_width" class="form-control" value="80mm">
            </div>
            <div class="col-md-4">
                <label class="form-label">Driver</label>
                <input type="text" name="driver" class="form-control" value="browser">
            </div>
            <div class="col-md-4">
                <label class="form-label">Endpoint</label>
                <input type="text" name="endpoint" class="form-control">
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
