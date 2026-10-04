<?php
$sections = [
    'index' => ['Contabilidad', 'Resumen del período y estructura del plan contable.', ''],
    'journal' => ['Libro diario', 'Revisa los asientos y los borradores pendientes de contabilizar.', '/diario'],
    'ledger' => ['Libro mayor', ($account['code'] ?? '') . ' · ' . ($account['name'] ?? ''), '/mayor/' . ($account['id'] ?? '')],
    'trial_balance' => ['Balance de comprobación', 'Consulta los débitos, créditos y saldos acumulados.', '/balance-comprobacion'],
    'balance_sheet' => ['Balance general', 'Situación patrimonial a la fecha seleccionada.', '/balance-general'],
    'income_statement' => ['Estado de resultados', 'Ingresos, egresos y resultado del período.', '/resultados'],
    'account' => ['Nueva cuenta', 'Define la estructura de tu plan contable.', '/cuentas/nueva'],
    'entry' => ['Nuevo asiento', 'Registra las líneas y verifica el equilibrio entre debe y haber.', '/asientos/nuevo'],
];
[$heading, $description, $path] = $sections[$section];
$editing = in_array($section, ['account', 'entry'], true);
$query = http_build_query(['company_id' => $selectedCompanyId]);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/accounting-dashboard.css') ?>">
<div class="insight-shell inventory-shell accounting-shell" data-accounting-page="<?= esc($section) ?>">
<?php $insidePopup = ($isPopup ?? false) || service('request')->getGet('popup') === '1'; ?>
<?php if (!$insidePopup): ?>
<header class="insight-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / CONTABILIDAD</div>
        <h1><?= esc($heading) ?></h1><p><?= esc($description) ?></p>
        <div class="insight-identity"><i class="bi bi-journal-text" aria-hidden="true"></i><span>Gestión contable · <?= esc($context['company']['name']) ?></span></div>
    </div>
    <nav class="accounting-hero-actions" aria-label="Libros y acciones contables">
        <?php foreach ([
            'index'=>['Resumen y cuentas','bi-list-columns-reverse'],
            'journal'=>['Libro diario','bi-journal-text'],
            'trial_balance'=>['Comprobación','bi-calculator'],
            'balance_sheet'=>['Balance general','bi-bar-chart'],
            'income_statement'=>['Resultados','bi-graph-up'],
        ] as $key=>[$label,$icon]): ?>
            <a class="btn btn-outline-dark icon-btn" href="<?= site_url('contabilidad' . $sections[$key][2] . '?' . $query) ?>" title="<?= esc($label) ?>" aria-label="<?= esc($label) ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>><i class="bi <?= esc($icon) ?>" aria-hidden="true"></i></a>
        <?php endforeach; ?>
        <?php if (!$editing): ?><button type="button" data-accounting-refresh class="btn btn-outline-dark icon-btn" title="Actualizar resumen" aria-label="Actualizar resumen"><i class="bi bi-arrow-clockwise"></i></button><?php endif; ?>
        <a class="btn btn-outline-dark icon-btn" href="<?= site_url('contabilidad/asientos/nuevo?' . $query) ?>" title="Nuevo asiento" aria-label="Nuevo asiento" data-popup="true" data-popup-title="Nuevo asiento"><i class="bi bi-plus-lg"></i></a>
        <a class="btn btn-outline-dark icon-btn" href="<?= site_url('contabilidad/cuentas/nueva?' . $query) ?>" title="Nueva cuenta" aria-label="Nueva cuenta" data-popup="true" data-popup-title="Nueva cuenta"><i class="bi bi-folder-plus"></i></a>
    </nav>
</header>
<?php else: ?>
<div class="popup-form-heading"><h2><?= esc($heading) ?></h2><p><?= esc($description) ?></p></div>
<?php endif; ?>

<?php if (!$editing): ?>
<form method="get" action="<?= site_url('contabilidad' . $path) ?>" class="insight-filters" id="accounting-filters">
    <label>Empresa activa
    <?php if ($section === 'ledger'): ?>
        <input type="hidden" name="company_id" value="<?= esc($selectedCompanyId) ?>"><input class="form-control" value="<?= esc($context['company']['name']) ?>" readonly>
    <?php else: ?>
        <select name="company_id" class="form-select"><?php foreach (($companies ?: [$context['company']]) as $option): ?><option value="<?= esc($option['id']) ?>" <?= $option['id'] === $selectedCompanyId ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select>
    <?php endif; ?></label>
    <?php if (isset($filters['date'])): ?>
        <label>Resumen al<input type="date" name="date" class="form-control" value="<?= esc($filters['date']) ?>" required></label>
    <?php else: ?>
        <label>Resumen desde<input type="date" name="from" class="form-control" value="<?= esc($filters['from']) ?>" required></label>
        <label>Resumen hasta<input type="date" name="to" class="form-control" value="<?= esc($filters['to']) ?>" required></label>
    <?php endif; ?>
    <?php if ($section === 'journal'): ?><label>Estado<select name="status" class="form-select"><?php foreach ([''=>'Todos','draft'=>'Borrador','posted'=>'Contabilizado'] as $value=>$label): ?><option value="<?= esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <div class="insight-filter-actions"><button class="btn insight-action" title="Aplicar filtros" aria-label="Aplicar filtros"><i class="bi bi-arrow-repeat"></i></button></div>
</form>
<div id="accounting-status" class="small text-secondary mb-2" role="status" aria-live="polite"></div>
<?php endif; ?>
<div id="accounting-content">
