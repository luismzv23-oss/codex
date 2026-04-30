<?php
$request = service('request');
$isPopup = $request->getGet('popup') === '1';
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
                        <?php if (auth_can('dashboard.view')): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                        <?php endif; ?>
                        <?php if (auth_can('users.view')): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= site_url('usuarios') ?>">Usuarios</a></li>
                        <?php endif; ?>
                        <?php if (auth_can('systems.view')): ?>
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
