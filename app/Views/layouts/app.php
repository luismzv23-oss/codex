<?php
$request = service('request');
$isPopup = $request->getGet('popup') === '1';

// Fetch accessible systems and active context
$switcherSystems = [];
$activeCompanyName = '';
$currentUri = uri_string();
$reqCompanyId = service('request')->getGet('company_id') ?: '';

if (auth_check() && !$isPopup) {
    $userId = auth_user()['id'] ?? '';
    $roleSlug = auth_user()['role_slug'] ?? '';
    $companyId = auth_user()['company_id'] ?? '';
    $db = \Config\Database::connect();
    
    // Resolve active company name
    $selectedCompanyId = $reqCompanyId ?: $companyId;
    if (!empty($selectedCompanyId)) {
        $cRow = $db->table('companies')->select('id, name')->where('id', $selectedCompanyId)->get()->getRowArray();
        $activeCompanyName = $cRow['name'] ?? '';
    }

    if (!empty($userId)) {
        if ($roleSlug === 'superadmin') {
            if (!empty($selectedCompanyId)) {
                $rows = $db->table('systems')
                    ->select('systems.*')
                    ->join('company_systems', 'company_systems.system_id = systems.id')
                    ->where('company_systems.company_id', $selectedCompanyId)
                    ->where('company_systems.active', 1)
                    ->where('systems.active', 1)
                    ->orderBy('systems.name', 'ASC')
                    ->get()
                    ->getResultArray();
            } else {
                $rows = $db->table('systems')
                    ->where('systems.active', 1)
                    ->orderBy('name', 'ASC')
                    ->get()
                    ->getResultArray();
            }
        } else {
            $rows = $db->table('user_systems')
                ->select('systems.id, systems.name, systems.slug, systems.description, systems.entry_url, systems.icon, user_systems.access_level')
                ->join('systems', 'systems.id = user_systems.system_id')
                ->join('company_systems', 'company_systems.system_id = systems.id AND company_systems.company_id = user_systems.company_id')
                ->where('user_systems.user_id', $userId)
                ->where('user_systems.active', 1)
                ->where('company_systems.active', 1)
                ->where('systems.active', 1)
                ->orderBy('systems.name', 'ASC')
                ->get()
                ->getResultArray();
        }
        
        foreach ($rows as $row) {
            $baseHref = $row['entry_url'];
            if ($baseHref !== '#') {
                $companyQuery = (!empty($selectedCompanyId) && in_array($row['slug'], ['inventario', 'ventas', 'compras', 'caja', 'contabilidad', 'impuestos', 'comercial'], true))
                    ? '?company_id=' . $selectedCompanyId
                    : '';
                $entryHref = site_url($baseHref . $companyQuery);
                
                $switcherSystems[] = [
                    'id' => $row['id'] ?? '',
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'entry_url' => $entryHref,
                    'icon' => $row['icon'] ?: 'bi-grid',
                ];
            }
        }
    }
}

// Module matching helpers
$isInventoryActive = str_starts_with($currentUri, 'inventario');
$isSalesActive = str_starts_with($currentUri, 'ventas');
$isPurchasesActive = str_starts_with($currentUri, 'compras');
$isCashActive = str_starts_with($currentUri, 'caja');
$isAccountingActive = str_starts_with($currentUri, 'contabilidad');
$isTaxesActive = str_starts_with($currentUri, 'impuestos');
$isUsersActive = str_starts_with($currentUri, 'usuarios');
$isSystemsActive = str_starts_with($currentUri, 'sistemas');
$isCompaniesActive = str_starts_with($currentUri, 'empresas');
$isSettingsActive = str_starts_with($currentUri, 'configuracion');
$isDashboardActive = $currentUri === 'dashboard' || str_starts_with($currentUri, 'dashboard/');

