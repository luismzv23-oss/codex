<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $salesLive=false; ?><?= view('sales/ui_start',get_defined_vars()) ?>
<style>
#price-manager .price-product-row{display:grid;grid-template-columns:minmax(0,1fr) 65px 85px 110px 140px 42px;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid #e2e6ed}
#price-manager .price-column-head{font-size:12px;color:#657593;background:#f5f7fa;margin-top:16px}
#price-manager .price-product-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
#price-manager .price-stock,#price-manager .price-current{text-align:right}
#price-manager .price-edit{width:100%;min-width:0}
@media(max-width:800px){#price-manager .price-product-row{grid-template-columns:minmax(0,1fr) 120px 42px;gap:8px}#price-manager .price-stock,#price-manager .price-current{display:none}}
@media(max-width:420px){#price-manager .price-product-row{grid-template-columns:minmax(0,1fr) 90px 38px;gap:5px}}
</style>
<div id="price-manager" data-endpoint="<?= site_url('ventas/listas-precio/productos') ?>" data-company="<?= esc($companyId,'attr') ?>" data-header="<?= csrf_header() ?>" data-csrf="<?= csrf_hash() ?>">
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<h2 class="h5">Lista de precios</h2><label for="price-search" class="form-label">Buscar producto por codigo o nombre</label><input id="price-search" class="form-control mb-3" type="search" placeholder="Buscar en todo el inventario" autocomplete="off">
<div class="d-flex align-items-end gap-2 flex-wrap"><div style="flex:1;min-width:180px"><label class="form-label" for="price-list">Lista que deseas actualizar</label><select id="price-list" class="form-select"></select></div>
<button id="price-new" class="btn btn-outline-dark icon-btn" title="Crear lista" aria-label="Crear lista"><i class="bi bi-plus-lg"></i></button><button id="price-settings" class="btn btn-outline-dark icon-btn" title="Editar lista" aria-label="Editar lista"><i class="bi bi-gear"></i></button></div>
<p class="small text-secondary mt-2">El precio se guarda en la lista seleccionada. El precio base de inventario se conserva.</p><p id="price-status" role="status"></p>
<form id="price-metadata" hidden class="border rounded-3 p-3 mb-3"><div class="row g-2"><div class="col-md-6"><label for="price-name" class="form-label">Nombre</label><input id="price-name" class="form-control" required maxlength="120"></div><div class="col-md-3"><label for="price-default" class="form-label">Predeterminada</label><select id="price-default" class="form-select"><option value="0">No</option><option value="1">Si</option></select></div><div class="col-md-3"><label for="price-active" class="form-label">Estado</label><select id="price-active" class="form-select"><option value="1">Activa</option><option value="0">Inactiva</option></select></div><div class="col-12"><label for="price-description" class="form-label">Descripcion</label><input id="price-description" class="form-control" maxlength="1000"></div></div><div class="mt-3"><button class="btn btn-dark icon-btn" title="Guardar lista" aria-label="Guardar lista"><i class="bi bi-check-lg"></i></button><button type="button" id="price-cancel" class="btn btn-outline-dark icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></button></div></form>
<div class="price-product-row price-column-head" aria-hidden="true"><span>Producto</span><span class="price-stock">Stock</span><span class="price-stock">Disponible</span><span class="price-current">Precio actual</span><span>Precio lista</span><span></span></div><div id="price-products"></div><div id="price-pages" class="d-flex justify-content-end align-items-center gap-2 mt-3"></div>
</div></div></div><script src="<?= base_url('assets/js/price-list-manager.js') ?>" defer></script>
<?= view('sales/ui_end') ?><?= $this->endSection() ?>
