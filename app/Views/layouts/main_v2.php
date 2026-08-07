<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Codex ERP — Sistema de Gestión Empresarial') ?></title>

    <!-- Master UI/UX Design System Stylesheets -->
    <link rel="stylesheet" href="<?= base_url('assets/css/codex-design-system.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/data-grid.css') ?>">
    <script src="<?= base_url('assets/js/omnisearch.js') ?>" defer></script>

    <style>
        .shell-layout {
            display: flex;
            min-height: 100vh;
        }
        .shell-sidebar {
            width: 260px;
            background: var(--codex-bg-surface);
            border-right: 1px solid var(--codex-border-color);
            display: flex;
            flex-direction: column;
            padding: 1.25rem 1rem;
            position: fixed;
            top: 0; bottom: 0; left: 0;
            z-index: 100;
        }
        .shell-main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .shell-topbar {
            height: 64px;
            background: var(--codex-bg-glass);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--codex-border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 90;
        }
        .nav-item-v2 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--codex-text-muted);
            text-decoration: none;
            border-radius: var(--codex-radius-md);
            font-weight: 600;
            transition: var(--codex-transition-fast);
            margin-bottom: 0.25rem;
        }
        .nav-item-v2:hover, .nav-item-v2.active {
            color: var(--codex-text-main);
            background: var(--codex-brand-subtle);
            box-shadow: inset 3px 0 0 var(--codex-brand-primary);
        }
    </style>
</head>
<body>
    <div class="shell-layout">
        <!-- Sidebar Navigation -->
        <aside class="shell-sidebar">
            <div style="display:flex; align-items:center; gap:0.75rem; padding-bottom:1.5rem; border-bottom:1px solid var(--codex-border-color); margin-bottom:1.5rem;">
                <div style="width:36px; height:36px; background:var(--codex-brand-primary); border-radius:0.75rem; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:1.2rem; box-shadow:var(--codex-shadow-glow);">C</div>
                <div>
                    <div style="font-weight:800; font-size:1.1rem; letter-spacing:-0.02em;">CODEX <span style="color:var(--codex-brand-primary);">ERP</span></div>
                    <div style="font-size:0.7rem; color:var(--codex-text-muted);">Enterprise Suite v2.0</div>
                </div>
            </div>

            <nav style="flex:1;">
                <a href="<?= base_url('dashboard') ?>" class="nav-item-v2 <?= (uri_string() === 'dashboard') ? 'active' : '' ?>">⚡ Dashboard BI</a>
                <a href="<?= base_url('ventas') ?>" class="nav-item-v2 <?= (str_contains(uri_string(), 'ventas')) ? 'active' : '' ?>">🛒 Ventas & POS</a>
                <a href="<?= base_url('compras') ?>" class="nav-item-v2 <?= (str_contains(uri_string(), 'compras')) ? 'active' : '' ?>">📦 Compras & P2P</a>
                <a href="<?= base_url('inventario') ?>" class="nav-item-v2 <?= (str_contains(uri_string(), 'inventario')) ? 'active' : '' ?>">📊 Inventario & Kardex</a>
                <a href="<?= base_url('caja') ?>" class="nav-item-v2 <?= (str_contains(uri_string(), 'caja')) ? 'active' : '' ?>">💵 Caja & Tesorería</a>
                <a href="<?= base_url('contabilidad') ?>" class="nav-item-v2 <?= (str_contains(uri_string(), 'contabilidad')) ? 'active' : '' ?>">📈 Contabilidad e IVA</a>
            </nav>

            <div style="padding-top:1rem; border-top:1px solid var(--codex-border-color);">
                <div class="codex-badge codex-badge-success" style="width:100%; justify-content:center;">
                    <span class="status-dot-pulse"></span> Sistema en línea
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="shell-main">
            <!-- Topbar Navigation -->
            <header class="shell-topbar">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <button type="button" onclick="document.dispatchEvent(new KeyboardEvent('keydown', {key:'k', ctrlKey:true}))" style="display:flex; align-items:center; gap:0.5rem; padding:0.4rem 0.8rem; background:var(--codex-bg-body); border:1px solid var(--codex-border-color); border-radius:var(--codex-radius-md); color:var(--codex-text-muted); cursor:pointer; font-size:0.85rem;">
                        <span>🔍 Buscar o ejecutar (Cmd+K)</span>
                        <span style="background:var(--codex-border-color); padding:0.15rem 0.4rem; border-radius:0.25rem; font-size:0.75rem;">Ctrl+K</span>
                    </button>
                </div>

                <div style="display:flex; align-items:center; gap:1rem;">
                    <!-- Theme Switcher -->
                    <button type="button" onclick="const t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', t);" style="background:transparent; border:none; color:var(--codex-text-main); font-size:1.2rem; cursor:pointer;" title="Cambiar tema">
                        🌓
                    </button>
                    <!-- User Avatar -->
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <div style="width:32px; height:32px; border-radius:50%; background:var(--codex-brand-subtle); display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--codex-brand-primary);">U</div>
                        <span style="font-weight:600; font-size:0.875rem;"><?= esc(auth_user()['username'] ?? 'Usuario') ?></span>
                    </div>
                </div>
            </header>

            <!-- Page Content Injection -->
            <main style="padding:1.5rem; flex:1;">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
</body>
</html>
