<?= $this->extend('layouts/main_v2') ?>

<?= $this->section('content') ?>
<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.75rem; font-weight:800; letter-spacing:-0.03em; margin:0;">Dashboard Ejecutivo & Analítica BI</h1>
    <p style="color:var(--codex-text-muted); margin-top:0.25rem;">Visión integral de operaciones comerciales, tesorería y facturación en tiempo real.</p>
</div>

<!-- Executive Metric Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <div class="codex-glass-card">
        <div style="font-size:0.75rem; font-weight:700; color:var(--codex-text-muted); text-transform:uppercase;">Ventas del Mes</div>
        <div style="font-size:1.8rem; font-weight:800; font-family:var(--codex-font-mono); margin:0.4rem 0;">$14.850.200</div>
        <div style="display:flex; align-items:center; gap:0.35rem; font-size:0.8rem; color:var(--codex-success); font-weight:700;">
            <span>↑ +14,2%</span> <span style="color:var(--codex-text-muted); font-weight:400;">vs mes anterior</span>
        </div>
    </div>

    <div class="codex-glass-card">
        <div style="font-size:0.75rem; font-weight:700; color:var(--codex-text-muted); text-transform:uppercase;">Facturación ARCA CAE</div>
        <div style="font-size:1.8rem; font-weight:800; font-family:var(--codex-font-mono); margin:0.4rem 0;">1.240</div>
        <div style="display:flex; align-items:center; gap:0.35rem; font-size:0.8rem; color:var(--codex-success); font-weight:700;">
            <span class="status-dot-pulse"></span> <span>99,8% Autorizadas</span>
        </div>
    </div>

    <div class="codex-glass-card">
        <div style="font-size:0.75rem; font-weight:700; color:var(--codex-text-muted); text-transform:uppercase;">Saldo en Caja & Bancos</div>
        <div style="font-size:1.8rem; font-weight:800; font-family:var(--codex-font-mono); margin:0.4rem 0;">$4.210.000</div>
        <div style="display:flex; align-items:center; gap:0.35rem; font-size:0.8rem; color:var(--codex-info); font-weight:700;">
            <span>📈 Conciliado</span>
        </div>
    </div>

    <div class="codex-glass-card">
        <div style="font-size:0.75rem; font-weight:700; color:var(--codex-text-muted); text-transform:uppercase;">Artículos a Reordenar (MRP I)</div>
        <div style="font-size:1.8rem; font-weight:800; font-family:var(--codex-font-mono); margin:0.4rem 0; color:var(--codex-warning);">8 SKUs</div>
        <div style="display:flex; align-items:center; gap:0.35rem; font-size:0.8rem; color:var(--codex-warning); font-weight:700;">
            <span>⚠️ Sugerencia emitida</span>
        </div>
    </div>
</div>

<!-- Main Analytics Grid -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:1.5rem;">
    <!-- Interactive Chart Container -->
    <div class="codex-glass-card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
            <h3 style="margin:0; font-size:1.1rem; font-weight:700;">Evolución de Ventas vs. Cobranzas (Semanal)</h3>
            <span class="codex-badge codex-badge-success">Tiempo Real</span>
        </div>
        
        <!-- SVG Sparkline Chart -->
        <div style="height:220px; width:100%; display:flex; align-items:flex-end; gap:1rem; padding-top:1rem;">
            <div style="flex:1; background:var(--codex-brand-subtle); height:45%; border-radius:0.5rem 0.5rem 0 0; position:relative;" title="Lun: $1.8M"><span style="position:absolute; top:-20px; font-size:0.75rem; width:100%; text-align:center;">Lun</span></div>
            <div style="flex:1; background:var(--codex-brand-subtle); height:65%; border-radius:0.5rem 0.5rem 0 0; position:relative;" title="Mar: $2.4M"><span style="position:absolute; top:-20px; font-size:0.75rem; width:100%; text-align:center;">Mar</span></div>
            <div style="flex:1; background:var(--codex-brand-subtle); height:85%; border-radius:0.5rem 0.5rem 0 0; position:relative;" title="Mié: $3.8M"><span style="position:absolute; top:-20px; font-size:0.75rem; width:100%; text-align:center;">Mié</span></div>
            <div style="flex:1; background:var(--codex-brand-primary); height:95%; border-radius:0.5rem 0.5rem 0 0; position:relative;" title="Jue: $4.2M"><span style="position:absolute; top:-20px; font-size:0.75rem; width:100%; text-align:center;">Jue</span></div>
            <div style="flex:1; background:var(--codex-brand-subtle); height:70%; border-radius:0.5rem 0.5rem 0 0; position:relative;" title="Vie: $2.9M"><span style="position:absolute; top:-20px; font-size:0.75rem; width:100%; text-align:center;">Vie</span></div>
        </div>
    </div>

    <!-- Live Activity Stream -->
    <div class="codex-glass-card">
        <h3 style="margin:0 0 1rem 0; font-size:1.1rem; font-weight:700;">Stream de Actividad</h3>
        <div style="display:flex; flex-direction:column; gap:0.85rem;">
            <div style="display:flex; align-items:flex-start; gap:0.75rem; font-size:0.85rem;">
                <span style="background:var(--codex-success-bg); color:var(--codex-success); padding:0.2rem 0.4rem; border-radius:0.25rem; font-weight:700;">CAE</span>
                <div>
                    <div>Factura A N° 0001-00004821 autorizada por ARCA</div>
                    <div style="font-size:0.75rem; color:var(--codex-text-muted);">Hace 2 minutos</div>
                </div>
            </div>
            <div style="display:flex; align-items:flex-start; gap:0.75rem; font-size:0.85rem;">
                <span style="background:var(--codex-brand-subtle); color:var(--codex-brand-primary); padding:0.2rem 0.4rem; border-radius:0.25rem; font-weight:700;">POS</span>
                <div>
                    <div>Venta al contado por $8.200,00 cobrada en Caja POS</div>
                    <div style="font-size:0.75rem; color:var(--codex-text-muted);">Hace 5 minutos</div>
                </div>
            </div>
            <div style="display:flex; align-items:flex-start; gap:0.75rem; font-size:0.85rem;">
                <span style="background:var(--codex-warning-bg); color:var(--codex-warning); padding:0.2rem 0.4rem; border-radius:0.25rem; font-weight:700;">MRP</span>
                <div>
                    <div>Requerimiento de compra sugerido para SKU-1048</div>
                    <div style="font-size:0.75rem; color:var(--codex-text-muted);">Hace 12 minutos</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
