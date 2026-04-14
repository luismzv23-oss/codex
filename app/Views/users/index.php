<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Usuarios</h1>
        <p class="text-secondary mb-0">Gestion de acceso, roles y operacion por empresa.</p>
    </div>
    <?php if ($canManageUsers): ?>
        <a href="<?= site_url('usuarios/nuevo') ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo usuario" data-popup-subtitle="Registrar un usuario asignado a empresa y sucursal." title="Nuevo usuario" aria-label="Nuevo usuario"><i class="bi bi-person-plus"></i></a>
    <?php endif; ?>
</div>
<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0" data-codex-pagination="10">
            <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Empresa</th><th>Sucursal</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><?= esc($row['name']) ?></td>
                    <td><?= esc($row['username']) ?><div class="small text-secondary"><?= esc($row['email']) ?></div></td>
                    <td><?= esc($row['role_name']) ?></td>
                    <td><?= esc($row['company_name'] ?? '-') ?></td>
                    <td><?= esc($row['branch_name'] ?? '-') ?></td>
                    <td><?= (int) $row['active'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                    <td class="text-end">
                        <?php if ($canManageUsers): ?>
                            <div class="d-inline-flex gap-2">
                                <a href="<?= site_url('usuarios/' . $row['id'] . '/editar') ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar usuario" data-popup-subtitle="Modificar datos, rol y asignacion del usuario." title="Editar usuario" aria-label="Editar usuario"><i class="bi bi-pencil-square"></i></a>
                                <?php if ($canDisableOrDeleteUsers && $row['id'] !== (auth_user()['id'] ?? null)): ?>
                                    <form method="post" action="<?= site_url('usuarios/' . $row['id'] . '/toggle') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm <?= (int) $row['active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?> icon-btn" title="<?= (int) $row['active'] === 1 ? 'Deshabilitar usuario' : 'Habilitar usuario' ?>" aria-label="<?= (int) $row['active'] === 1 ? 'Deshabilitar usuario' : 'Habilitar usuario' ?>">
                                            <i class="bi <?= (int) $row['active'] === 1 ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= site_url('usuarios/' . $row['id'] . '/eliminar') ?>" class="d-inline" onsubmit="return confirm('Se eliminara este usuario. Continuar?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar usuario" aria-label="Eliminar usuario">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
