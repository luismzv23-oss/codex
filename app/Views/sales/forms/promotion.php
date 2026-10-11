<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $salesLive=false; ?>
<?= view('sales/ui_start',get_defined_vars()) ?>
<div id="promotion-manager" data-company="<?= esc($companyId,'attr') ?>" data-endpoint="<?= site_url('ventas/promociones/productos') ?>" data-search="<?= site_url('ventas/productos/buscar') ?>" data-csrf="<?= csrf_hash() ?>" data-header="<?= csrf_header() ?>">
<div class="card rounded-4 border-0 shadow-sm mb-3"><div class="card-body p-4">
<h2 class="h5">Promociones por producto</h2><p class="text-secondary">Busca un producto, elige su nuevo precio y define cuando estara en promocion.</p>
<label class="form-label" for="promo-search">Buscar por codigo o nombre</label><input id="promo-search" class="form-control" type="search" placeholder="Escribe para buscar un producto" autocomplete="off">
<div id="promo-results" class="mt-2"></div><p id="promo-status" role="status" class="mt-2"></p>
<form id="promo-form" hidden class="mt-3"><div id="promo-product" class="p-3 rounded-3 bg-light mb-3"></div>
<div class="row g-3"><div class="col-md-4"><label class="form-label" for="promo-price">Precio promocional</label><input id="promo-price" class="form-control" type="number" min="0.01" step="0.01" required></div>
<div class="col-md-4"><label class="form-label" for="promo-start">Inicio</label><input id="promo-start" type="datetime-local" class="form-control" required></div>
<div class="col-md-4"><label class="form-label" for="promo-end">Finalizacion</label><input id="promo-end" type="datetime-local" class="form-control" required></div></div>
<p id="promo-saving" class="mt-3 text-success"></p><button class="btn btn-dark icon-btn" type="submit" title="Guardar promocion" aria-label="Guardar promocion"><i class="bi bi-check-lg"></i></button>
<button id="promo-cancel" class="btn btn-outline-dark icon-btn" type="button" title="Limpiar seleccion" aria-label="Limpiar seleccion"><i class="bi bi-x-lg"></i></button></form>
</div></div>
<div class="card rounded-4 border-0 shadow-sm"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap"><h2 class="h5 mb-0">Productos con promociones</h2><input id="promo-filter" class="form-control" type="search" placeholder="Buscar en promociones" aria-label="Buscar en promociones" style="max-width:280px"></div><div id="promo-list" class="mt-3"></div><div id="promo-pages" class="d-flex justify-content-end align-items-center gap-2 mt-3"></div></div></div>
</div><script src="<?= base_url('assets/js/product-promotions.js') ?>" defer></script>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