$companyParam = !empty($reqCompanyId) ? '?company_id=' . $reqCompanyId : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle ?? 'Codex Core ERP') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/codex-assist.css') ?>" rel="stylesheet">
    <meta name="codex-api-base" content="<?= site_url('api/v1') ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <style>
        .popup-shell {
            max-width: 760px;
            margin: 0 auto;
            padding: 18px 12px 22px;
        }
        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(20, 20, 20, 0.42);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1080;
        }
        .popup-overlay.is-open {
            display: flex;
        }
        .popup-card {
            width: min(860px, 100%);
            max-height: min(92vh, 920px);
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(0,0,0,.25);
            border: 1px solid rgba(0,0,0,.08);
        }
        .popup-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 14px 18px;
            border-bottom: 1px solid rgba(0,0,0,.08);
            background: #fbf8f3;
        }
        .icon-btn {
            width: 2.25rem;
            height: 2.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .popup-frame {
            width: 100%;
            min-height: 420px;
            border: 0;
            display: block;
        }
        .codex-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }
        .codex-pagination__summary {
            color: #6c757d;
            font-size: .95rem;
        }
        .codex-pagination__controls {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .codex-pagination__pages {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .codex-pagination__btn {
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border-radius: 12px;
            border: 1px solid rgba(17, 24, 39, 0.14);
            background: #fff;
            color: #111827;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all .2s ease;
        }
        .codex-pagination__btn:hover:not(:disabled) {
            background: #212529;
            color: #fff;
            border-color: #212529;
        }
        .codex-pagination__btn.is-active {
            background: #212529;
            color: #fff;
            border-color: #212529;
            box-shadow: 0 10px 24px rgba(17, 24, 39, 0.16);
        }
        .codex-pagination__btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="<?= $isPopup ? 'popup-mode' : '' ?>">

<?php if ($isPopup): ?>
    <main class="popup-shell">
        <?php if (session()->getFlashdata('message')): ?>
            <div class="alert alert-success rounded-4"><?= esc(session()->getFlashdata('message')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger rounded-4"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?= $this->renderSection('content') ?>
    </main>
<?php else: ?>

    <div class="codex-layout" id="codexAppLayout">
        <!-- Backdrop for mobile drawer -->
        <div class="codex-sidebar-backdrop" id="codexSidebarBackdrop"></div>

        <!-- Collapsible Smart Sidebar -->
        <aside class="codex-sidebar" id="codexSidebar">
            <!-- Sidebar Header / Brand -->
            <div class="codex-sidebar__header">
                <a href="<?= site_url('dashboard') ?>" class="codex-brand">
                    <div class="codex-brand__logo"><i class="bi bi-layers-fill"></i></div>
                    <div class="codex-brand__text">
                        <div class="codex-brand__name">CODEX <span>ERP</span></div>
                        <div class="codex-brand__badge"><?= esc($activeCompanyName ?: 'Enterprise') ?></div>
                    </div>
                </a>
                <button type="button" class="btn btn-sm text-secondary d-lg-none p-1" id="codexMobileSidebarClose" aria-label="Cerrar menú">
                    <i class="bi bi-x-lg fs-5"></i>
                </button>
            </div>

            <!-- Sidebar Body -->
            <div class="codex-sidebar__body">
                <!-- Quick Create Trigger -->
                <div>
                    <div class="dropdown w-100">
                        <button class="codex-quick-action w-100 justify-content-center dropdown-toggle" type="button" id="sidebarQuickActionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-plus-circle-fill"></i>
                            <span class="codex-quick-action__text">Acción Rápida</span>
                        </button>
                        <ul class="dropdown-menu border-0 shadow-lg rounded-4 p-2" aria-labelledby="sidebarQuickActionDropdown" style="min-width: 240px; z-index: 2100;">
                            <li class="dropdown-header small text-uppercase text-secondary fw-bold px-3 py-1">Inventario & Stock</li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="<?= site_url('inventario/productos/nuevo' . $companyParam) ?>" data-popup="true" data-popup-title="Nuevo Producto" data-popup-subtitle="Alta de artículo en catálogo y niveles iniciales.">
                                    <i class="bi bi-box-seam text-danger"></i>
                                    <span>Nuevo Producto</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="<?= site_url('inventario/movimientos/nuevo' . $companyParam) ?>" data-popup="true" data-popup-title="Movimiento de Stock" data-popup-subtitle="Registrar ingreso, egreso, transferencia o ajuste.">
                                    <i class="bi bi-arrow-left-right text-success"></i>
                                    <span>Registrar Movimiento</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="<?= site_url('inventario/reservas/nueva' . $companyParam) ?>" data-popup="true" data-popup-title="Reserva de Stock" data-popup-subtitle="Comprometer existencias por depósito.">
                                    <i class="bi bi-shield-lock text-warning"></i>
                                    <span>Nueva Reserva</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li class="dropdown-header small text-uppercase text-secondary fw-bold px-3 py-1">Ventas & Clientes</li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="<?= site_url('ventas/pos' . $companyParam) ?>">
                                    <i class="bi bi-receipt text-primary"></i>
                                    <span>Punto de Venta (POS)</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="<?= site_url('ventas/clientes/nuevo' . $companyParam) ?>" data-popup="true" data-popup-title="Nuevo Cliente" data-popup-subtitle="Registrar nuevo cliente en el sistema.">
                                    <i class="bi bi-person-plus text-info"></i>
                                    <span>Nuevo Cliente</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Section: Módulos Operativos -->
                <div class="codex-nav-section">
                    <div class="codex-nav-section__title">Ecosistema ERP</div>
                    <ul class="codex-nav-list">
                        <?php if (!empty($switcherSystems)): ?>
                            <?php foreach ($switcherSystems as $sys): ?>
                                <?php
                                    $slug = $sys['slug'];
                                    $isThisActive = ($slug === 'inventario' && $isInventoryActive)
                                        || ($slug === 'ventas' && $isSalesActive)
                                        || ($slug === 'compras' && $isPurchasesActive)
                                        || ($slug === 'caja' && $isCashActive)
                                        || ($slug === 'contabilidad' && $isAccountingActive)
                                        || ($slug === 'impuestos' && $isTaxesActive);
                                ?>
                                <li class="codex-nav-item">
                                    <a href="<?= esc($sys['entry_url']) ?>" class="codex-nav-link <?= $isThisActive ? 'is-active' : '' ?>" title="<?= esc($sys['name']) ?>">
                                        <span class="codex-nav-link__icon"><i class="bi <?= esc($sys['icon']) ?>"></i></span>
                                        <span class="codex-nav-link__text"><?= esc($sys['name']) ?></span>
                                    </a>

                                    <!-- Contextual Submenu for Inventario -->
                                    <?php if ($slug === 'inventario' && $isInventoryActive): ?>
                                        <ul class="codex-submenu">
                                            <li><a href="<?= site_url('inventario' . $companyParam) ?>" class="codex-submenu__link <?= ($currentUri === 'inventario') ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Vista General</a></li>
                                            <li><a href="<?= site_url('inventario/kardex' . $companyParam) ?>" class="codex-submenu__link <?= (str_contains($currentUri, 'kardex')) ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Kardex Operativo</a></li>
                                            <li><a href="<?= site_url('inventario/configuracion' . $companyParam) ?>" class="codex-submenu__link <?= (str_contains($currentUri, 'inventario/configuracion')) ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Catálogo & Almacenes</a></li>
                                        </ul>
                                    <?php endif; ?>

                                    <!-- Contextual Submenu for Ventas -->
                                    <?php if ($slug === 'ventas' && $isSalesActive): ?>
                                        <ul class="codex-submenu">
                                            <li><a href="<?= site_url('ventas' . $companyParam) ?>" class="codex-submenu__link <?= ($currentUri === 'ventas') ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Panel de Ventas</a></li>
                                            <li><a href="<?= site_url('ventas/pos' . $companyParam) ?>" class="codex-submenu__link <?= (str_contains($currentUri, 'ventas/pos')) ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Terminal POS</a></li>
                                            <li><a href="<?= site_url('ventas/kiosco' . $companyParam) ?>" class="codex-submenu__link <?= (str_contains($currentUri, 'ventas/kiosco')) ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Modo Kiosco</a></li>
                                            <li><a href="<?= site_url('ventas/cobranzas' . $companyParam) ?>" class="codex-submenu__link <?= (str_contains($currentUri, 'cobranzas')) ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Cobranzas & Recibos</a></li>
                                            <li><a href="<?= site_url('ventas/reportes' . $companyParam) ?>" class="codex-submenu__link <?= (str_contains($currentUri, 'reportes')) ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Reportes</a></li>
                                            <li><a href="<?= site_url('ventas/configuracion' . $companyParam) ?>" class="codex-submenu__link <?= (str_contains($currentUri, 'ventas/configuracion')) ? 'is-active' : '' ?>"><i class="bi bi-dot"></i> Comprobantes & Ajustes</a></li>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Section: Administración & Configuración -->
                <div class="codex-nav-section">
                    <div class="codex-nav-section__title">Gestión Central</div>
                    <ul class="codex-nav-list">
                        <?php if (auth_can('dashboard.view') && (auth_user()['role_slug'] ?? '') !== 'vendedor'): ?>
                            <li class="codex-nav-item">
                                <a href="<?= site_url('dashboard') ?>" class="codex-nav-link <?= $isDashboardActive ? 'is-active' : '' ?>" title="Dashboard">
                                    <span class="codex-nav-link__icon"><i class="bi bi-speedometer2"></i></span>
                                    <span class="codex-nav-link__text">Dashboard</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (auth_can('users.view')): ?>
                            <li class="codex-nav-item">
                                <a href="<?= site_url('usuarios') ?>" class="codex-nav-link <?= $isUsersActive ? 'is-active' : '' ?>" title="Usuarios">
                                    <span class="codex-nav-link__icon"><i class="bi bi-people-fill"></i></span>
                                    <span class="codex-nav-link__text">Usuarios</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (auth_can('systems.view') && (auth_user()['role_slug'] ?? '') !== 'vendedor'): ?>
                            <li class="codex-nav-item">
                                <a href="<?= site_url('sistemas') ?>" class="codex-nav-link <?= $isSystemsActive ? 'is-active' : '' ?>" title="Sistemas">
                                    <span class="codex-nav-link__icon"><i class="bi bi-diagram-3-fill"></i></span>
                                    <span class="codex-nav-link__text">Sistemas</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (auth_can('companies.view')): ?>
                            <li class="codex-nav-item">
                                <a href="<?= site_url('empresas') ?>" class="codex-nav-link <?= $isCompaniesActive ? 'is-active' : '' ?>" title="Empresas">
                                    <span class="codex-nav-link__icon"><i class="bi bi-buildings-fill"></i></span>
                                    <span class="codex-nav-link__text">Empresas</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (auth_can('settings.view')): ?>
                            <li class="codex-nav-item">
                                <a href="<?= site_url('configuracion') ?>" class="codex-nav-link <?= $isSettingsActive ? 'is-active' : '' ?>" title="Configuración">
                                    <span class="codex-nav-link__icon"><i class="bi bi-gear-fill"></i></span>
                                    <span class="codex-nav-link__text">Configuración</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Sidebar Footer: Collapse Toggle -->
            <div class="codex-sidebar__footer">
                <button type="button" class="codex-sidebar-toggle-btn" id="codexSidebarToggle" title="Colapsar menú lateral">
                    <i class="bi bi-layout-sidebar-inset"></i>
                    <span>Colapsar menú</span>
                </button>
            </div>
        </aside>

        <!-- Main Wrapper (Topbar + Content) -->
        <div class="codex-main-wrapper" id="codexMainWrapper">
            <!-- Smart Sticky Topbar -->
            <header class="codex-topbar">
                <!-- Left: Sidebar mobile toggle + Active Context -->
                <div class="codex-topbar__left">
                    <button type="button" class="btn btn-outline-dark btn-sm icon-btn rounded-3 d-lg-none" id="codexMobileMenuBtn" aria-label="Abrir menú">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <div class="d-none d-md-flex align-items-center gap-2">
                        <?php if (!empty($activeCompanyName)): ?>
                            <span class="badge rounded-pill bg-white text-dark border px-3 py-1.5 shadow-sm d-flex align-items-center gap-1.5" style="font-size: 0.82rem;">
                                <i class="bi bi-building text-secondary"></i>
                                <strong class="fw-semibold"><?= esc($activeCompanyName) ?></strong>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Center: Global Spotlight Search Trigger (Ctrl + K) -->
                <div class="codex-topbar__center">
                    <button type="button" class="codex-search-trigger" id="codexCmdTrigger">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-search text-secondary"></i>
                            <span>Buscar módulos, acciones, productos...</span>
                        </div>
                        <span class="codex-kbd-badge"><i class="bi bi-command"></i> Ctrl K</span>
                    </button>
                </div>

                <!-- Right: Quick Actions, User Profile & Exit -->
                <div class="codex-topbar__right">
                    <!-- Switcher Dropdown Shortcut -->
                    <?php if (!empty($switcherSystems)): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-dark btn-sm rounded-3 icon-btn" type="button" id="topbarModuleLauncher" data-bs-toggle="dropdown" aria-expanded="false" title="Lanzador de Módulos">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-3 border-0 shadow-lg rounded-4 mt-2" aria-labelledby="topbarModuleLauncher" style="width: 320px; z-index: 2000;">
                                <div class="dropdown-header px-2 py-1 text-uppercase text-secondary small fw-bold mb-2">Ecosistema Codex</div>
                                <div class="row g-2">
                                    <?php foreach ($switcherSystems as $sys): ?>
                                        <div class="col-6">
                                            <a href="<?= esc($sys['entry_url']) ?>" class="d-flex flex-column align-items-center text-center p-2 rounded-3 text-decoration-none text-dark bg-light bg-opacity-50" style="transition: all 0.2s ease;">
                                                <span class="icon-btn mb-1.5 rounded-3 bg-white text-dark d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; font-size: 18px;"><i class="bi <?= esc($sys['icon']) ?>"></i></span>
                                                <span style="font-size: 11.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%;"><?= esc($sys['name']) ?></span>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- User Pill Dropdown -->
                    <div class="dropdown">
                        <div class="codex-user-pill" role="button" id="userProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="codex-avatar">
                                <?= strtoupper(substr(auth_user()['name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="d-none d-sm-flex flex-column text-start">
                                <span class="fw-bold text-dark lh-1" style="font-size: 0.85rem;"><?= esc(auth_user()['name'] ?? '') ?></span>
                                <span class="text-secondary" style="font-size: 0.72rem;"><?= esc(auth_user()['role_name'] ?? 'Usuario') ?></span>
                            </div>
                            <i class="bi bi-chevron-down text-secondary small ms-1"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2 mt-2" aria-labelledby="userProfileDropdown" style="min-width: 210px; z-index: 2000;">
                            <li class="px-3 py-2 border-bottom mb-1">
                                <div class="fw-bold"><?= esc(auth_user()['name'] ?? '') ?></div>
                                <div class="small text-secondary"><?= esc(auth_user()['email'] ?? '') ?></div>
                            </li>
                            <?php if (auth_can('settings.view')): ?>
                                <li>
                                    <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="<?= site_url('configuracion') ?>">
                                        <i class="bi bi-gear text-secondary"></i> Configuración
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 text-danger d-flex align-items-center gap-2" href="<?= site_url('logout') ?>">
                                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Page Body / Injected Views -->
            <main class="container-fluid px-3 px-md-4 py-4 flex-grow-1">
                <?php if (session()->getFlashdata('message')): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-4 px-4 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div><?= esc(session()->getFlashdata('message')) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 px-4 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                        <div><?= esc(session()->getFlashdata('error')) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('errors')): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 px-4 py-3">
                        <?php foreach ((array) session()->getFlashdata('errors') as $error): ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span><?= esc($error) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>

    <!-- Command Palette Overlay (Ctrl + K) -->
    <div class="codex-cmd-overlay" id="codexCmdOverlay">
        <div class="codex-cmd-card">
            <div class="codex-cmd-header">
                <i class="bi bi-search"></i>
                <input type="text" class="codex-cmd-input" id="codexCmdInput" placeholder="Escribe para buscar módulos, acciones o presiona Esc..." autocomplete="off">
                <button type="button" class="btn btn-sm btn-outline-secondary border-0 icon-btn rounded-3" id="codexCmdClose" aria-label="Cerrar buscador">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="codex-cmd-results" id="codexCmdResults">
                <!-- Group: Acciones Rápidas -->
                <div class="codex-cmd-group-title">Acciones Inmediatas (Sin salir de vista)</div>
                <div class="codex-cmd-item" data-url="<?= site_url('inventario/productos/nuevo' . $companyParam) ?>" data-popup="true" data-title="Nuevo Producto" data-subtitle="Crear nuevo artículo en catálogo">
                    <div class="codex-cmd-item__icon"><i class="bi bi-box-seam"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Nuevo Producto</span>
                        <span class="codex-cmd-item__subtitle">Inventario &bull; Alta en catálogo de artículos</span>
                    </div>
                    <span class="badge text-bg-light small">Modal</span>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('inventario/movimientos/nuevo' . $companyParam) ?>" data-popup="true" data-title="Movimiento de Stock" data-subtitle="Registrar ingreso, egreso o ajuste">
                    <div class="codex-cmd-item__icon"><i class="bi bi-arrow-left-right"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Registrar Movimiento de Stock</span>
                        <span class="codex-cmd-item__subtitle">Inventario &bull; Ingreso, Egreso, Transferencia o Ajuste</span>
                    </div>
                    <span class="badge text-bg-light small">Modal</span>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('inventario/reservas/nueva' . $companyParam) ?>" data-popup="true" data-title="Reserva de Stock" data-subtitle="Comprometer existencias por depósito">
                    <div class="codex-cmd-item__icon"><i class="bi bi-shield-lock"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Nueva Reserva de Stock</span>
                        <span class="codex-cmd-item__subtitle">Inventario &bull; Reserva operativa de mercadería</span>
                    </div>
                    <span class="badge text-bg-light small">Modal</span>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('ventas/clientes/nuevo' . $companyParam) ?>" data-popup="true" data-title="Nuevo Cliente" data-subtitle="Registrar cliente en el sistema">
                    <div class="codex-cmd-item__icon"><i class="bi bi-person-plus"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Nuevo Cliente</span>
                        <span class="codex-cmd-item__subtitle">Ventas &bull; Alta rápida de cliente o contacto</span>
                    </div>
                    <span class="badge text-bg-light small">Modal</span>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('inventario/depositos/nuevo' . $companyParam) ?>" data-popup="true" data-title="Nuevo Depósito" data-subtitle="Crear nuevo almacén o depósito">
                    <div class="codex-cmd-item__icon"><i class="bi bi-shop"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Nuevo Depósito / Almacén</span>
                        <span class="codex-cmd-item__subtitle">Inventario &bull; Crear depósito para almacenamiento</span>
                    </div>
                    <span class="badge text-bg-light small">Modal</span>
                </div>

                <!-- Group: Módulos y Secciones -->
                <div class="codex-cmd-group-title">Módulos del Sistema</div>
                <div class="codex-cmd-item" data-url="<?= site_url('inventario' . $companyParam) ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-boxes"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Inventario &bull; Vista General</span>
                        <span class="codex-cmd-item__subtitle">Stock en tiempo real, alertas de stock mínimo y filtros</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('inventario/kardex' . $companyParam) ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-journal-text"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Kardex Operativo</span>
                        <span class="codex-cmd-item__subtitle">Historial de movimientos, saldos valorizados y reportes PDF</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('inventario/configuracion' . $companyParam) ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-sliders"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Configuración de Inventario</span>
                        <span class="codex-cmd-item__subtitle">Gestión de depósitos, ubicaciones y parámetros de costeo</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('ventas' . $companyParam) ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-cart3"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Panel de Ventas</span>
                        <span class="codex-cmd-item__subtitle">Ventas diarias, métricas comerciales y facturación</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('ventas/pos' . $companyParam) ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-receipt-cutoff"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Terminal Punto de Venta (POS)</span>
                        <span class="codex-cmd-item__subtitle">Facturación rápida de mostrador y cobro ágil</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('ventas/cobranzas' . $companyParam) ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-cash-coin"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Cobranzas & Recibos</span>
                        <span class="codex-cmd-item__subtitle">Gestión de cobranza y cuentas por cobrar</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('ventas/reportes' . $companyParam) ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Reportes de Ventas</span>
                        <span class="codex-cmd-item__subtitle">Estadísticas por cliente, artículo y exportaciones</span>
                    </div>
                </div>

                <!-- Group: Administración General -->
                <div class="codex-cmd-group-title">Administración y Seguridad</div>
                <div class="codex-cmd-item" data-url="<?= site_url('dashboard') ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-speedometer2"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Dashboard Principal</span>
                        <span class="codex-cmd-item__subtitle">Métricas clave y estado de la empresa</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('usuarios') ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-people"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Gestión de Usuarios</span>
                        <span class="codex-cmd-item__subtitle">Roles, permisos y control de acceso</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('empresas') ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-buildings"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Empresas & Sucursales</span>
                        <span class="codex-cmd-item__subtitle">Configuración multiempresa</span>
                    </div>
                </div>
                <div class="codex-cmd-item" data-url="<?= site_url('configuracion') ?>">
                    <div class="codex-cmd-item__icon"><i class="bi bi-gear"></i></div>
                    <div class="codex-cmd-item__title">
                        <span>Configuración del Sistema</span>
                        <span class="codex-cmd-item__subtitle">Monedas, impuestos, sucursales y talonarios</span>
                    </div>
                </div>
            </div>
            <div class="codex-cmd-footer">
                <div class="codex-cmd-keys">
                    <span><kbd class="codex-kbd-badge">&uarr;</kbd> <kbd class="codex-kbd-badge">&darr;</kbd> Navegar</span>
                    <span><kbd class="codex-kbd-badge">&#9166;</kbd> Seleccionar</span>
                    <span><kbd class="codex-kbd-badge">ESC</kbd> Cerrar</span>
                </div>
                <div><strong>Codex</strong> Command Spotlight</div>
            </div>
        </div>
    </div>

    <!-- Modal Popup Overlay for Iframe Forms -->
    <div class="popup-overlay" id="codex-popup">
        <div class="popup-card">
            <div class="popup-head">
                <div>
                    <strong class="d-block" id="codex-popup-title">Formulario</strong>
                    <span class="text-secondary small" id="codex-popup-subtitle">Gestión del registro actual.</span>
                </div>
                <button type="button" class="btn btn-outline-dark btn-sm icon-btn" id="codex-popup-close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <iframe class="popup-frame" id="codex-popup-frame" title="Formulario del sistema"></iframe>
        </div>
    </div>

<?php endif; ?>

    <!-- Core Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.select-search').forEach((el) => {
                new TomSelect(el, { create: false, sortField: { field: "text", direction: "asc" } });
            });
        });
    </script>

<?php if ($isPopup): ?>
    <script>
        (() => {
            const notifyParent = () => {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'codex-popup-resize' }, window.location.origin);
                }
            };
            window.addEventListener('load', notifyParent);
            window.addEventListener('resize', notifyParent);
            setTimeout(notifyParent, 80);
        })();
    </script>
