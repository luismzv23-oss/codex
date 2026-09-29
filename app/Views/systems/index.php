<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/systems-workspace.css') ?>">
<div class="systems-workspace">
<header class="systems-heading">
    <div>
        <span class="systems-eyebrow">ADMINISTRACIÓN / ECOSISTEMA</span>
        <h1 class="h2 mb-1">Sistemas</h1>
        <p class="text-secondary mb-0"><?= $isSuperadmin ? 'Conecta empresas, sistemas y equipos desde un solo lugar.' : 'Accede a los sistemas de tu empresa y organiza los permisos de tu equipo.' ?></p>
    </div>
    <?php if ($isSuperadmin): ?>
        <a href="<?= site_url('sistemas/nuevo') ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo sistema" data-popup-subtitle="Registrar un nuevo sistema del ecosistema." title="Nuevo sistema" aria-label="Nuevo sistema"><i class="bi bi-window-plus"></i></a>
    <?php endif; ?>
</header>

<?php if (! empty($companies)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('sistemas') ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label" for="systems-company">Empresa activa</label>
                    <select name="company_id" id="systems-company" class="form-select">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
                </div>
                <div class="col-md-4 text-md-end"><span class="systems-context"><i class="bi bi-shield-check" aria-hidden="true"></i> Administración global</span></div>
            </form>
        </div>
    </div>
<?php elseif (! empty($selectedCompany)): ?>
    <div class="systems-company-context"><span><i class="bi bi-building" aria-hidden="true"></i> <?= esc($selectedCompany['name']) ?></span><span class="systems-context">Sistemas de tu empresa</span></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12" id="systems-available">
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
                                <div class="card h-100 border rounded-4 systems-module">
                                    <div class="card-body d-flex flex-column gap-3">
                                        <div class="systems-module-heading">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="systems-module-icon"><i class="bi <?= esc($system['icon'] ?: 'bi-grid') ?>" aria-hidden="true"></i></span>
                                                <div>
                                                    <div class="fw-semibold"><?= esc($system['name']) ?></div>
                                                    <div class="small text-secondary"><?= esc($system['slug']) ?></div>
                                                </div>
                                            </div>
                                            <div class="systems-module-status">
                                                <span class="badge text-bg-<?= ($system['access_level'] ?? 'view') === 'manage' ? 'dark' : 'secondary' ?>">
                                                    <?= ($system['access_level'] ?? 'view') === 'manage' ? 'Gestión' : 'Consulta' ?>
                                                </span>
                                                <?php if ($isSuperadmin): ?>
                                                    <div class="small <?= (int) ($system['active'] ?? 1) === 1 ? 'text-success' : 'text-danger' ?>">
                                                        <?= (int) ($system['active'] ?? 1) === 1 ? 'Sistema activo' : 'Sistema inactivo' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="text-secondary small mb-0"><?= esc($system['description'] ?: 'Sistema disponible sin descripcion adicional.') ?></p>
                                        
                                        <div class="mt-auto d-flex align-items-center justify-content-between gap-2 systems-module-footer">
                                            <?php
                                            $canEnter = $system['entry_url'] !== '#' && (int) ($system['active'] ?? 1) === 1;
                                            $baseHref = $system['entry_url'];
                                            $companyQuery = (! empty($selectedCompanyId) && in_array($system['slug'], ['inventario', 'ventas', 'compras', 'caja', 'contabilidad', 'impuestos', 'comercial'], true)) 
                                                ? '?company_id=' . $selectedCompanyId 
                                                : '';
                                            $entryHref = $canEnter ? ($baseHref . $companyQuery) : '#';
                                            ?>
                                            <span class="small text-secondary"><?= $canEnter ? 'Acceso al sistema' : 'Acceso no disponible' ?></span>
                                            <a href="<?= esc($entryHref) ?>" class="btn btn-outline-dark btn-sm icon-btn <?= $canEnter ? '' : 'disabled' ?>" title="Ingresar a <?= esc($system['name'], 'attr') ?>" aria-label="Ingresar a <?= esc($system['name'], 'attr') ?>" <?= $canEnter ? '' : 'aria-disabled="true" tabindex="-1"' ?>><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="systems-empty"><i class="bi bi-grid" aria-hidden="true"></i><strong>No hay sistemas disponibles</strong><span>No hay sistemas asignados para este contexto.</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($selectedCompanyId && ($isSuperadmin || $canManageSystems)): ?>
        <div class="col-lg-6" id="systems-company-assignments">
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

        <div class="col-lg-6" id="systems-user-permissions">
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

    <?php if ($isSuperadmin): ?>
        <div class="col-12 mt-2" id="systems-catalog">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h4 mb-1">Catálogo global de sistemas</h2>
                            <p class="text-secondary mb-0">Gestion centralizada de todos los sistemas del ecosistema.</p>
                        </div>
                        <a href="<?= site_url('sistemas/nuevo') ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo sistema" data-popup-subtitle="Registrar un nuevo sistema del ecosistema." title="Nuevo sistema" aria-label="Nuevo sistema"><i class="bi bi-window-plus"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" data-codex-pagination="10">
                            <thead><tr><th>Sistema</th><th>Entrada URL</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($catalogSystems as $system): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi <?= esc($system['icon'] ?: 'bi-grid') ?> text-secondary"></i>
                                            <div>
                                                <div class="fw-semibold"><?= esc($system['name']) ?></div>
                                                <div class="small text-secondary"><?= esc($system['slug']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= esc($system['entry_url'] ?: '-') ?></td>
                                    <td><?= (int) ($system['active'] ?? 1) === 1 ? 'Activo' : 'Inactivo' ?></td>
                                    <td class="text-end">
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
<footer class="systems-footer"><i class="bi bi-shield-check" aria-hidden="true"></i> Los accesos y las acciones disponibles respetan los permisos de tu perfil.</footer>
</div>
<?= $this->endSection() ?>
