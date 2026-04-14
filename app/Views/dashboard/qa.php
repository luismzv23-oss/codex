<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">QA integral</h1>
        <p class="text-secondary mb-0">Checklist operativo end-to-end por modulo del ERP.</p>
    </div>
    <div class="text-end">
        <div class="small text-secondary">Score global</div>
        <div class="display-6 fw-bold"><?= esc((string) ($qa['score'] ?? 0)) ?>%</div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h4 mb-1">Registrar ejecucion QA</h2>
                <p class="text-secondary mb-0">Deja evidencia de pruebas manuales por modulo y escenario.</p>
            </div>
        </div>
        <form method="post" action="<?= site_url('dashboard/qa/runs') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label">Modulo</label>
                <select name="module_name" class="form-select">
                    <?php foreach (($qa['modules'] ?? []) as $module): ?>
                        <option value="<?= esc($module['name']) ?>"><?= esc($module['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Escenario</label>
                <input type="text" name="scenario_code" class="form-control" placeholder="qa-end-to-end-01">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                    <option value="passed">Aprobado</option>
                    <option value="warning">Con alertas</option>
                    <option value="failed">Fallido</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Notas</label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-dark">Registrar</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php foreach (($qa['modules'] ?? []) as $module): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h4 mb-1"><?= esc($module['name']) ?></h2>
                            <p class="text-secondary mb-0">Validacion funcional por modulo.</p>
                        </div>
                        <div class="text-end">
                            <span class="badge text-bg-<?= $module['status'] === 'ready' ? 'success' : ($module['status'] === 'warning' ? 'warning' : 'danger') ?>"><?= esc(strtoupper($module['status'])) ?></span>
                            <div class="small mt-2"><?= esc((string) $module['score']) ?>%</div>
                        </div>
                    </div>
                    <?php foreach (($module['checks'] ?? []) as $check): ?>
                        <div class="border rounded-3 p-3 mb-2 d-flex justify-content-between align-items-center">
                            <span><?= esc($check['label']) ?></span>
                            <span class="badge text-bg-<?= ! empty($check['ok']) ? 'success' : 'danger' ?>"><?= ! empty($check['ok']) ? 'OK' : 'Pendiente' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 mt-4">
    <div class="card-body p-4">
        <h2 class="h4 mb-3">Ultimas ejecuciones</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Modulo</th><th>Escenario</th><th>Estado</th><th>Usuario</th><th>Notas</th></tr></thead>
                <tbody>
                    <?php foreach (($recentRuns ?? []) as $run): ?>
                        <tr>
                            <td><?= esc(! empty($run['executed_at']) ? date('d/m/Y H:i', strtotime($run['executed_at'])) : '-') ?></td>
                            <td><?= esc($run['module_name']) ?></td>
                            <td><?= esc($run['scenario_code']) ?></td>
                            <td><?= esc($run['status']) ?></td>
                            <td><?= esc($run['user_name'] ?? '-') ?></td>
                            <td><?= esc($run['notes'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($recentRuns ?? []) === []): ?><tr><td colspan="6" class="text-secondary">Todavia no hay ejecuciones QA registradas.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
