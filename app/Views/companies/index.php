<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Empresas</h1>
        <p class="text-secondary mb-0">Base multiempresa del sistema.</p>
    </div>
    <?php if ($canCreate): ?>
        <a href="<?= site_url('empresas/nueva') ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nueva empresa" data-popup-subtitle="Registrar una nueva empresa del sistema." title="Nueva empresa" aria-label="Nueva empresa"><i class="bi bi-building-add"></i></a>
    <?php endif; ?>
</div>
<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0" data-codex-pagination="10">
            <thead><tr><th>Nombre</th><th>Razon social</th><th>Moneda</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($companies as $company): ?>
                <tr>
                    <td><?= esc($company['name']) ?></td>
                    <td><?= esc($company['legal_name'] ?? '-') ?></td>
                    <td><?= esc($company['currency_code']) ?></td>
                    <td><?= (int) $company['active'] === 1 ? 'Activa' : 'Inactiva' ?></td>
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
    </div>
</div>
<?= $this->endSection() ?>
