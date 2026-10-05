<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
$users = $users ?? [];
$activeCount = count(array_filter($users, static fn($row) => (int) $row['active'] === 1));
$roleOptions = [];
$companyOptions = [];
foreach ($users as $row) {
    $roleOptions[$row['role_slug'] ?? ''] = $row['role_name'] ?? 'Sin rol';
    $companyOptions[$row['company_id'] ?? ''] = $row['company_name'] ?? 'Sin empresa';
}
asort($roleOptions);
asort($companyOptions);
$isSuperadmin = (auth_user()['role_slug'] ?? '') === 'superadmin';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/users-directory.css') ?>">
<div class="insight-shell users-directory" id="users-directory">
<header class="insight-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / EQUIPO Y ACCESOS</div>
        <h1>Las personas detrás de tu operación.</h1>
        <p>Administra usuarios, roles y acceso a cada empresa desde un solo lugar.</p>
        <div class="insight-identity"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= $isSuperadmin ? 'Superadmin · directorio multiempresa' : 'Directorio de tu empresa' ?></div>
    </div>
    <?php if ($canManageUsers): ?>
        <a href="<?= site_url('usuarios/nuevo') ?>" class="btn users-create icon-btn" data-popup="true" data-popup-title="Nuevo usuario" data-popup-subtitle="Registrar un usuario asignado a empresa y sucursal." title="Nuevo usuario" aria-label="Nuevo usuario"><i class="bi bi-person-plus" aria-hidden="true"></i></a>
    <?php endif; ?>
</header>
<section class="users-overview" aria-label="Resumen del directorio">
    <div><span class="users-stat-icon"><i class="bi bi-people" aria-hidden="true"></i></span><div><span>Usuarios registrados</span><strong><?= count($users) ?></strong></div></div>
    <div><span class="users-stat-icon users-green"><i class="bi bi-person-check" aria-hidden="true"></i></span><div><span>Con acceso activo</span><strong><?= $activeCount ?></strong></div></div>
    <div><span class="users-stat-icon users-muted"><i class="bi bi-person-dash" aria-hidden="true"></i></span><div><span>Usuarios inactivos</span><strong><?= count($users) - $activeCount ?></strong></div></div>
    <div><span class="users-stat-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span><div><span>Roles presentes</span><strong><?= count($roleOptions) ?></strong></div></div>
</section>
<section class="users-panel" aria-labelledby="users-heading">
<div class="users-panel-heading"><div><span class="insight-overline">DIRECTORIO</span><h2 id="users-heading">Usuarios</h2><p>Encuentra a una persona y gestiona su acceso.</p></div><span class="insight-tag">Resumen de todos los usuarios visibles para tu perfil</span></div>
<form id="users-filters" class="users-filters" role="search">
    <label class="users-search">Buscar usuario<div><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="users-search" class="form-control" placeholder="Nombre, usuario o correo" autocomplete="off"></div></label>
    <label>Rol<select id="users-role" class="form-select"><option value="">Todos los roles</option><?php foreach ($roleOptions as $slug => $name): ?><option value="<?= esc($slug) ?>"><?= esc($name) ?></option><?php endforeach; ?></select></label>
    <label>Estado<select id="users-state" class="form-select"><option value="">Todos los estados</option><option value="1">Activo</option><option value="0">Inactivo</option></select></label>
    <?php if ($isSuperadmin): ?><label>Empresa<select id="users-company" class="form-select"><option value="*">Todas las empresas</option><?php foreach ($companyOptions as $id => $name): ?><option value="<?= esc($id) ?>"><?= esc($name) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <button type="reset" class="btn users-reset icon-btn" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
</form>
<noscript><p class="users-noscript">Activa JavaScript para buscar, filtrar y cambiar de página. Se muestran todos los usuarios.</p></noscript>
<div class="users-table-wrap">
        <table class="table align-middle mb-0 users-table">
            <thead><tr><th scope="col">Persona</th><th scope="col">Cuenta</th><th scope="col">Rol</th><th scope="col">Empresa</th><th scope="col">Sucursal</th><th scope="col">Estado</th><th scope="col" class="text-end">Acciones</th></tr></thead>
            <tbody id="users-rows">
            <?php foreach ($users as $row):
                $parts = preg_split('/\s+/u', trim($row['name']));
                $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : ''));
            ?>
                <tr data-user-row data-search="<?= esc($row['name'] . ' ' . $row['username'] . ' ' . $row['email'], 'attr') ?>" data-role="<?= esc($row['role_slug'] ?? '', 'attr') ?>" data-active="<?= (int) $row['active'] ?>" data-company="<?= esc($row['company_id'] ?? '', 'attr') ?>">
                    <td><div class="users-person"><span class="users-avatar" aria-hidden="true"><?= esc($initials) ?></span><div><strong><?= esc($row['name']) ?></strong><?php if ($row['id'] === (auth_user()['id'] ?? null)): ?><small class="users-you">Tu cuenta</small><?php endif; ?></div></div></td>
                    <td><span class="users-username">@<?= esc($row['username']) ?></span><small class="users-email"><?= esc($row['email']) ?></small></td>
                    <td><span class="users-role-badge"><?= esc($row['role_name']) ?></span></td>
                    <td><?= esc($row['company_name'] ?? 'Sin empresa') ?></td>
                    <td><span class="users-branch"><?= esc($row['branch_name'] ?? 'Sin sucursal') ?></span></td>
                    <td><span class="users-status <?= (int) $row['active'] === 1 ? 'is-active' : 'is-inactive' ?>"><span aria-hidden="true"></span><?= (int) $row['active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
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
                        <?php if (!$canManageUsers): ?><span class="users-readonly">Solo lectura</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
                <tr id="users-empty" <?= $users ? 'hidden' : '' ?>><td colspan="7"><div class="users-empty"><i class="bi bi-person-search" aria-hidden="true"></i><strong>No hay usuarios para mostrar</strong><span>Prueba otra búsqueda o limpia los filtros.</span></div></td></tr>
            </tbody>
        </table>
</div>
<div class="users-pagination" id="users-pagination" hidden>
    <span id="users-range" role="status" aria-live="polite"></span>
    <div class="users-page-controls"><label>Por página<select id="users-size" class="form-select"><option value="5">5</option><option value="10" selected>10</option><option value="20">20</option></select></label><nav aria-label="Paginación de usuarios"><button type="button" class="btn icon-btn" data-page="first" title="Primera página" aria-label="Primera página"><i class="bi bi-chevron-double-left" aria-hidden="true"></i></button><button type="button" class="btn icon-btn" data-page="prev" title="Página anterior" aria-label="Página anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button><span id="users-page"></span><button type="button" class="btn icon-btn" data-page="next" title="Página siguiente" aria-label="Página siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button><button type="button" class="btn icon-btn" data-page="last" title="Última página" aria-label="Última página"><i class="bi bi-chevron-double-right" aria-hidden="true"></i></button></nav></div>
</div>
</section>
<div class="users-footer"><i class="bi bi-lock" aria-hidden="true"></i> Las acciones disponibles dependen de los permisos de tu perfil.</div>
</div>
<script src="<?= base_url('assets/js/users-directory.js') ?>" defer></script>
<?= $this->endSection() ?>
