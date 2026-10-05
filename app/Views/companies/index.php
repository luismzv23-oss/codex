<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/companies-directory.css') ?>">
<div class="companies-directory">
<header class="companies-heading">
    <div>
        <span class="companies-eyebrow">ADMINISTRACIÓN / ORGANIZACIÓN</span>
        <h1 class="h2 mb-1">Empresas</h1>
        <p class="text-secondary mb-0"><?= $canCreate ? 'Organiza las empresas que forman parte de tu operación.' : 'La información y el estado de tu empresa, en un solo lugar.' ?></p>
    </div>
    <?php if ($canCreate): ?>
        <a href="<?= site_url('empresas/nueva') ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nueva empresa" data-popup-subtitle="Registrar una nueva empresa del sistema." title="Nueva empresa" aria-label="Nueva empresa"><i class="bi bi-building-add"></i></a>
    <?php endif; ?>
</header>
<?php $activeCount = count(array_filter($companies, static fn ($item) => (int) $item['active'] === 1)); ?>
<div class="companies-overview">
    <div><span class="companies-stat-icon"><i class="bi bi-buildings" aria-hidden="true"></i></span><div><span>Empresas visibles</span><strong><?= count($companies) ?></strong></div></div>
    <div><span class="companies-stat-icon is-active"><i class="bi bi-check-circle" aria-hidden="true"></i></span><div><span>Activas</span><strong><?= $activeCount ?></strong></div></div>
    <div><span class="companies-stat-icon is-inactive"><i class="bi bi-pause-circle" aria-hidden="true"></i></span><div><span>Inactivas</span><strong><?= count($companies) - $activeCount ?></strong></div></div>
</div>
<section class="companies-panel">
    <div class="companies-panel-heading">
        <div><span class="companies-eyebrow">DIRECTORIO</span><h2><?= $canCreate ? 'Todas las empresas' : 'Tu empresa' ?></h2></div>
        <span class="companies-scope"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= $canCreate ? 'Administración global' : 'Administración de tu empresa' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0" data-codex-pagination="10">
            <thead><tr><th scope="col">Empresa</th><th scope="col">Razón social</th><th scope="col">Moneda base</th><th scope="col">Estado</th><th scope="col" class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($companies as $company): ?>
                <tr>
                    <td><div class="companies-identity"><span class="companies-avatar"><i class="bi bi-building" aria-hidden="true"></i></span><div><strong><?= esc($company['name']) ?></strong><span class="companies-tax-id">CUIT: <?= esc(trim((string) ($company['tax_id'] ?? '')) ?: 'Sin registrar') ?></span></div></div></td>
                    <td><?= esc($company['legal_name'] ?? '-') ?></td>
                    <td><span class="companies-currency"><?= esc($company['currency_code']) ?></span></td>
                    <td><span class="companies-status <?= (int) $company['active'] === 1 ? 'is-active' : 'is-inactive' ?>"><span aria-hidden="true"></span><?= (int) $company['active'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2">
                            <a href="<?= site_url('empresas/' . $company['id'] . '/editar') ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar empresa" data-popup-subtitle="Actualizar datos generales y moneda base." title="Editar empresa" aria-label="Editar empresa"><i class="bi bi-pencil-square"></i></a>
                            <?php if ($canDisable): ?>
                                <form method="post" action="<?= site_url('empresas/' . $company['id'] . '/toggle') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm <?= (int) $company['active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?> icon-btn" title="<?= (int) $company['active'] === 1 ? 'Deshabilitar empresa' : 'Habilitar empresa' ?>" aria-label="<?= (int) $company['active'] === 1 ? 'Deshabilitar empresa' : 'Habilitar empresa' ?>">
                                        <i class="bi <?= (int) $company['active'] === 1 ? 'bi-ban' : 'bi-check-circle' ?>"></i>
                                    </button>
                                </form>
                                <form method="post" action="<?= site_url('empresas/' . $company['id'] . '/eliminar') ?>" class="d-inline" onsubmit="return confirm('Se eliminara la empresa y todos sus datos relacionados. Deseas continuar?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar empresa" aria-label="Eliminar empresa">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($companies)): ?>
            <div class="companies-empty"><i class="bi bi-buildings" aria-hidden="true"></i><strong>No hay empresas disponibles</strong><span><?= $canCreate ? 'Usa el botón superior para registrar la primera empresa.' : 'No tienes una empresa disponible para administrar.' ?></span></div>
        <?php endif; ?>
    </div>
</section>
<footer class="companies-footer"><i class="bi bi-lock" aria-hidden="true"></i> Las acciones disponibles respetan los permisos de tu perfil.</footer>
</div>
<?= $this->endSection() ?>
