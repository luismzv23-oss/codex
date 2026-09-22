<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
$roles = []; $companies = []; $activeCount = 0;
foreach ($users as $entry) {
    $roles[$entry['role_slug'] ?? ''] = $entry['role_name'] ?? 'Sin rol';
    $companies[$entry['company_id'] ?? ''] = $entry['company_name'] ?? 'Sin empresa';
    $activeCount += (int) $entry['active'] === 1 ? 1 : 0;
}
asort($roles); asort($companies);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/users-directory.css') ?>">
<section class="ud" id="users-directory" aria-label="Directorio de usuarios">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Usuarios</h1>
        <p class="text-secondary mb-0">Administra tu equipo, sus roles y accesos desde un solo lugar.</p>
    </div>
    <?php if ($canManageUsers): ?>
        <a href="<?= site_url('usuarios/nuevo') ?>" class="btn btn-dark ud-create" data-popup="true" data-popup-title="Nuevo usuario" data-popup-subtitle="Registrar un usuario asignado a empresa y sucursal." title="Nuevo usuario" aria-label="Nuevo usuario"><i class="bi bi-person-plus" aria-hidden="true"></i></a>
    <?php endif; ?>
</div>
<div class="ud-stats" aria-label="Resumen del directorio">
    <div><span>Usuarios</span><strong><?= count($users) ?></strong><small>En tu alcance de acceso</small></div>
    <div><span>Activos</span><strong><?= $activeCount ?></strong><small>Con acceso habilitado</small></div>
    <div><span>Inactivos</span><strong><?= count($users) - $activeCount ?></strong><small>Con acceso deshabilitado</small></div>
</div>
<div class="ud-card">
    <div class="ud-toolbar" id="ud-toolbar" hidden>
        <label class="ud-search">Buscar usuario<input type="search" id="ud-search" placeholder="Nombre, usuario, correo o sucursal..."></label>
        <label>Rol<select id="ud-role"><option value="*">Todos los roles</option><?php foreach ($roles as $value => $label): ?><option value="<?= esc($value) ?>"><?= esc($label) ?></option><?php endforeach; ?></select></label>
        <label>Empresa<select id="ud-company"><option value="*">Todas las empresas</option><?php foreach ($companies as $value => $label): ?><option value="<?= esc($value) ?>"><?= esc($label) ?></option><?php endforeach; ?></select></label>
        <label>Estado<select id="ud-state"><option value="*">Todos los estados</option><option value="1">Activos</option><option value="0">Inactivos</option></select></label>
        <button type="button" class="ud-clear" id="ud-clear" aria-label="Limpiar filtros" title="Limpiar filtros"><i class="bi bi-funnel" aria-hidden="true"></i></button>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="ud-table">
            <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Empresa</th><th>Sucursal</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr data-ud-row data-role="<?= esc($row['role_slug'] ?? '') ?>" data-company="<?= esc($row['company_id'] ?? '') ?>" data-state="<?= (int) $row['active'] === 1 ? '1' : '0' ?>">
                    <td><div class="ud-person"><span class="ud-avatar" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr(trim($row['name']), 0, 1))) ?></span><div><strong><?= esc($row['name']) ?></strong><?php if ($row['id'] === (auth_user()['id'] ?? null)): ?><small>Tu cuenta</small><?php endif; ?></div></div></td>
                    <td><?= esc($row['username']) ?><div class="small text-secondary"><?= esc($row['email']) ?></div></td>
                    <td><span class="ud-role"><?= esc($row['role_name']) ?></span></td>
                    <td><?= esc($row['company_name'] ?? '-') ?></td>
                    <td><?= esc($row['branch_name'] ?? '-') ?></td>
                    <td><span class="ud-status <?= (int) $row['active'] === 1 ? 'is-active' : '' ?>"><span aria-hidden="true">&#9679;</span> <?= (int) $row['active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
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
                <tr id="ud-empty" <?= $users ? 'hidden' : '' ?>><td colspan="7"><div class="ud-empty"><i class="bi bi-search" aria-hidden="true"></i><strong>No hay usuarios para mostrar</strong><span>Prueba con otro término o limpia los filtros.</span></div></td></tr>
            </tbody>
        </table>
    </div>
</div>
    <nav class="ud-pagination" id="ud-pagination" aria-label="Paginación de usuarios" hidden>
        <span id="ud-summary" role="status" aria-live="polite"></span>
        <div><label>Por página <select id="ud-size"><option>10</option><option>25</option><option>50</option></select></label><button type="button" id="ud-prev" aria-label="Anterior" title="Anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button><span id="ud-page"></span><button type="button" id="ud-next" aria-label="Siguiente" title="Siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button></div>
    </nav>
</section>
<script src="<?= base_url('assets/js/users-directory.js') ?>" defer></script>
<?= $this->endSection() ?>
