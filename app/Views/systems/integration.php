<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<header class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h2">Integración de sistemas</h1><p class="text-secondary">Verificación del contrato básico de código, rutas, dependencias y esquema. No acredita operación ni servicios externos.</p></div>
    <a href="<?= esc((new \App\Libraries\Core\ModuleNavigation())->url('sistemas',$integrationStatus['company_id'] ?? null)) ?>" class="btn btn-outline-dark icon-btn" title="Volver a Sistemas" aria-label="Volver a Sistemas"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
</header>
<?php if (!empty($integrationStatus)): ?>
<section class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
    <h2 class="h5">Entregas de integración</h2>
    <p class="small text-secondary">Empresa activa: <?= esc($integrationStatus['company_id']) ?>. Estos estados describen entregas; no sustituyen la validación de los circuitos de negocio.</p>
    <?php if (!$integrationStatus['available']): ?><p class="mb-0">Pendiente de aplicar la migración aislada de eventos de Core.</p>
    <?php else: ?><dl class="row mb-0">
        <?php foreach (['pending'=>'Pendientes','processing'=>'En proceso','retry'=>'En reintento','succeeded'=>'Completadas','dead'=>'Intentos agotados','blocked'=>'Bloqueadas'] as $state=>$label): ?>
        <div class="col-6 col-md-4 col-xl-2"><dt class="small text-secondary"><?= esc($label) ?></dt><dd class="fs-4"><?= (int)$integrationStatus['deliveries'][$state] ?></dd></div>
        <?php endforeach; ?>
    </dl>
    <?php if (!empty($integrationStatus['attention'])): ?>
    <h3 class="h6 mt-3">Entregas que requieren seguimiento</h3>
    <ul class="list-group list-group-flush">
    <?php foreach ($integrationStatus['attention'] as $delivery): ?>
        <li class="list-group-item px-0">
            <div class="d-flex flex-wrap justify-content-between gap-2"><strong><?= esc($delivery['event']) ?></strong><span><?= esc($delivery['status']) ?> · <?= (int)$delivery['attempts'] ?> intentos</span></div>
            <div class="small text-secondary text-break"><?= esc($delivery['consumer']) ?> · Entrega: <?= esc($delivery['id']) ?></div>
            <div class="small"><?= esc($delivery['last_error']) ?></div>
        </li>
    <?php endforeach; ?>
    </ul><?php endif; ?>
    <?php endif; ?>
</div></section>
<?php endif; ?>
<?php if (!$report['tracking_available']): ?>
<div class="alert alert-info">El registro de verificaciones está pendiente de la migración aislada de Core. El catálogo y los permisos siguen funcionando.</div>
<?php endif; ?>
<?php foreach ($report['errors'] as $error): ?><div class="alert alert-warning"><?= esc($error) ?></div><?php endforeach; ?>
<div class="row g-3">
<?php foreach ($report['systems'] as $system): $check=$system['technical']; ?>
    <div class="col-md-6 col-xl-4">
        <article class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h5"><?= esc($system['name']) ?></h2>
            <div class="small text-secondary mb-3"><?= esc($system['slug']) ?> · <?= esc($check['version'] ?? 'Sin versión técnica') ?></div>
            <span class="badge <?= $check['ok'] ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $check['ok'] ? 'Contrato básico verificado' : 'Requiere revisión' ?></span>
            <p class="small mt-3 mb-1"><?= empty($system['id']) ? 'Sin registro en el catálogo' : ((int)$system['active'] === 1 ? 'Activo en el catálogo' : 'Inactivo en el catálogo') ?></p>
            <p class="small text-secondary"><?= $system['current'] ? 'Verificación guardada para este manifiesto: '.esc($system['installation']['checked_at']) : 'Sin verificación guardada para esta versión del manifiesto.' ?></p>
            <?php if ($check['issues']): ?><ul class="small mb-0"><?php foreach ($check['issues'] as $issue): ?><li><?= esc($issue) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div></article>
    </div>
<?php endforeach; ?>
</div>
<?= $this->endSection() ?>
