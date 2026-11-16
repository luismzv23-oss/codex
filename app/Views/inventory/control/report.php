<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $q = '?company_id=' . urlencode($companyId) . '&dias=' . (int) $report['days']; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Informe semanal de stock</h1>
        <p class="text-muted mb-0"><?= esc($company['name'] ?? '') ?> · Semana <?= esc($report['week']) ?> · generado <?= esc($report['generated_at']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="get" class="d-flex gap-1"><input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <select name="dias" class="form-select form-select-sm" onchange="this.form.submit()"><?php foreach ([7, 14, 30, 60, 90] as $d): ?><option value="<?= $d ?>" <?= (int) $report['days'] === $d ? 'selected' : '' ?>>Últimos <?= $d ?> días</option><?php endforeach; ?></select></form>
        <a class="btn btn-outline-dark" href="<?= site_url('inventario/control-semanal/informe/pdf' . $q) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <a class="btn btn-outline-dark" href="<?= site_url('inventario/control-semanal/informe/xlsx' . $q) ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a class="btn btn-outline-dark" href="<?= site_url('inventario/control-semanal?company_id=' . urlencode($companyId)) ?>"><i class="bi bi-arrow-left"></i> Control semanal</a>
    </div>
</div>
<style>.report-title { font-size: 1.05rem; margin-top: 1.5rem; } .report-title small { color: #888; }</style>
<?= view('inventory/control/_report_body', ['report' => $report]) ?>
<?= $this->endSection() ?>
