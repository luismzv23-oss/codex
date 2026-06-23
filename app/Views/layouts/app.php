<?php
$request = service('request');
$isPopup = $request->getGet('popup') === '1';

// Fetch accessible systems for quick switcher
$switcherSystems = [];
if (auth_check() && !$isPopup) {
    $userId = auth_user()['id'] ?? '';
    $roleSlug = auth_user()['role_slug'] ?? '';
    $companyId = auth_user()['company_id'] ?? '';
    if (!empty($userId)) {
        $db = \Config\Database::connect();
        if ($roleSlug === 'superadmin') {
            $reqCompanyId = service('request')->getGet('company_id') ?: $companyId;
            if (!empty($reqCompanyId)) {
                $rows = $db->table('systems')
                    ->select('systems.*')
                    ->join('company_systems', 'company_systems.system_id = systems.id')
                    ->where('company_systems.company_id', $reqCompanyId)
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
                $reqCompanyId = service('request')->getGet('company_id') ?: $companyId;
                $companyQuery = (!empty($reqCompanyId) && in_array($row['slug'], ['inventario', 'ventas', 'compras', 'caja', 'contabilidad', 'impuestos', 'comercial'], true))
                    ? '?company_id=' . $reqCompanyId
                    : '';
                $entryHref = site_url($baseHref . $companyQuery);
                
                $switcherSystems[] = [
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'entry_url' => $entryHref,
                    'icon' => $row['icon'] ?: 'bi-grid',
                ];
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle ?? 'Codex') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/codex-assist.css') ?>" rel="stylesheet">
    <meta name="codex-api-base" content="<?= site_url('api/v1') ?>">
    <style>
        body.popup-mode {
            background: #f6f1eb;
        }
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
        .switcher-card:hover {
            background-color: rgba(33, 37, 41, 0.05);
            transform: translateY(-2px);
        }
        .switcher-card:hover .icon-btn {
            background-color: #212529 !important;
            color: #fff !important;
        }
    </style>
</head>
<body class="<?= $isPopup ? 'popup-mode' : '' ?>">
    <?php if (! $isPopup): ?>
        <nav class="navbar navbar-expand-lg bg-white bg-opacity-75 border-bottom sticky-top backdrop-blur">
            <div class="container">
                <a class="navbar-brand fw-bold" href="<?= site_url('dashboard') ?>">Codex Core</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <?php if (auth_can('dashboard.view') && (auth_user()['role_slug'] ?? '') !== 'vendedor'): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                        <?php endif; ?>
                        <?php if (auth_can('users.view')): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= site_url('usuarios') ?>">Usuarios</a></li>
                        <?php endif; ?>
                        <?php if (auth_can('systems.view') && (auth_user()['role_slug'] ?? '') !== 'vendedor'): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= site_url('sistemas') ?>">Sistemas</a></li>
                        <?php endif; ?>
                        <?php if (auth_can('companies.view')): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= site_url('empresas') ?>">Empresas</a></li>
                        <?php endif; ?>
                        <?php if (auth_can('settings.view')): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= site_url('configuracion') ?>">Configuracion</a></li>
                        <?php endif; ?>
                    </ul>
                    <div class="d-flex align-items-center gap-3">
                        <!-- Lanzador de Módulos (Quick Switcher) -->
                        <?php if (!empty($switcherSystems)): ?>
                            <div class="dropdown me-1">
                                <button class="btn btn-outline-dark dropdown-toggle d-flex align-items-center gap-2 px-3 py-1.5 rounded-3 shadow-sm" type="button" id="moduleSwitcherDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border: 1px solid rgba(0,0,0,0.12); font-weight: 500; font-size: 13.5px;">
                                    <i class="bi bi-grid-3x3-gap-fill text-dark"></i>
                                    <span>Módulos</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-3 border-0 shadow-lg rounded-4 mt-2" aria-labelledby="moduleSwitcherDropdown" style="width: 320px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(0,0,0,0.06) !important; z-index: 2000;">
                                    <div class="dropdown-header px-2 py-1 text-uppercase tracking-wider text-secondary small fw-bold mb-2">Ecosistema ERP</div>
                                    <div class="row g-2">
                                        <?php foreach ($switcherSystems as $sys): ?>
                                            <div class="col-6">
                                                <a href="<?= esc($sys['entry_url']) ?>" class="d-flex flex-column align-items-center text-center p-2 rounded-3 text-decoration-none text-dark switcher-card" style="transition: all 0.2s ease;">
                                                    <span class="icon-btn mb-1.5 rounded-3 bg-light text-dark d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 20px;"><i class="bi <?= esc($sys['icon']) ?>"></i></span>
                                                    <span style="font-size: 11.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%;"><?= esc($sys['name']) ?></span>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="border-top mt-3 pt-2 text-center">
                                        <a href="<?= site_url('sistemas') ?>" class="text-decoration-none text-secondary small fw-medium" style="font-size: 11px;"><i class="bi bi-gear-fill me-1"></i>Gestionar asignaciones</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="small text-end">
                            <div class="fw-semibold"><?= esc(auth_user()['name'] ?? '') ?></div>
                            <div class="text-secondary"><?= esc(auth_user()['role_name'] ?? '') ?></div>
                        </div>
                        <a href="<?= site_url('logout') ?>" class="btn btn-outline-dark btn-sm">Salir</a>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <main class="<?= $isPopup ? 'popup-shell' : 'container py-4' ?>">
        <?php if (session()->getFlashdata('message')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger">
                <?php foreach ((array) session()->getFlashdata('errors') as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?= $this->renderSection('content') ?>
    </main>

    <?php if (! $isPopup): ?>
        <div class="popup-overlay" id="codex-popup">
            <div class="popup-card">
                <div class="popup-head">
                    <div>
                        <strong class="d-block" id="codex-popup-title">Formulario</strong>
                        <span class="text-secondary small" id="codex-popup-subtitle">Gestion del registro actual.</span>
                    </div>
                    <button type="button" class="btn btn-outline-dark btn-sm icon-btn" id="codex-popup-close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
                <iframe class="popup-frame" id="codex-popup-frame" title="Formulario del sistema"></iframe>
            </div>
        </div>
    <?php endif; ?>

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
    <?php endif; ?>
    <?php if (! $isPopup): ?>
        <script>
            (() => {
                const overlay = document.getElementById('codex-popup');
                const frame = document.getElementById('codex-popup-frame');
                const closeButton = document.getElementById('codex-popup-close');
                const title = document.getElementById('codex-popup-title');
                const subtitle = document.getElementById('codex-popup-subtitle');
                const card = overlay.querySelector('.popup-card');

                if (!overlay || !frame || !closeButton || !title || !subtitle || !card) {
                    return;
                }

                const resizePopup = () => {
                    if (frame.src === 'about:blank') {
                        return;
                    }

                    try {
                        const doc = frame.contentWindow?.document;

                        if (!doc) {
                            return;
                        }

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

                const openPopup = (link) => {
                    const url = new URL(link.href, window.location.origin);
                    url.searchParams.set('popup', '1');
                    frame.src = url.toString();
                    title.textContent = link.dataset.popupTitle || link.textContent.trim() || 'Formulario';
                    subtitle.textContent = link.dataset.popupSubtitle || 'Gestion del registro actual.';
                    overlay.classList.add('is-open');
                };

                const closePopup = () => {
                    overlay.classList.remove('is-open');
                    frame.src = 'about:blank';
                    frame.style.height = '420px';
                };

                document.addEventListener('click', (event) => {
                    const link = event.target.closest('a[data-popup="true"]');

                    if (!link) {
                        return;
                    }

                    event.preventDefault();
                    openPopup(link);
                });

                closeButton.addEventListener('click', closePopup);
                frame.addEventListener('load', resizePopup);
                window.addEventListener('resize', resizePopup);
                overlay.addEventListener('click', (event) => {
                    if (event.target === overlay) {
                        closePopup();
                    }
                });

                window.addEventListener('message', (event) => {
                    if (event.origin !== window.location.origin || !event.data) {
                        return;
                    }

                    if (event.data.type === 'codex-customer-created') {
                        closePopup();
                        const customerEvent = new CustomEvent('codex:customer-created', { detail: event.data });
                        window.dispatchEvent(customerEvent);
                        if (!document.getElementById('sale-customer-id') && !document.getElementById('kiosk-customer-id')) {
                            window.location.href = event.data.redirectUrl || window.location.href;
                        }
                        return;
                    }

                    if (event.data.type === 'codex-popup-close') {
                        closePopup();
                        window.location.href = event.data.redirectUrl || window.location.href;
                        return;
                    }

                    if (event.data.type === 'codex-popup-resize') {
                        resizePopup();
                    }
                });
            })();
        </script>
        <script>
            (() => {
                const buildPagination = (table) => {
                    const pageSize = Number(table.dataset.codexPagination || 10);
                    const tbody = table.tBodies[0];

                    if (!tbody || !Number.isFinite(pageSize) || pageSize <= 0) {
                        return;
                    }

                    const rows = Array.from(tbody.querySelectorAll(':scope > tr'));
                    const validRows = rows.filter((row) => row.children.length > 1 || !row.querySelector('[colspan]'));

                    if (validRows.length <= pageSize) {
                        return;
                    }

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
                    prev.setAttribute('aria-label', 'Pagina anterior');

                    const pages = document.createElement('div');
                    pages.className = 'codex-pagination__pages';

                    const next = document.createElement('button');
                    next.type = 'button';
                    next.className = 'codex-pagination__btn';
                    next.innerHTML = '<i class="bi bi-chevron-right"></i>';
                    next.setAttribute('aria-label', 'Pagina siguiente');

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
