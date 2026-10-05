<?php $purchaseCompanyId = $selectedCompanyId ?? $companyId; $purchaseQuery = http_build_query(['company_id'=>$purchaseCompanyId]); $purchaseEditing = $purchaseEditing ?? false; ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/purchases-dashboard.css') ?>">
<div class="insight-shell inventory-shell purchases-shell" data-purchases-page="<?= $purchaseEditing ? 'form' : 'dashboard' ?>">
<?php $insidePopup = ($isPopup ?? false) || service('request')->getGet('popup') === '1'; ?>
<?php if (!$insidePopup): ?>
<header class="insight-hero">
    <div><div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / COMPRAS</div>
        <h1><?= esc($pageTitle ?? 'Compras') ?></h1>
        <p><?= $purchaseEditing ? 'Gestiona el abastecimiento y la relación con tus proveedores.' : 'Prioriza pagos, controla pedidos y sigue las recepciones de mercadería.' ?></p>
        <div class="insight-identity"><i class="bi bi-bag-check" aria-hidden="true"></i><span>Gestión de compras · <?= esc($context['company']['name']) ?></span></div>
    </div>
    <nav class="purchases-hero-actions" aria-label="Acciones de compras">
    <?php if (!$purchaseEditing): ?><button type="button" data-purchases-refresh class="btn btn-outline-dark icon-btn" title="Actualizar resumen" aria-label="Actualizar resumen"><i class="bi bi-arrow-clockwise"></i></button><?php else: ?><a class="btn btn-outline-dark icon-btn" href="<?= site_url('compras?'.$purchaseQuery) ?>" title="Volver a Compras" aria-label="Volver a Compras"><i class="bi bi-arrow-left"></i></a><?php endif; ?>
    <?php if ($context['canManage']): ?>
    <?php foreach (['proveedores/nuevo'=>['Proveedor','person-badge'],'ordenes/nueva'=>['Orden de compra','cart-plus'],'facturas/nueva'=>['Factura proveedor','receipt'],'notas-credito/nueva'=>['Nota de crédito','file-earmark-minus']] as $route=>[$label,$icon]): ?>
        <a href="<?= site_url('compras/'.$route.'?'.$purchaseQuery) ?>" class="btn btn-outline-dark icon-btn" title="<?= esc($label) ?>" aria-label="<?= esc($label) ?>" <?= !$purchaseEditing ? 'data-popup="true"' : '' ?> data-popup-title="<?= esc($label) ?>"><i class="bi bi-<?= esc($icon) ?>" aria-hidden="true"></i></a>
    <?php endforeach; ?>
    <?php endif; ?>
    </nav>
</header>
<?php endif; ?>
<?php if (!$purchaseEditing): ?>
<form id="purchases-filters" method="get" action="<?= site_url('compras') ?>" class="insight-filters">
    <label>Empresa activa<select name="company_id" class="form-select"><?php foreach (($companies ?: [$context['company']]) as $option): ?><option value="<?= esc($option['id']) ?>" <?= $purchaseCompanyId === $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select></label>
    <label>Proveedor<select name="supplier_id" class="form-select"><option value="">Todos los proveedores</option><?php foreach ($supplierOptions as $option): ?><option value="<?= esc($option['id']) ?>" <?= $filters['supplier_id'] === $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select></label>
    <label>Resumen desde<input type="date" class="form-control" name="from" value="<?= esc($filters['from']) ?>" required></label>
    <label>Resumen hasta<input type="date" class="form-control" name="to" value="<?= esc($filters['to']) ?>" required></label>
    <div class="insight-filter-actions"><button class="btn insight-action" title="Aplicar filtros" aria-label="Aplicar filtros"><i class="bi bi-arrow-repeat"></i></button></div>
</form>
<div id="purchases-status" class="small text-secondary mb-2" role="status" aria-live="polite"></div>
<?php endif; ?>
<div id="purchases-content">
