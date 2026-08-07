<?= $this->extend('layouts/main_v2') ?>

<?= $this->section('content') ?>
<div style="margin-bottom:1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
    <div>
        <div style="display:flex; align-items:center; gap:0.5rem;">
            <h1 style="font-size:1.75rem; font-weight:800; letter-spacing:-0.03em; margin:0;">Centro de Reportes BI & Analítica ERP</h1>
            <span class="codex-badge codex-badge-warning" style="font-weight:700;">🔒 Exclusivo Admin / Superadmin</span>
        </div>
        <p style="color:var(--codex-text-muted); margin-top:0.25rem;">Consola centralizada de informes gerenciales, cumplimiento impositivo ARCA y analítica financiera.</p>
    </div>

    <!-- Date Filter Form -->
    <form method="get" action="<?= base_url('reports') ?>" style="display:flex; align-items:center; gap:0.75rem; background:var(--codex-bg-glass); padding:0.5rem 1rem; border-radius:var(--codex-radius-md); border:1px solid var(--codex-border-color);">
        <label style="font-size:0.8rem; font-weight:600; color:var(--codex-text-muted);">Desde:</label>
        <input type="date" name="start_date" value="<?= esc($startDate) ?>" style="background:var(--codex-bg-body); color:var(--codex-text-main); border:1px solid var(--codex-border-color); padding:0.35rem 0.5rem; border-radius:var(--codex-radius-sm); font-size:0.85rem;">
        <label style="font-size:0.8rem; font-weight:600; color:var(--codex-text-muted);">Hasta:</label>
        <input type="date" name="end_date" value="<?= esc($endDate) ?>" style="background:var(--codex-bg-body); color:var(--codex-text-main); border:1px solid var(--codex-border-color); padding:0.35rem 0.5rem; border-radius:var(--codex-radius-sm); font-size:0.85rem;">
        <button type="submit" class="codex-btn-v2 codex-btn-v2-primary" style="padding:0.35rem 0.85rem; font-size:0.85rem;">Filtrar</button>
    </form>
</div>

<!-- Executive Summary Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <div class="codex-glass-card">
        <div style="font-size:0.75rem; font-weight:700; color:var(--codex-text-muted); text-transform:uppercase;">Flujo de Caja Proyectado</div>
        <div style="font-size:1.6rem; font-weight:800; font-family:var(--codex-font-mono); margin:0.4rem 0; color:var(--codex-success);">$<?= number_format($cashFlowForecast['projected_inflows'], 2, ',', '.') ?></div>
        <div style="font-size:0.8rem; color:var(--codex-text-muted);">Cuentas a cobrar + Cheques en cartera</div>
    </div>

    <div class="codex-glass-card">
        <div style="font-size:0.75rem; font-weight:700; color:var(--codex-text-muted); text-transform:uppercase;">Cheques en Cartera</div>
        <div style="font-size:1.6rem; font-weight:800; font-family:var(--codex-font-mono); margin:0.4rem 0; color:var(--codex-info);">$<?= number_format($cashFlowForecast['checks_in_portfolio'], 2, ',', '.') ?></div>
        <div style="font-size:0.8rem; color:var(--codex-text-muted);">Valores pendientes de depósito</div>
    </div>

    <div class="codex-glass-card">
        <div style="font-size:0.75rem; font-weight:700; color:var(--codex-text-muted); text-transform:uppercase;">Deuda Vencida Clientes</div>
        <div style="font-size:1.6rem; font-weight:800; font-family:var(--codex-font-mono); margin:0.4rem 0; color:var(--codex-warning);">$<?= number_format($cashFlowForecast['receivables_due'], 2, ',', '.') ?></div>
        <div style="font-size:0.8rem; color:var(--codex-text-muted);">Pendiente de cobro en Cta Cte</div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="codex-datagrid-container">
    <div class="codex-datagrid-toolbar">
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <button class="codex-btn-v2 codex-btn-v2-primary" style="font-size:0.85rem;">📊 Ranking 80/20 Ventas</button>
            <button class="codex-btn-v2" style="font-size:0.85rem; background:var(--codex-bg-body); color:var(--codex-text-muted); border:1px solid var(--codex-border-color);">⏳ Aging de Deudores</button>
            <button class="codex-btn-v2" style="font-size:0.85rem; background:var(--codex-bg-body); color:var(--codex-text-muted); border:1px solid var(--codex-border-color);">📦 Kardex Valorizado</button>
        </div>

        <a href="<?= base_url('reports/export/aging') ?>" class="codex-btn-v2" style="background:var(--codex-success-bg); color:var(--codex-success); border:1px solid var(--codex-success); text-decoration:none; font-size:0.85rem;">
            📥 Exportar Excel/CSV
        </a>
    </div>

    <!-- Data Table View -->
    <table class="codex-table-v2 density-normal">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Producto / Artículo</th>
                <th style="text-align:right;">Cantidad Vendida</th>
                <th style="text-align:right;">Importe Total ($)</th>
                <th style="text-align:center;">Participación (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($paretoSales)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding:2rem; color:var(--codex-text-muted);">No existen registros de ventas en el período seleccionado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($paretoSales as $row): ?>
                    <tr>
                        <td class="font-mono-val"><?= esc($row['sku']) ?></td>
                        <td style="font-weight:600;"><?= esc($row['product_name']) ?></td>
                        <td style="text-align:right;" class="font-mono-val"><?= number_format($row['total_qty'], 0, ',', '.') ?></td>
                        <td style="text-align:right; font-weight:700;" class="font-mono-val">$<?= number_format($row['total_amount'], 2, ',', '.') ?></td>
                        <td style="text-align:center;"><span class="codex-badge codex-badge-info">Top <?= esc($row['sku']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
