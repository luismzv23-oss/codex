<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Cliente nuevo</h2>
            <p class="text-secondary mb-0">Alta rapida de cliente para ventas, limites y condiciones comerciales.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6"><label class="form-label">Cliente</label><input type="text" name="name" class="form-control" value="<?= esc(old('name')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Razon social / Facturacion</label><input type="text" name="billing_name" class="form-control" value="<?= esc(old('billing_name')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Tipo doc.</label><select name="document_type" class="form-select"><option value="DNI">DNI</option><option value="CUIT">CUIT</option><option value="CUIL">CUIL</option><option value="PAS">Pasaporte</option></select></div>
            <div class="col-md-3"><label class="form-label">Documento</label><input type="text" name="document_number" class="form-control" value="<?= esc(old('document_number')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Perfil fiscal</label><select name="tax_profile" class="form-select"><option value="consumidor_final">Consumidor final</option><option value="responsable_inscripto">Responsable inscripto</option><option value="monotributo">Monotributo</option><option value="exento">Exento</option></select></div>
            <div class="col-md-3"><label class="form-label">Condicion IVA</label><input type="text" name="vat_condition" class="form-control" value="<?= esc(old('vat_condition')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Telefono</label><input type="text" name="phone" class="form-control" value="<?= esc(old('phone')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Sucursal</label><select name="branch_id" class="form-select"><option value="">Sin sucursal</option><?php foreach ($branches as $branch): ?><option value="<?= esc($branch['id']) ?>"><?= esc($branch['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Lista</label><select name="price_list_id" class="form-select"><option value="">Lista General</option><?php foreach (($priceLists ?? []) as $priceList): ?><option value="<?= esc($priceList['id']) ?>"><?= esc($priceList['name']) ?></option><?php endforeach; ?></select><input type="hidden" name="price_list_name" value="<?= esc(old('price_list_name', 'Lista General')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Credito</label><input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="<?= esc(old('credit_limit', '0')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Vendedor</label><select name="sales_agent_id" class="form-select"><option value="">Sin vendedor</option><?php foreach (($agents ?? []) as $agent): ?><option value="<?= esc($agent['id']) ?>"><?= esc($agent['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Zona</label><select name="sales_zone_id" class="form-select"><option value="">Sin zona</option><?php foreach (($zones ?? []) as $zone): ?><option value="<?= esc($zone['id']) ?>"><?= esc($zone['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Condicion</label><select name="sales_condition_id" class="form-select"><option value="">Sin condicion</option><?php foreach (($conditions ?? []) as $condition): ?><option value="<?= esc($condition['id']) ?>"><?= esc($condition['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Desc. %</label><input type="number" step="0.01" min="0" name="custom_discount_rate" class="form-control" value="<?= esc(old('custom_discount_rate', '0')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Plazo dias</label><input type="number" min="0" name="payment_terms_days" class="form-control" value="<?= esc(old('payment_terms_days', '0')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Estado</label><select name="active" class="form-select"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
            <div class="col-12"><label class="form-label">Direccion</label><textarea name="address" class="form-control" rows="3"><?= esc(old('address')) ?></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
