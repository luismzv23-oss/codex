<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
    <div class="mb-3"><h2 class="h5 mb-1">Configuracion de Ventas</h2><p class="text-secondary mb-0">Ajusta ARCA, monedas y modos de facturacion para la empresa.</p></div>
    <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
        <?= csrf_field() ?>
        <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
        <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
        <div class="col-md-4"><label class="form-label">Facturacion estandar</label><select name="invoice_mode_standard_enabled" class="form-select"><option value="1" <?= (int) ($settings['invoice_mode_standard_enabled'] ?? 1) === 1 ? 'selected' : '' ?>>Activa</option><option value="0" <?= (int) ($settings['invoice_mode_standard_enabled'] ?? 1) === 0 ? 'selected' : '' ?>>Inactiva</option></select></div>
        <div class="col-md-4"><label class="form-label">Facturacion kiosco</label><select name="invoice_mode_kiosk_enabled" class="form-select"><option value="1" <?= (int) ($settings['invoice_mode_kiosk_enabled'] ?? 1) === 1 ? 'selected' : '' ?>>Activa</option><option value="0" <?= (int) ($settings['invoice_mode_kiosk_enabled'] ?? 1) === 0 ? 'selected' : '' ?>>Inactiva</option></select></div>
        <div class="col-md-4"><label class="form-label">Moneda por defecto</label><select name="default_currency_code" class="form-select"><?php foreach ($currencyOptions as $code => $label): ?><option value="<?= esc($code) ?>" <?= ($settings['default_currency_code'] ?? '') === $code ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">ARCA</label><select name="arca_enabled" class="form-select"><option value="0" <?= (int) ($settings['arca_enabled'] ?? 0) === 0 ? 'selected' : '' ?>>Pendiente</option><option value="1" <?= (int) ($settings['arca_enabled'] ?? 0) === 1 ? 'selected' : '' ?>>Activa</option></select></div>
        <div class="col-md-4"><label class="form-label">Ambiente</label><select name="arca_environment" class="form-select"><option value="homologacion" <?= ($settings['arca_environment'] ?? '') === 'homologacion' ? 'selected' : '' ?>>Homologacion</option><option value="produccion" <?= ($settings['arca_environment'] ?? '') === 'produccion' ? 'selected' : '' ?>>Produccion</option></select></div>
        <div class="col-md-4"><label class="form-label">CUIT</label><input type="text" name="arca_cuit" class="form-control" value="<?= esc($settings['arca_cuit'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Condicion IVA</label><input type="text" name="arca_iva_condition" class="form-control" value="<?= esc($settings['arca_iva_condition'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Ingresos brutos</label><input type="text" name="arca_iibb" class="form-control" value="<?= esc($settings['arca_iibb'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Inicio actividades</label><input type="date" name="arca_start_activities" class="form-control" value="<?= esc($settings['arca_start_activities'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Alias fiscal</label><input type="text" name="arca_alias" class="form-control" value="<?= esc($settings['arca_alias'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Autorizacion automatica</label><select name="arca_auto_authorize" class="form-select"><option value="0" <?= (int) ($settings['arca_auto_authorize'] ?? 0) === 0 ? 'selected' : '' ?>>Manual</option><option value="1" <?= (int) ($settings['arca_auto_authorize'] ?? 0) === 1 ? 'selected' : '' ?>>Automatica</option></select></div>
        <div class="col-md-4"><label class="form-label">Vencimiento certificado</label><input type="datetime-local" name="arca_certificate_expires_at" class="form-control" value="<?= esc(! empty($settings['arca_certificate_expires_at']) ? date('Y-m-d\TH:i', strtotime($settings['arca_certificate_expires_at'])) : '') ?>"></div>
        <div class="col-md-3"><label class="form-label">PV estandar</label><input type="number" min="1" name="point_of_sale_standard" class="form-control" value="<?= esc((string) ($settings['point_of_sale_standard'] ?? 1)) ?>"></div>
        <div class="col-md-3"><label class="form-label">PV kiosco</label><input type="number" min="1" name="point_of_sale_kiosk" class="form-control" value="<?= esc((string) ($settings['point_of_sale_kiosk'] ?? 2)) ?>"></div>
        <div class="col-md-6"><label class="form-label">Documento kiosco</label><input type="text" name="kiosk_document_label" class="form-control" value="<?= esc($settings['kiosk_document_label'] ?? 'Ticket Consumidor Final') ?>"></div>
        <div class="col-md-4"><label class="form-label">Certificado</label><input type="text" name="certificate_path" class="form-control" value="<?= esc($settings['certificate_path'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Clave privada</label><input type="text" name="private_key_path" class="form-control" value="<?= esc($settings['private_key_path'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Cache TA</label><input type="text" name="token_cache_path" class="form-control" value="<?= esc($settings['token_cache_path'] ?? '') ?>"></div>
        <?php foreach (['wsaa','wsfev1','wsmtxca','wsfexv1','wsbfev1','wsct','wsseg'] as $service): ?>
            <div class="col-md-3"><label class="form-label text-uppercase"><?= esc($service) ?></label><select name="<?= esc($service) ?>_enabled" class="form-select"><option value="1" <?= (int) ($settings[$service . '_enabled'] ?? 0) === 1 ? 'selected' : '' ?>>Habilitado</option><option value="0" <?= (int) ($settings[$service . '_enabled'] ?? 0) === 0 ? 'selected' : '' ?>>No</option></select></div>
        <?php endforeach; ?>
        <?php if (! empty($arcaReadiness['checks'])): ?>
            <div class="col-12">
                <div class="border rounded-4 p-3">
                    <div class="fw-semibold mb-2">Checklist fiscal</div>
                    <div class="row g-2">
                        <?php foreach ($arcaReadiness['checks'] as $check): ?>
                            <div class="col-md-4"><div class="small <?= ! empty($check['ok']) ? 'text-success' : 'text-danger' ?>"><?= ! empty($check['ok']) ? 'Listo' : 'Pendiente' ?>: <?= esc($check['label']) ?></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-md-4"><label class="form-label">Monedas estrictas</label><select name="strict_company_currencies" class="form-select"><option value="1" <?= (int) ($settings['strict_company_currencies'] ?? 1) === 1 ? 'selected' : '' ?>>Si</option><option value="0" <?= (int) ($settings['strict_company_currencies'] ?? 1) === 0 ? 'selected' : '' ?>>No</option></select></div>
        <div class="col-md-4"><label class="form-label">Venta con stock negativo</label><select name="allow_negative_stock_sales" class="form-select"><option value="0" <?= (int) ($settings['allow_negative_stock_sales'] ?? 0) === 0 ? 'selected' : '' ?>>No</option><option value="1" <?= (int) ($settings['allow_negative_stock_sales'] ?? 0) === 1 ? 'selected' : '' ?>>Si</option></select></div>
        <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
    </form>
</div></div>
<?= $this->endSection() ?>
