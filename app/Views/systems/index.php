<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Sistemas</h1>
        <p class="text-secondary mb-0">Asignaciones por empresa y permisos operativos por usuario dentro del ecosistema.</p>
    </div>
    <?php if ($isSuperadmin): ?>
        <a href="<?= site_url('sistemas/nuevo') ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo sistema" data-popup-subtitle="Registrar un nuevo sistema del ecosistema." title="Nuevo sistema" aria-label="Nuevo sistema"><i class="bi bi-window-plus"></i></a>
    <?php endif; ?>
</div>

<?php if (! empty($companies)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('sistemas') ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Empresa activa</label>
                    <select name="company_id" class="form-select">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
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
                        <h2 class="h4 mb-1">Sistemas disponibles</h2>
                        <p class="text-secondary mb-0">
                            <?php if ($isSuperadmin): ?>
                                Vista global del catalogo y acceso total al ecosistema.
                            <?php elseif (($user['role_slug'] ?? null) === 'admin'): ?>
                                Sistemas funcionales asignados a tu empresa.
                            <?php else: ?>
                                Sistemas asignados a tu usuario segun permisos operativos.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php if (! empty($accessibleSystems)): ?>
                    <div class="row g-3">
                        <?php foreach ($accessibleSystems as $system): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="card h-100 border rounded-4">
                                    <div class="card-body d-flex flex-column gap-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="btn btn-outline-dark icon-btn disabled"><i class="bi <?= esc($system['icon']) ?>"></i></span>
                                                <div>
                                                    <div class="fw-semibold"><?= esc($system['name']) ?></div>
                                                    <div class="small text-secondary"><?= esc($system['slug']) ?></div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge text-bg-<?= ($system['access_level'] ?? 'view') === 'manage' ? 'dark' : 'secondary' ?>">
                                                    <?= ($system['access_level'] ?? 'view') === 'manage' ? 'Gestion' : 'Consulta' ?>
                                                </span>
                                                <?php if ($isSuperadmin && ! empty($system['id'])): ?>
                                                    <a href="<?= site_url('sistemas/' . $system['id'] . '/editar') ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar sistema" data-popup-subtitle="Actualizar catalogo y punto de entrada del sistema." title="Editar sistema" aria-label="Editar sistema"><i class="bi bi-pencil-square"></i></a>
                                                    <form method="post" action="<?= site_url('sistemas/' . $system['id'] . '/toggle') ?>" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <button class="btn btn-sm <?= (int) ($system['active'] ?? 1) === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?> icon-btn" title="<?= (int) ($system['active'] ?? 1) === 1 ? 'Deshabilitar sistema' : 'Habilitar sistema' ?>" aria-label="<?= (int) ($system['active'] ?? 1) === 1 ? 'Deshabilitar sistema' : 'Habilitar sistema' ?>">
                                                            <i class="bi <?= (int) ($system['active'] ?? 1) === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" action="<?= site_url('sistemas/' . $system['id'] . '/eliminar') ?>" class="d-inline" onsubmit="return confirm('Se eliminara el sistema y sus asignaciones. Deseas continuar?');">
                                                        <?= csrf_field() ?>
                                                        <button class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar sistema" aria-label="Eliminar sistema"><i class="bi bi-trash3"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="text-secondary small mb-0"><?= esc($system['description'] ?: 'Sistema disponible sin descripcion adicional.') ?></p>
                                        <?php if ($isSuperadmin): ?>
                                            <div class="small <?= (int) ($system['active'] ?? 1) === 1 ? 'text-success' : 'text-danger' ?>">
                                                <?= (int) ($system['active'] ?? 1) === 1 ? 'Sistema activo' : 'Sistema inactivo' ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-auto d-flex flex-wrap gap-2">
                                            <?php
                                            $canEnter = $system['entry_url'] !== '#' && (int) ($system['active'] ?? 1) === 1;
                                            $entryHref = $system['entry_url'];
                                            if ($canEnter && ! empty($selectedCompanyId) && in_array($system['slug'], ['inventario', 'ventas', 'compras', 'caja'], true)) {
                                                $entryHref .= '?company_id=' . $selectedCompanyId;
                                            }
                                            ?>
                                            <a href="<?= esc($canEnter ? $entryHref : '#') ?>" class="btn btn-outline-dark btn-sm <?= $canEnter ? '' : 'disabled' ?>" <?= $canEnter ? '' : 'aria-disabled="true"' ?>>Ingresar</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-secondary">No hay sistemas asignados para este contexto.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($selectedCompanyId && ($isSuperadmin || $canManageSystems)): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h4 mb-1">Sistemas asignados a la empresa</h2>
                            <p class="text-secondary mb-0"><?= esc($selectedCompany['name'] ?? 'Empresa activa') ?></p>
                        </div>
                        <?php if ($isSuperadmin): ?>
                            <a href="<?= site_url('sistemas/asignaciones-empresa/nueva?company_id=' . $selectedCompanyId) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Asignar sistema a empresa" data-popup-subtitle="Habilitar un sistema para la empresa seleccionada." title="Asignar sistema" aria-label="Asignar sistema"><i class="bi bi-plus-lg"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" data-codex-pagination="8">
                            <thead><tr><th>Sistema</th><th>Entrada</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($companyAssignments as $assignment): ?>
                                <tr>
                                    <td><?= esc($assignment['system_name']) ?></td>
                                    <td><?= esc($assignment['entry_url'] ?: '-') ?></td>
                                    <td><?= (int) $assignment['active'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                                    <td class="text-end">
                                        <?php if ($isSuperadmin): ?>
                                            <form method="post" action="<?= site_url('sistemas/asignaciones-empresa/' . $assignment['id'] . '/toggle') ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm <?= (int) $assignment['active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?> icon-btn" title="<?= (int) $assignment['active'] === 1 ? 'Deshabilitar asignacion' : 'Habilitar asignacion' ?>" aria-label="<?= (int) $assignment['active'] === 1 ? 'Deshabilitar asignacion' : 'Habilitar asignacion' ?>">
                                                    <i class="bi <?= (int) $assignment['active'] === 1 ? 'bi-ban' : 'bi-check-circle' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h4 mb-1">Permisos por usuario</h2>
                            <p class="text-secondary mb-0">Asignaciones funcionales dentro de los sistemas habilitados para la empresa.</p>
                        </div>
                        <a href="<?= site_url('sistemas/asignaciones-usuario/nueva?company_id=' . $selectedCompanyId) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Asignar sistema a usuario" data-popup-subtitle="Definir acceso operativo para un usuario de la empresa." title="Asignar permiso" aria-label="Asignar permiso"><i class="bi bi-plus-lg"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" data-codex-pagination="8">
                            <thead><tr><th>Usuario</th><th>Sistemas</th><th>Permiso</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($operatorAssignments as $assignment): ?>
                                <tr>
                                    <td><?= esc($assignment['user_name']) ?> <span class="small text-secondary"><?= esc($assignment['username']) ?></span></td>
                                    <td><?= ! empty($assignment['systems_count']) ? esc((string) $assignment['systems_count']) : '-' ?></td>
                                    <td><?= esc($assignment['access_summary'] ?? '-') ?></td>
                                    <td><?= esc($assignment['status_label'] ?? '-') ?></td>
                                    <td class="text-end">
                                        <?php if (! empty($assignment['has_assignments'])): ?>
                                            <a href="<?= site_url('sistemas/usuarios/' . $assignment['user_id'] . '/detalle?company_id=' . $selectedCompanyId) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Detalle de permisos" data-popup-subtitle="Sistemas funcionales asignados al usuario." title="Detalle" aria-label="Detalle"><i class="bi bi-list-ul"></i></a>
                                            <form method="post" action="<?= site_url('sistemas/usuarios/' . $assignment['user_id'] . '/toggle?company_id=' . $selectedCompanyId) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm <?= ($assignment['active_systems_count'] ?? 0) > 0 ? 'btn-outline-danger' : 'btn-outline-success' ?> icon-btn" title="<?= ($assignment['active_systems_count'] ?? 0) > 0 ? 'Deshabilitar permisos' : 'Habilitar permisos' ?>" aria-label="<?= ($assignment['active_systems_count'] ?? 0) > 0 ? 'Deshabilitar permisos' : 'Habilitar permisos' ?>">
                                                    <i class="bi <?= ($assignment['active_systems_count'] ?? 0) > 0 ? 'bi-ban' : 'bi-check-circle' ?>"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-secondary">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
