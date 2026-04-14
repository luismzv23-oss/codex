<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="mb-4">
    <h1 class="h2 mb-1">Configuracion</h1>
    <p class="text-secondary mb-0">Parametros centrales por empresa: datos, sucursales, impuestos, monedas y numeraciones.</p>
</div>

<?php if (! empty($companies)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('configuracion') ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Empresa activa</label>
                    <select name="company_id" class="form-select">
                        <?php foreach ($companies as $companyOption): ?>
                            <option value="<?= esc($companyOption['id']) ?>" <?= ($company['id'] ?? '') === $companyOption['id'] ? 'selected' : '' ?>><?= esc($companyOption['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-dark icon-btn" title="Cambiar empresa activa" aria-label="Cambiar empresa activa"><i class="bi bi-arrow-repeat"></i></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Configuracion por sistema</h2>
                        <p class="text-secondary mb-0">La empresa centraliza su base y cada sistema consume esta configuracion de forma integrada.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">Inventario</div>
                                    <div class="small text-secondary">Depositos, alertas, stock negativo y politicas operativas.</div>
                                </div>
                                <a href="<?= site_url('inventario/configuracion?company_id=' . ($company['id'] ?? '')) ?>" class="btn btn-outline-dark btn-sm">Configuracion</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">Ventas</div>
                                    <div class="small text-secondary">Argentina ARCA, monedas habilitadas, facturacion estandar y kiosco.</div>
                                </div>
                                <a href="<?= site_url('ventas/configuracion?company_id=' . ($company['id'] ?? '')) ?>" class="btn btn-outline-dark btn-sm">Configuracion</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Datos de la empresa</h2>
                        <p class="text-secondary mb-0">Configuracion general de la empresa activa.</p>
                    </div>
                    <?php if (auth_can('settings.manage')): ?>
                        <a href="<?= site_url('configuracion/empresa/editar?company_id=' . ($company['id'] ?? '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Editar empresa" data-popup-subtitle="Actualizar datos principales de la empresa activa." title="Editar empresa" aria-label="Editar empresa"><i class="bi bi-pencil-square"></i></a>
                    <?php endif; ?>
                </div>
                <dl class="row mb-0">
                    <dt class="col-md-3">Nombre</dt><dd class="col-md-9"><?= esc($company['name'] ?? '') ?></dd>
                    <dt class="col-md-3">Razon social</dt><dd class="col-md-9"><?= esc($company['legal_name'] ?? '-') ?></dd>
                    <dt class="col-md-3">Cuit</dt><dd class="col-md-9"><?= esc($company['tax_id'] ?? '-') ?></dd>
                    <dt class="col-md-3">Moneda base</dt><dd class="col-md-9"><?= esc($company['currency_code'] ?? '-') ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h4 mb-0">Sucursales</h2>
                    <?php if (auth_can('branches.manage')): ?>
                        <a href="<?= site_url('configuracion/sucursales/nueva?company_id=' . ($company['id'] ?? '')) ?>" class="btn btn-dark btn-sm icon-btn" data-popup="true" data-popup-title="Nueva sucursal" data-popup-subtitle="Registrar una sucursal para la empresa activa." title="Nueva sucursal" aria-label="Nueva sucursal"><i class="bi bi-plus-lg"></i></a>
                    <?php endif; ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($branches as $branch): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between"><span><?= esc($branch['name']) ?> <span class="text-secondary">(<?= esc($branch['code']) ?>)</span></span><span><?= (int) $branch['active'] === 1 ? 'Activa' : 'Inactiva' ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h4 mb-0">Impuestos</h2>
                    <?php if (auth_can('taxes.manage')): ?>
                        <a href="<?= site_url('configuracion/impuestos/nuevo?company_id=' . ($company['id'] ?? '')) ?>" class="btn btn-dark btn-sm icon-btn" data-popup="true" data-popup-title="Nuevo impuesto" data-popup-subtitle="Registrar un impuesto para la empresa activa." title="Nuevo impuesto" aria-label="Nuevo impuesto"><i class="bi bi-plus-lg"></i></a>
                    <?php endif; ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($taxes as $tax): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between"><span><?= esc($tax['name']) ?> <span class="text-secondary">(<?= esc($tax['code']) ?>)</span></span><span><?= esc((string) $tax['rate']) ?>%</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h4 mb-0">Monedas</h2>
                    <?php if (auth_can('currencies.manage')): ?>
                        <a href="<?= site_url('configuracion/monedas/nueva?company_id=' . ($company['id'] ?? '')) ?>" class="btn btn-dark btn-sm icon-btn" data-popup="true" data-popup-title="Nueva moneda" data-popup-subtitle="Registrar una moneda disponible para la empresa activa." title="Nueva moneda" aria-label="Nueva moneda"><i class="bi bi-plus-lg"></i></a>
                    <?php endif; ?>
                </div>
                <?php if (! empty($currencies)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($currencies as $currency): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">
                                            <?= esc($currency['name']) ?>
                                            <span class="text-secondary">(<?= esc($currency['code']) ?>)</span>
                                        </div>
                                        <div class="small text-secondary">
                                            Simbolo: <?= esc($currency['symbol'] ?: '-') ?> | Tasa: <?= esc(number_format((float) ($currency['exchange_rate'] ?? 0), 4, ',', '.')) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php if (($company['currency_code'] ?? null) === ($currency['code'] ?? null)): ?>
                                            <span class="badge text-bg-dark d-block mb-1">Moneda base</span>
                                        <?php endif; ?>
                                        <?php if ((int) ($currency['is_default'] ?? 0) === 1): ?>
                                            <span class="badge text-bg-secondary d-block mb-1">Predeterminada</span>
                                        <?php endif; ?>
                                        <span class="small <?= (int) ($currency['active'] ?? 0) === 1 ? 'text-success' : 'text-danger' ?>">
                                            <?= (int) ($currency['active'] ?? 0) === 1 ? 'Activa' : 'Inactiva' ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-secondary">No hay monedas registradas para esta empresa.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h4 mb-0">Numeracion de comprobantes</h2>
                    <?php if (auth_can('voucher_sequences.manage')): ?>
                        <a href="<?= site_url('configuracion/numeraciones/nueva?company_id=' . ($company['id'] ?? '')) ?>" class="btn btn-dark btn-sm icon-btn" data-popup="true" data-popup-title="Nueva numeracion" data-popup-subtitle="Registrar una numeracion de comprobantes para la empresa activa." title="Nueva numeracion" aria-label="Nueva numeracion"><i class="bi bi-plus-lg"></i></a>
                    <?php endif; ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($voucherSequences as $sequence): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between"><span><?= esc($sequence['document_type']) ?> <span class="text-secondary"><?= esc($sequence['prefix'] ?? '') ?></span></span><span>#<?= esc((string) $sequence['current_number']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
