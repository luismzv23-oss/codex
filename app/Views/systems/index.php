<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/systems-workspace.css') ?>">
<div class="systems-workspace" id="systems-workspace-root">
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
    <div class="card border-0 shadow-sm rounded-4 mb-4" id="systems-company-selector-card">
        <div class="card-body">
            <form method="get" action="<?= site_url('sistemas') ?>" class="row g-3 align-items-end" id="systems-company-form">
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
    <div class="col-12" id="systems-available-container">
        <div class="card border-0 shadow-sm rounded-4" id="systems-available-card">
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
                    <div class="row g-3" id="accessible-systems-grid">
                        <?php foreach ($accessibleSystems as $system): ?>
                            <div class="col-md-6 col-xl-4 system-card-item">
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
        <div class="col-lg-6" id="systems-company-assignments-container">
            <div class="card border-0 shadow-sm rounded-4 h-100" id="systems-company-assignments-card">
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
                        <table class="table align-middle mb-0 systems-paginated-table" id="company-assignments-table" data-page-size="5">
                            <thead><tr><th>Sistema</th><th>Entrada</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($companyAssignments as $assignment): ?>
                                <tr class="systems-row">
                                    <td><?= esc($assignment['system_name']) ?></td>
                                    <td><?= esc($assignment['entry_url'] ?: '-') ?></td>
                                    <td>
                                        <span class="badge <?= (int) $assignment['active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= (int) $assignment['active'] === 1 ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($isSuperadmin): ?>
                                            <form method="post" action="<?= site_url('sistemas/asignaciones-empresa/' . $assignment['id'] . '/toggle') ?>" class="d-inline systems-async-action-form">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm <?= (int) $assignment['active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?> icon-btn" title="<?= (int) $assignment['active'] === 1 ? 'Deshabilitar asignacion' : 'Habilitar asignacion' ?>" aria-label="<?= (int) $assignment['active'] === 1 ? 'Deshabilitar asignacion' : 'Habilitar asignacion' ?>">
                                                    <i class="bi <?= (int) $assignment['active'] === 1 ? 'bi-ban' : 'bi-check-circle' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($companyAssignments)): ?>
                                <tr class="no-data-row"><td colspan="4" class="text-secondary text-center py-3">No hay sistemas asignados a esta empresa.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6" id="systems-user-permissions-container">
            <div class="card border-0 shadow-sm rounded-4 h-100" id="systems-user-permissions-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h4 mb-1">Permisos por usuario</h2>
                            <p class="text-secondary mb-0">Asignaciones funcionales dentro de los sistemas habilitados para la empresa.</p>
                        </div>
                        <a href="<?= site_url('sistemas/asignaciones-usuario/nueva?company_id=' . $selectedCompanyId) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Asignar sistema a usuario" data-popup-subtitle="Definir acceso operativo para un usuario de la empresa." title="Asignar permiso" aria-label="Asignar permiso"><i class="bi bi-plus-lg"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 systems-paginated-table" id="user-permissions-table" data-page-size="5">
                            <thead><tr><th>Usuario</th><th>Sistemas</th><th>Permiso</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($operatorAssignments as $assignment): ?>
                                <tr class="systems-row">
                                    <td><?= esc($assignment['user_name']) ?> <span class="small text-secondary"><?= esc($assignment['username']) ?></span></td>
                                    <td><?= ! empty($assignment['systems_count']) ? esc((string) $assignment['systems_count']) : '-' ?></td>
                                    <td><?= esc($assignment['access_summary'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge <?= ($assignment['active_systems_count'] ?? 0) > 0 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= esc($assignment['status_label'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if (! empty($assignment['has_assignments'])): ?>
                                            <a href="<?= site_url('sistemas/usuarios/' . $assignment['user_id'] . '/detalle?company_id=' . $selectedCompanyId) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Detalle de permisos" data-popup-subtitle="Sistemas funcionales asignados al usuario." title="Detalle" aria-label="Detalle"><i class="bi bi-list-ul"></i></a>
                                            <form method="post" action="<?= site_url('sistemas/usuarios/' . $assignment['user_id'] . '/toggle?company_id=' . $selectedCompanyId) ?>" class="d-inline systems-async-action-form">
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
                            <?php if (empty($operatorAssignments)): ?>
                                <tr class="no-data-row"><td colspan="5" class="text-secondary text-center py-3">No hay permisos de usuario registrados.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($isSuperadmin): ?>
        <div class="col-12 mt-2" id="systems-catalog-container">
            <div class="card border-0 shadow-sm rounded-4" id="systems-catalog-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h4 mb-1">Catálogo global de sistemas</h2>
                            <p class="text-secondary mb-0">Gestion centralizada de todos los sistemas del ecosistema.</p>
                        </div>
                        <a href="<?= site_url('sistemas/nuevo') ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo sistema" data-popup-subtitle="Registrar un nuevo sistema del ecosistema." title="Nuevo sistema" aria-label="Nuevo sistema"><i class="bi bi-window-plus"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 systems-paginated-table" id="catalog-systems-table" data-page-size="5">
                            <thead><tr><th>Sistema</th><th>Entrada URL</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($catalogSystems as $system): ?>
                                <tr class="systems-row">
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
                                    <td>
                                        <span class="badge <?= (int) ($system['active'] ?? 1) === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= (int) ($system['active'] ?? 1) === 1 ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= site_url('sistemas/' . $system['id'] . '/editar') ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar sistema" data-popup-subtitle="Actualizar catalogo y punto de entrada del sistema." title="Editar sistema" aria-label="Editar sistema"><i class="bi bi-pencil-square"></i></a>
                                        <form method="post" action="<?= site_url('sistemas/' . $system['id'] . '/toggle') ?>" class="d-inline systems-async-action-form">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm <?= (int) ($system['active'] ?? 1) === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?> icon-btn" title="<?= (int) ($system['active'] ?? 1) === 1 ? 'Deshabilitar sistema' : 'Habilitar sistema' ?>" aria-label="<?= (int) ($system['active'] ?? 1) === 1 ? 'Deshabilitar sistema' : 'Habilitar sistema' ?>">
                                                <i class="bi <?= (int) ($system['active'] ?? 1) === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= site_url('sistemas/' . $system['id'] . '/eliminar') ?>" class="d-inline systems-async-action-form" onsubmit="return confirm('Se eliminara el sistema y sus asignaciones. Deseas continuar?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar sistema" aria-label="Eliminar sistema"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($catalogSystems)): ?>
                                <tr class="no-data-row"><td colspan="4" class="text-secondary text-center py-3">No hay sistemas registrados en el catálogo.</td></tr>
                            <?php endif; ?>
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

<style>
    /* CODEX Pagination for Systems Dashboard */
    .codex-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 14px;
        border-top: 1px solid var(--bs-border-color, #e9ecef);
        margin-top: 14px;
    }
    .codex-pagination__summary {
        font-size: 11px;
        color: var(--bs-secondary, #6c757d);
        font-weight: 500;
    }
    .codex-pagination__controls {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .codex-pagination__pages {
        display: flex;
        align-items: center;
        gap: 3px;
    }
    .codex-pagination__btn {
        min-width: 28px;
        height: 28px;
        padding: 0 6px;
        border-radius: 7px;
        border: 1px solid var(--bs-border-color, #dee2e6);
        background: #fff;
        color: var(--bs-body-color, #212529);
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .codex-pagination__btn:hover:not(:disabled) {
        background: #f8f9fa;
        border-color: #ced4da;
        color: #212529;
    }
    .codex-pagination__btn.is-active {
        background: #212529;
        border-color: #212529;
        color: #fff;
    }
    .codex-pagination__btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const activePaginators = new Map();

    // Universal Table Pagination (5 items per page)
    function setupTablePagination(tableId, pageSize = 5) {
        const table = document.getElementById(tableId);
        if (!table) return null;

        const tableResponsive = table.closest('.table-responsive');
        if (!tableResponsive) return null;

        let paginationWrapper = tableResponsive.parentNode.querySelector(`.codex-pagination[data-for="${tableId}"]`);
        if (!paginationWrapper) {
            paginationWrapper = document.createElement('div');
            paginationWrapper.className = 'codex-pagination';
            paginationWrapper.dataset.for = tableId;
            tableResponsive.after(paginationWrapper);
        }

        let currentPage = 1;

        function render() {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr.systems-row'));
            const totalItems = rows.length;

            if (totalItems <= pageSize) {
                rows.forEach(r => {
                    r.classList.remove('d-none');
                    r.style.display = '';
                });
                paginationWrapper.innerHTML = '';
                paginationWrapper.classList.add('d-none');
                return;
            }

            paginationWrapper.classList.remove('d-none');
            const pageCount = Math.ceil(totalItems / pageSize);
            if (currentPage > pageCount) currentPage = pageCount;
            if (currentPage < 1) currentPage = 1;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            rows.forEach((row, index) => {
                const isVisible = index >= startIndex && index < endIndex;
                row.classList.toggle('d-none', !isVisible);
                row.style.display = isVisible ? '' : 'none';
            });

            paginationWrapper.innerHTML = '';

            const summary = document.createElement('div');
            summary.className = 'codex-pagination__summary';
            summary.textContent = `Mostrando ${startIndex + 1}-${Math.min(endIndex, totalItems)} de ${totalItems} registros`;

            const controls = document.createElement('div');
            controls.className = 'codex-pagination__controls';

            // First (<<)
            if (pageCount > 4) {
                const first = document.createElement('button');
                first.type = 'button';
                first.className = 'codex-pagination__btn';
                first.innerHTML = '<i class="bi bi-chevron-double-left"></i>';
                first.disabled = currentPage === 1;
                first.title = 'Primera página';
                first.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage = 1;
                        render();
                    }
                });
                controls.appendChild(first);
            }

            // Prev (<)
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'codex-pagination__btn';
            prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prev.disabled = currentPage === 1;
            prev.title = 'Página anterior';
            prev.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    render();
                }
            });
            controls.appendChild(prev);

            // Page numbers
            const pages = document.createElement('div');
            pages.className = 'codex-pagination__pages';

            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(pageCount, currentPage + 2);

            for (let p = startPage; p <= endPage; p++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `codex-pagination__btn${p === currentPage ? ' is-active' : ''}`;
                btn.textContent = String(p);
                btn.title = `Página ${p}`;
                btn.addEventListener('click', () => {
                    currentPage = p;
                    render();
                });
                pages.appendChild(btn);
            }
            controls.appendChild(pages);

            // Next (>)
            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'codex-pagination__btn';
            next.innerHTML = '<i class="bi bi-chevron-right"></i>';
            next.disabled = currentPage === pageCount;
            next.title = 'Página siguiente';
            next.addEventListener('click', () => {
                if (currentPage < pageCount) {
                    currentPage++;
                    render();
                }
            });
            controls.appendChild(next);

            // Last (>>)
            if (pageCount > 4) {
                const last = document.createElement('button');
                last.type = 'button';
                last.className = 'codex-pagination__btn';
                last.innerHTML = '<i class="bi bi-chevron-double-right"></i>';
                last.disabled = currentPage === pageCount;
                last.title = 'Última página';
                last.addEventListener('click', () => {
                    if (currentPage < pageCount) {
                        currentPage = pageCount;
                        render();
                    }
                });
                controls.appendChild(last);
            }

            paginationWrapper.appendChild(summary);
            paginationWrapper.appendChild(controls);
        }

        render();

        return {
            refresh: () => {
                currentPage = 1;
                render();
            },
            render
        };
    }

    // Initialize table paginators (5 items per page)
    function initAllTablePagination() {
        ['company-assignments-table', 'user-permissions-table', 'catalog-systems-table'].forEach(id => {
            const paginator = setupTablePagination(id, 5);
            if (paginator) {
                activePaginators.set(id, paginator);
            }
        });
    }
    initAllTablePagination();

    // Bind async action forms (toggles, deletes) without full reload
    function bindAsyncForms() {
        document.querySelectorAll('.systems-async-action-form').forEach(form => {
            form.onsubmit = async (e) => {
                e.preventDefault();
                const btn = form.querySelector('button');
                if (btn) btn.disabled = true;

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (window.showCodexToast) {
                        window.showCodexToast('Operación completada con éxito.', 'success');
                    }
                    await reloadSystemsData();
                } catch (err) {
                    console.error('Error en acción de sistemas:', err);
                    form.submit();
                }
            };
        });
    }
    bindAsyncForms();

    // Seamless AJAX refresh of the entire Systems Workspace
    let isSystemsReloading = false;
    async function reloadSystemsData() {
        if (isSystemsReloading) return;
        isSystemsReloading = true;

        try {
            const response = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('Error al recargar sistemas');

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // 1. Update Available Systems Cards Grid (all systems visible)
            const newAvail = doc.getElementById('systems-available-container');
            const curAvail = document.getElementById('systems-available-container');
            if (newAvail && curAvail) {
                curAvail.innerHTML = newAvail.innerHTML;
            }

            // 2. Update Company Assignments Table
            const newCompTable = doc.getElementById('company-assignments-table');
            const curCompTable = document.getElementById('company-assignments-table');
            if (newCompTable && curCompTable) {
                const newTbody = newCompTable.querySelector('tbody');
                const curTbody = curCompTable.querySelector('tbody');
                if (newTbody && curTbody) {
                    curTbody.innerHTML = newTbody.innerHTML;
                }
                activePaginators.get('company-assignments-table')?.refresh();
            }

            // 3. Update User Permissions Table
            const newUserTable = doc.getElementById('user-permissions-table');
            const curUserTable = document.getElementById('user-permissions-table');
            if (newUserTable && curUserTable) {
                const newTbody = newUserTable.querySelector('tbody');
                const curTbody = curUserTable.querySelector('tbody');
                if (newTbody && curTbody) {
                    curTbody.innerHTML = newTbody.innerHTML;
                }
                activePaginators.get('user-permissions-table')?.refresh();
            }

            // 4. Update Catalog Systems Table
            const newCatTable = doc.getElementById('catalog-systems-table');
            const curCatTable = document.getElementById('catalog-systems-table');
            if (newCatTable && curCatTable) {
                const newTbody = newCatTable.querySelector('tbody');
                const curTbody = curCatTable.querySelector('tbody');
                if (newTbody && curTbody) {
                    curTbody.innerHTML = newTbody.innerHTML;
                }
                activePaginators.get('catalog-systems-table')?.refresh();
            }

            // 5. Re-bind async forms & popups
            bindAsyncForms();
            if (typeof window.initCodexPopups === 'function') {
                window.initCodexPopups();
            }
        } catch (err) {
            console.error('Error al actualizar datos de sistemas:', err);
        } finally {
            isSystemsReloading = false;
        }
    }
    window.reloadSystemsData = reloadSystemsData;

    // Listen for real-time creation / modifications across systems
    window.addEventListener('codex:system-saved', () => reloadSystemsData());
    window.addEventListener('codex:item-saved', () => reloadSystemsData());
    window.addEventListener('message', (event) => {
        if (event.origin === window.location.origin && event.data && (event.data.type === 'codex-popup-saved' || event.data.type === 'codex-popup-close')) {
            reloadSystemsData();
        }
    });
});
</script>
<?= $this->endSection() ?>

