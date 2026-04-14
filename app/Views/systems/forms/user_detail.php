<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-4">
            <h2 class="h5 mb-1"><?= esc($userDetail['name'] ?? '') ?></h2>
            <p class="text-secondary mb-0"><?= esc(($userDetail['username'] ?? '') . ' / ' . ($userDetail['role_name'] ?? 'Usuario')) ?></p>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Sistema</th><th>Permiso</th><th>Estado</th><th>Descripcion</th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><?= esc($assignment['system_name']) ?></td>
                        <td><?= ($assignment['access_level'] ?? 'view') === 'manage' ? 'Gestion' : 'Consulta' ?></td>
                        <td><?= (int) ($assignment['active'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?></td>
                        <td class="text-secondary small"><?= esc($assignment['description'] ?: 'Sin descripcion') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($assignments === []): ?><tr><td colspan="4" class="text-secondary">El usuario no tiene sistemas asignados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
