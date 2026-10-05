<?php $taxQuery = http_build_query(['company_id'=>$selectedCompanyId] + $filters); ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/taxes-dashboard.css') ?>">
<div class="insight-shell inventory-shell taxes-shell" data-tax-page="<?= esc($selectedCompanyId) ?>">
<?php if (!(($isPopup ?? false) || service('request')->getGet('popup') === '1')): ?>
<header class="insight-hero">
    <div><div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / IMPUESTOS</div><h1>Impuestos</h1>
        <p>Consulta los libros de IVA, revisa SICORE y exporta la información del período.</p>
        <div class="insight-identity"><i class="bi bi-percent" aria-hidden="true"></i><span>Gestión impositiva · <?= esc($context['company']['name']) ?></span></div>
    </div>
    <nav class="taxes-actions" aria-label="Libros y exportaciones de impuestos">
        <button type="button" class="btn btn-outline-dark icon-btn" data-tax-refresh title="Actualizar resumen" aria-label="Actualizar resumen"><i class="bi bi-arrow-clockwise"></i></button>
        <?php foreach (['tax-sales'=>['Libro IVA Ventas','journal-arrow-up'],'tax-purchases'=>['Libro IVA Compras','journal-arrow-down'],'tax-sicore'=>['SICORE','percent']] as $id=>[$label,$icon]): ?><a class="btn btn-outline-dark icon-btn" href="#<?= esc($id) ?>" title="<?= esc($label) ?>" aria-label="<?= esc($label) ?>"><i class="bi bi-<?= esc($icon) ?>" aria-hidden="true"></i></a><?php endforeach; ?>
        <?php foreach ([
            'iva-ventas/cbte'=>['Ventas: comprobantes TXT','file-earmark-arrow-up'],
            'iva-ventas/alicuotas'=>['Ventas: alícuotas TXT','file-earmark-spreadsheet'],
            'iva-compras/cbte'=>['Compras: comprobantes TXT','file-earmark-arrow-down'],
            'iva-compras/alicuotas'=>['Compras: alícuotas TXT','file-earmark-ruled'],
            'sicore/retenciones/txt'=>['SICORE: retenciones TXT','file-earmark-minus'],
            'sicore/percepciones/txt'=>['SICORE: percepciones TXT','file-earmark-plus'],
        ] as $path=>[$label,$icon]): ?><a class="btn btn-outline-dark icon-btn" href="<?= site_url('impuestos/'.$path.'?'.$taxQuery) ?>" title="<?= esc($label) ?>" aria-label="<?= esc($label) ?>"><i class="bi bi-<?= esc($icon) ?>" aria-hidden="true"></i></a><?php endforeach; ?>
    </nav>
</header>
<?php endif; ?>
<form method="get" action="<?= site_url('impuestos') ?>" class="insight-filters" id="tax-filters">
    <label>Empresa activa<select name="company_id" class="form-select"><?php foreach (($companies ?: [$context['company']]) as $option): ?><option value="<?= esc($option['id']) ?>" <?= $selectedCompanyId === $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select></label>
    <label>Resumen desde<input type="date" name="from" class="form-control" value="<?= esc($filters['from']) ?>" required></label>
    <label>Resumen hasta<input type="date" name="to" class="form-control" value="<?= esc($filters['to']) ?>" required></label>
    <div class="insight-filter-actions"><button class="btn insight-action" title="Aplicar filtros" aria-label="Aplicar filtros"><i class="bi bi-arrow-repeat"></i></button></div>
</form>
<div id="tax-status" class="small text-secondary mb-2" role="status" aria-live="polite"></div>
<div id="tax-content">
