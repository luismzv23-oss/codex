<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 mb-1"><?= esc($pageTitle) ?></h2>
        <p class="text-secondary mb-0 small">Movimientos de la cuenta <strong><?= esc($account['code'] ?? '') ?> — <?= esc($account['name'] ?? '') ?></strong>.</p>
    </div>
    <div>
        <a href="<?= site_url('contabilidad?company_id=' . $selectedCompanyId) ?>" class="btn btn-outline-dark btn-sm icon-btn" title="Volver a Contabilidad" aria-label="Volver a Contabilidad"><i class="bi bi-arrow-left"></i></a>
    </div>
</div>
<form class="row g-2 mb-3">
    <input type="hidden" name="company_id" value="<?= esc($selectedCompanyId) ?>">
    <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($filters['from'] ?? '') ?>"></div>
    <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($filters['to'] ?? '') ?>"></div>
    <div class="col-auto"><button class="btn btn-outline-dark btn-sm"><i class="bi bi-search"></i></button></div>
</form>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Asiento</th><th>Fecha</th><th>Descripcion</th><th class="text-end">Debe</th><th class="text-end">Haber</th><th class="text-end">Saldo</th></tr></thead>
            <tbody>
                <?php if (empty($ledger['entries'])): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-4">Sin movimientos en el periodo.</td></tr>
                <?php else: ?>
                    <?php foreach ($ledger['entries'] as $e): ?>
                        <tr>
                            <td class="fw-semibold">#<?= esc($e['entry_number'] ?? '') ?></td>
                            <td><?= esc($e['entry_date'] ?? '') ?></td>
                            <td><?= esc($e['description'] ?? $e['entry_description'] ?? '') ?></td>
                            <td class="text-end"><?= (float)($e['debit'] ?? 0) > 0 ? number_format((float)$e['debit'], 2, ',', '.') : '' ?></td>
                            <td class="text-end"><?= (float)($e['credit'] ?? 0) > 0 ? number_format((float)$e['credit'], 2, ',', '.') : '' ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)($e['running_balance'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-dark"><td colspan="3" class="fw-bold">Saldo final</td><td></td><td></td><td class="text-end fw-bold"><?= number_format((float)($ledger['final_balance'] ?? 0), 2, ',', '.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
