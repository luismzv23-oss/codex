<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/companies-directory.css') ?>">
<section class="cd" id="companies-directory" aria-label="Directorio de empresas">
<header class="cd-heading">
    <div>
        <h1 class="h2 mb-1">Empresas</h1>
        <p><?= $canCreate ? 'Organiza tus empresas y administra sus datos desde un solo lugar.' : 'Consulta y actualiza los datos de tu empresa.' ?></p>
    </div>
    <?php if ($canCreate): ?>
        <a href="<?= site_url('empresas/nueva') ?>" class="btn btn-dark cd-create" data-popup="true" data-popup-title="Nueva empresa" data-popup-subtitle="Registrar una nueva empresa del sistema." aria-label="Nueva empresa"><i class="bi bi-building-add" aria-hidden="true"></i></a>
    <?php endif; ?>
</header>
<div class="cd-card">
    <div class="cd-toolbar" id="cd-toolbar" hidden>
        <label class="cd-search">Buscar empresa<input type="search" id="cd-search" placeholder="Nombre, razón social o moneda…"></label>
        <label>Estado<select id="cd-state"><option value="*">Todos los estados</option><option value="1">Activas</option><option value="0">Inactivas</option></select></label>
        <button type="button" id="cd-clear" class="cd-clear" aria-label="Limpiar filtros" title="Limpiar filtros"><i class="bi bi-funnel" aria-hidden="true"></i></button>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th scope="col">Empresa</th><th scope="col">Razón social</th><th scope="col">Moneda base</th><th scope="col">Estado</th><th scope="col" class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($companies as $company): ?>
                <tr data-cd-row data-state="<?= (int) $company['active'] === 1 ? '1' : '0' ?>">
                    <td><div class="cd-company"><span class="cd-avatar" aria-hidden="true"><i class="bi bi-building"></i></span><strong><?= esc($company['name']) ?></strong></div></td>
                    <td><?= esc($company['legal_name'] ?: 'Sin razón social registrada') ?></td>
                    <td><span class="cd-currency"><?= esc($company['currency_code']) ?></span></td>
                    <td><span class="cd-status <?= (int) $company['active'] === 1 ? 'is-active' : '' ?>"><span aria-hidden="true">●</span> <?= (int) $company['active'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2">
                            <a href="<?= site_url('empresas/' . $company['id'] . '/editar') ?>" class="btn btn-sm btn-outline-dark cd-edit" data-popup="true" data-popup-title="Editar empresa" data-popup-subtitle="Actualizar datos generales y moneda base." aria-label="<?= esc('Editar ' . $company['name']) ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
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
                <tr id="cd-empty" <?= $companies ? 'hidden' : '' ?>><td colspan="5"><div class="cd-empty"><i class="bi bi-buildings" aria-hidden="true"></i><strong>No hay empresas para mostrar</strong><span><?= $companies ? 'Prueba otra búsqueda o limpia los filtros.' : 'No hay empresas disponibles para tu perfil.' ?></span></div></td></tr>
            </tbody>
        </table>
    </div>
    <nav class="cd-pagination" id="cd-pagination" aria-label="Paginación de empresas" hidden>
        <span id="cd-summary" role="status" aria-live="polite"></span>
        <div><label>Por página <select id="cd-size"><option>10</option><option>25</option><option>50</option></select></label><button type="button" id="cd-prev" aria-label="Anterior" title="Anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button><span id="cd-page"></span><button type="button" id="cd-next" aria-label="Siguiente" title="Siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button></div>
    </nav>
</div>
</section>
<script src="<?= base_url('assets/js/companies-directory.js') ?>" defer></script>
<?= $this->endSection() ?>