<?php else: ?>
    <!-- Interactive Navigation & Command Palette Scripts -->
    <script>
        (() => {
            // Sidebar Collapsible & Mobile Drawer Logic
            const sidebar = document.getElementById('codexSidebar');
            const mainWrapper = document.getElementById('codexMainWrapper');
            const toggleBtn = document.getElementById('codexSidebarToggle');
            const mobileMenuBtn = document.getElementById('codexMobileMenuBtn');
            const mobileCloseBtn = document.getElementById('codexMobileSidebarClose');
            const backdrop = document.getElementById('codexSidebarBackdrop');

            // Load saved sidebar state
            const savedState = localStorage.getItem('codex-sidebar-collapsed');
            if (savedState === 'true' && window.innerWidth >= 992) {
                sidebar?.classList.add('codex-sidebar--collapsed');
                mainWrapper?.classList.add('codex-main-wrapper--expanded');
                if (toggleBtn) toggleBtn.innerHTML = '<i class="bi bi-layout-sidebar"></i><span>Expandir menú</span>';
            }

            const toggleSidebar = () => {
                if (!sidebar || !mainWrapper) return;
                const isCollapsed = sidebar.classList.toggle('codex-sidebar--collapsed');
                mainWrapper.classList.toggle('codex-main-wrapper--expanded', isCollapsed);
                localStorage.setItem('codex-sidebar-collapsed', isCollapsed ? 'true' : 'false');
                if (toggleBtn) {
                    toggleBtn.innerHTML = isCollapsed
                        ? '<i class="bi bi-layout-sidebar"></i><span>Expandir menú</span>'
                        : '<i class="bi bi-layout-sidebar-inset"></i><span>Colapsar menú</span>';
                }
            };

            const openMobileSidebar = () => {
                sidebar?.classList.add('is-mobile-open');
                backdrop?.classList.add('is-open');
            };

            const closeMobileSidebar = () => {
                sidebar?.classList.remove('is-mobile-open');
                backdrop?.classList.remove('is-open');
            };

            toggleBtn?.addEventListener('click', toggleSidebar);
            mobileMenuBtn?.addEventListener('click', openMobileSidebar);
            mobileCloseBtn?.addEventListener('click', closeMobileSidebar);
            backdrop?.addEventListener('click', closeMobileSidebar);

            // Command Palette (Ctrl + K / Spotlight) Logic
            const cmdOverlay = document.getElementById('codexCmdOverlay');
            const cmdInput = document.getElementById('codexCmdInput');
            const cmdTrigger = document.getElementById('codexCmdTrigger');
            const cmdClose = document.getElementById('codexCmdClose');
            const cmdResults = document.getElementById('codexCmdResults');

            const openCmdPalette = () => {
                if (!cmdOverlay) return;
                cmdOverlay.classList.add('is-open');
                if (cmdInput) {
                    cmdInput.value = '';
                    filterCmdResults('');
                    setTimeout(() => cmdInput.focus(), 50);
                }
            };

            const closeCmdPalette = () => {
                cmdOverlay?.classList.remove('is-open');
            };

            const filterCmdResults = (query) => {
                if (!cmdResults) return;
                const q = query.toLowerCase().trim();
                const items = cmdResults.querySelectorAll('.codex-cmd-item');
                const groups = cmdResults.querySelectorAll('.codex-cmd-group-title');

                let anyVisible = false;
                items.forEach((item) => {
                    const text = item.textContent.toLowerCase();
                    const matches = q === '' || text.includes(q);
                    item.style.display = matches ? 'flex' : 'none';
                    item.classList.remove('is-selected');
                    if (matches) anyVisible = true;
                });

                // Select first visible item
                const firstVisible = Array.from(items).find(i => i.style.display !== 'none');
                if (firstVisible) firstVisible.classList.add('is-selected');

                // Toggle group titles
                groups.forEach(g => {
                    let next = g.nextElementSibling;
                    let hasVisibleItem = false;
                    while (next && !next.classList.contains('codex-cmd-group-title')) {
                        if (next.classList.contains('codex-cmd-item') && next.style.display !== 'none') {
                            hasVisibleItem = true;
                            break;
                        }
                        next = next.nextElementSibling;
                    }
                    g.style.display = hasVisibleItem ? 'block' : 'none';
                });
            };

            cmdTrigger?.addEventListener('click', openCmdPalette);
            cmdClose?.addEventListener('click', closeCmdPalette);
            cmdOverlay?.addEventListener('click', (e) => {
                if (e.target === cmdOverlay) closeCmdPalette();
            });

            // Global Keyboard Shortcuts (Ctrl+K, Cmd+K, Escape, Arrows)
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                    e.preventDefault();
                    if (cmdOverlay?.classList.contains('is-open')) {
                        closeCmdPalette();
                    } else {
                        openCmdPalette();
                    }
                } else if (e.key === 'Escape') {
                    if (cmdOverlay?.classList.contains('is-open')) {
                        closeCmdPalette();
                    }
                } else if (cmdOverlay?.classList.contains('is-open')) {
                    const visibleItems = Array.from(cmdResults?.querySelectorAll('.codex-cmd-item') || []).filter(i => i.style.display !== 'none');
                    if (!visibleItems.length) return;

                    const currentIndex = visibleItems.findIndex(i => i.classList.contains('is-selected'));

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const nextIndex = (currentIndex + 1) % visibleItems.length;
                        visibleItems.forEach(i => i.classList.remove('is-selected'));
                        visibleItems[nextIndex].classList.add('is-selected');
                        visibleItems[nextIndex].scrollIntoView({ block: 'nearest' });
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const prevIndex = (currentIndex - 1 + visibleItems.length) % visibleItems.length;
                        visibleItems.forEach(i => i.classList.remove('is-selected'));
                        visibleItems[prevIndex].classList.add('is-selected');
                        visibleItems[prevIndex].scrollIntoView({ block: 'nearest' });
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        const selected = visibleItems[currentIndex] || visibleItems[0];
                        if (selected) executeCmdItem(selected);
                    }
                }
            });

            cmdInput?.addEventListener('input', (e) => {
                filterCmdResults(e.target.value);
            });

            const executeCmdItem = (item) => {
                const url = item.dataset.url;
                const isPopup = item.dataset.popup === 'true';
                closeCmdPalette();
                if (!url) return;

                if (isPopup) {
                    const mockLink = document.createElement('a');
                    mockLink.href = url;
                    mockLink.dataset.popup = 'true';
                    mockLink.dataset.popupTitle = item.dataset.title || 'Formulario';
                    mockLink.dataset.popupSubtitle = item.dataset.subtitle || '';
                    document.body.appendChild(mockLink);
                    if (window.openCodexPopup) {
                        window.openCodexPopup(mockLink);
                    } else {
                        mockLink.click();
                    }
                    mockLink.remove();
                } else {
                    window.location.href = url;
                }
            };

            cmdResults?.addEventListener('click', (e) => {
                const item = e.target.closest('.codex-cmd-item');
                if (item) executeCmdItem(item);
            });
        })();
    </script>

    <!-- Modal Popup Engine & Notifications -->
    <script>
        (() => {
            const overlay = document.getElementById('codex-popup');
            const frame = document.getElementById('codex-popup-frame');
            const closeButton = document.getElementById('codex-popup-close');
            const title = document.getElementById('codex-popup-title');
            const subtitle = document.getElementById('codex-popup-subtitle');
            const card = overlay?.querySelector('.popup-card');

            if (!overlay || !frame || !closeButton || !title || !subtitle || !card) {
                return;
            }

            const resizePopup = () => {
                if (frame.src === 'about:blank') return;
                try {
                    const doc = frame.contentWindow?.document;
                    if (!doc) return;
                    const bodyHeight = doc.body ? doc.body.scrollHeight : 0;
                    const htmlHeight = doc.documentElement ? doc.documentElement.scrollHeight : 0;
                    const contentHeight = Math.max(bodyHeight, htmlHeight, 420);
                    const headHeight = overlay.querySelector('.popup-head')?.offsetHeight ?? 72;
                    const availableHeight = Math.max(420, Math.floor(window.innerHeight * 0.9) - headHeight);
                    frame.style.height = `${Math.min(contentHeight, availableHeight)}px`;
                } catch (error) {
                    frame.style.height = '640px';
                }
            };

            window.openCodexPopup = (link) => {
                const url = new URL(link.href, window.location.origin);
                url.searchParams.set('popup', '1');
                frame.src = url.toString();
                title.textContent = link.dataset.popupTitle || link.textContent.trim() || 'Formulario';
                subtitle.textContent = link.dataset.popupSubtitle || 'Gestión del registro actual.';
                overlay.classList.add('is-open');
            };

            const closePopup = () => {
                overlay.classList.remove('is-open');
                frame.src = 'about:blank';
                frame.style.height = '420px';
            };

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[data-popup="true"]');
                if (!link) return;
                event.preventDefault();
                window.openCodexPopup(link);
            });

            closeButton.addEventListener('click', closePopup);
            frame.addEventListener('load', resizePopup);
            window.addEventListener('resize', resizePopup);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) closePopup();
            });

            window.showCodexToast = (message, type = 'success') => {
                let container = document.getElementById('codex-toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'codex-toast-container';
                    container.style.position = 'fixed';
                    container.style.top = '20px';
                    container.style.right = '20px';
                    container.style.zIndex = '99999';
                    container.style.display = 'flex';
                    container.style.flexDirection = 'column';
                    container.style.gap = '10px';
                    container.style.pointerEvents = 'none';
                    document.body.appendChild(container);
                }

                const toast = document.createElement('div');
                toast.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible shadow-lg border-0 rounded-4 px-4 py-3 mb-0`;
                toast.style.pointerEvents = 'auto';
                toast.style.minWidth = '280px';
                toast.style.maxWidth = '420px';
                toast.style.transition = 'all 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
                toast.style.transform = 'translateY(-10px)';
                toast.style.opacity = '0';
                toast.innerHTML = `
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-${type === 'error' ? 'exclamation-octagon-fill' : 'check-circle-fill'} fs-5"></i>
                        <div class="fw-semibold text-wrap">${message}</div>
                    </div>
                `;
                container.appendChild(toast);

                requestAnimationFrame(() => {
                    toast.style.transform = 'translateY(0)';
                    toast.style.opacity = '1';
                });

                setTimeout(() => {
                    toast.style.transform = 'translateY(-10px)';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 4000);
            };

            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin || !event.data) return;

                if (event.data.type === 'codex-popup-saved') {
                    closePopup();
                    if (event.data.message) window.showCodexToast(event.data.message, 'success');
                    if (event.data.entity) {
                        window.dispatchEvent(new CustomEvent('codex:' + event.data.entity + '-saved', { detail: event.data }));
                    }
                    window.dispatchEvent(new CustomEvent('codex:item-saved', { detail: event.data }));
                    return;
                }

                if (event.data.type === 'codex-customer-created') {
                    closePopup();
                    window.dispatchEvent(new CustomEvent('codex:customer-created', { detail: event.data }));
                    if (!document.getElementById('sale-customer-id') && !document.getElementById('kiosk-customer-id')) {
                        window.location.href = event.data.redirectUrl || window.location.href;
                    }
                    return;
                }

                if (event.data.type === 'codex-popup-close') {
                    closePopup();
                    if (event.data.forceReload) {
                        window.location.href = event.data.redirectUrl || window.location.href;
                    }
                    return;
                }

                if (event.data.type === 'codex-popup-resize') {
                    resizePopup();
                }
            });
        })();
    </script>

    <!-- Table Pagination Component Helper -->
    <script>
        (() => {
            const buildPagination = (table) => {
                const pageSize = Number(table.dataset.codexPagination || 10);
                const tbody = table.tBodies[0];
                if (!tbody || !Number.isFinite(pageSize) || pageSize <= 0) return;

                const rows = Array.from(tbody.querySelectorAll(':scope > tr'));
                const validRows = rows.filter((row) => row.children.length > 1 || !row.querySelector('[colspan]'));
                if (validRows.length <= pageSize) return;

                const wrapper = document.createElement('div');
                wrapper.className = 'codex-pagination';

                const summary = document.createElement('div');
                summary.className = 'codex-pagination__summary';

                const controls = document.createElement('div');
                controls.className = 'codex-pagination__controls';

                const prev = document.createElement('button');
                prev.type = 'button';
                prev.className = 'codex-pagination__btn';
                prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
                prev.setAttribute('aria-label', 'Página anterior');

                const pages = document.createElement('div');
                pages.className = 'codex-pagination__pages';

                const next = document.createElement('button');
                next.type = 'button';
                next.className = 'codex-pagination__btn';
                next.innerHTML = '<i class="bi bi-chevron-right"></i>';
                next.setAttribute('aria-label', 'Página siguiente');

                controls.append(prev, pages, next);
                wrapper.append(summary, controls);
                table.closest('.table-responsive')?.after(wrapper);

                const pageCount = Math.ceil(validRows.length / pageSize);
                let currentPage = 1;

                const renderPageButtons = () => {
                    pages.innerHTML = '';
                    const start = Math.max(1, currentPage - 2);
                    const end = Math.min(pageCount, currentPage + 2);

                    for (let page = start; page <= end; page++) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = `codex-pagination__btn${page === currentPage ? ' is-active' : ''}`;
                        button.textContent = String(page);
                        button.addEventListener('click', () => {
                            currentPage = page;
                            render();
                        });
                        pages.appendChild(button);
                    }
                };

                const render = () => {
                    const startIndex = (currentPage - 1) * pageSize;
                    const endIndex = startIndex + pageSize;

                    validRows.forEach((row, index) => {
                        row.style.display = index >= startIndex && index < endIndex ? '' : 'none';
                    });

                    summary.textContent = `Mostrando ${startIndex + 1}-${Math.min(endIndex, validRows.length)} de ${validRows.length} registros`;
                    prev.disabled = currentPage === 1;
                    next.disabled = currentPage === pageCount;
                    renderPageButtons();
                };

                prev.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage -= 1;
                        render();
                    }
                });

                next.addEventListener('click', () => {
                    if (currentPage < pageCount) {
                        currentPage += 1;
                        render();
                    }
                });

                render();
            };

            document.querySelectorAll('table[data-codex-pagination]').forEach(buildPagination);
        })();
    </script>
    <script src="<?= base_url('assets/js/codex-assist-widget.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>

