<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/kardex-dashboard.css') ?>">
<?php $q = '?company_id=' . urlencode($companyId) . '&dias=' . (int) $report['days']; ?>
<div class="insight-shell inventory-shell kardex-shell">
<header class="insight-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / CONTROL DE STOCK</div>
        <h1>Informe semanal de stock</h1>
        <p>Detecta las causas de las diferencias entre el stock real y el del sistema, y el avance del conteo cíclico.</p>
        <div class="insight-identity"><i class="bi bi-clipboard-data" aria-hidden="true"></i><span><?= esc($company['name'] ?? '') ?> · Semana <?= esc($report['week']) ?></span></div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" id="stock-report-refresh" class="btn btn-outline-dark icon-btn" title="Actualizar informe" aria-label="Actualizar informe"><i class="bi bi-arrow-clockwise"></i></button>
        <a href="<?= site_url('inventario/control-semanal/informe/pdf' . $q) ?>" target="_blank" class="btn btn-outline-dark icon-btn" title="Exportar PDF" aria-label="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
        <a href="<?= site_url('inventario/control-semanal/informe/xlsx' . $q) ?>" class="btn btn-outline-dark icon-btn" title="Exportar Excel" aria-label="Exportar Excel"><i class="bi bi-file-earmark-excel"></i></a>
        <a href="<?= site_url('inventario/control-semanal?company_id=' . urlencode($companyId)) ?>" class="btn btn-outline-dark icon-btn" title="Control semanal" aria-label="Control semanal"><i class="bi bi-clipboard-check"></i></a>
        <a href="<?= site_url('inventario?company_id=' . urlencode($companyId)) ?>" class="btn btn-outline-dark icon-btn" title="Volver a Inventario" aria-label="Volver a Inventario"><i class="bi bi-box-seam"></i></a>
    </div>
</header>
<form method="get" action="<?= site_url('inventario/control-semanal/informe') ?>" class="kardex-filter-card">
    <div class="insight-filters">
        <?php if (! empty($companies)): ?><label>Empresa activa<select name="company_id" class="form-select" onchange="this.form.submit()"><?php foreach ($companies as $co): ?><option value="<?= esc($co['id']) ?>" <?= $co['id'] === $companyId ? 'selected' : '' ?>><?= esc($co['name']) ?></option><?php endforeach; ?></select></label><?php else: ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
        <label>Período analizado<select name="dias" class="form-select" onchange="this.form.submit()">
            <?php foreach ([7, 14, 30, 60, 90] as $d): ?><option value="<?= $d ?>" <?= (int) $report['days'] === $d ? 'selected' : '' ?>>Últimos <?= $d ?> días</option><?php endforeach; ?>
        </select></label>
        <div class="insight-filter-actions"><button class="btn insight-action" title="Aplicar filtros y actualizar" aria-label="Aplicar filtros y actualizar"><i class="bi bi-arrow-repeat"></i></button></div>
    </div>
</form>
<div id="stock-report-status" class="small text-secondary" role="status" aria-live="polite"></div>
<div id="stock-report-panel"><?= view('inventory/control/_report_panels', ['report' => $report, 'companyId' => $companyId]) ?></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const status = document.getElementById('stock-report-status');
    const button = document.getElementById('stock-report-refresh');
    let busy = false;
    const reload = async () => {
        if (busy) return;
        busy = true; button.disabled = true; status.textContent = 'Actualizando...';
        try {
            const response = await fetch(window.location.href, {headers: {'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store'});
            if (!response.ok) throw new Error(response.status);
            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('stock-report-panel');
            if (!fresh) throw new Error('sin datos');
            const panel = document.getElementById('stock-report-panel');
            // Keep the page of each paginated list while replacing the data.
            const pages = Array.from(panel.querySelectorAll('.codex-pagination__btn.is-active')).map(b => b.textContent);
            panel.innerHTML = fresh.innerHTML;
            window.initCodexPagination?.(panel);
            panel.querySelectorAll('.codex-pagination').forEach((pager, i) => {
                const target = pages[i]; if (!target || target === '1') return;
                const btn = Array.from(pager.querySelectorAll('.codex-pagination__btn')).find(b => b.textContent === target);
                btn?.click();
            });
            status.textContent = 'Datos actualizados';
        } catch (e) {
            status.textContent = 'No se pudo actualizar. Se conservan los últimos datos.';
        } finally { busy = false; button.disabled = false; }
    };
    button.addEventListener('click', reload);
    setInterval(() => { if (!document.hidden && !document.querySelector('.codex-popup.is-open, .modal.show')) reload(); }, 60000);
    window.addEventListener('codex:item-saved', reload);
});
</script>
<?= $this->endSection() ?>
