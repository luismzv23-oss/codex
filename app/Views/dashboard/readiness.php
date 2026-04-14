<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Diagnostico ERP</h1>
        <p class="text-secondary mb-0">Checklist operativo para medir readiness real de la empresa.</p>
    </div>
    <div class="text-end">
        <div class="small text-secondary">Estado</div>
        <div class="badge text-bg-<?= $readiness['status'] === 'ready' ? 'success' : ($readiness['status'] === 'warning' ? 'warning' : 'danger') ?> fs-6"><?= esc(strtoupper($readiness['status'])) ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="small text-secondary mb-2">Readiness</div>
                <div class="display-5 fw-bold"><?= esc((string) ($readiness['score'] ?? 0)) ?>%</div>
                <div class="small text-secondary mt-2"><?= esc($readiness['company']['name'] ?? 'Sin empresa') ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Bloqueos</h2>
                <?php foreach (($readiness['blocking'] ?? []) as $item): ?>
                    <div class="border rounded-3 p-3 mb-2 text-danger"><?= esc($item) ?></div>
                <?php endforeach; ?>
                <?php if (($readiness['blocking'] ?? []) === []): ?><div class="text-secondary">Sin bloqueos criticos.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Advertencias</h2>
                <?php foreach (($readiness['warnings'] ?? []) as $item): ?>
                    <div class="border rounded-3 p-3 mb-2 text-warning-emphasis"><?= esc($item) ?></div>
                <?php endforeach; ?>
                <?php if (($readiness['warnings'] ?? []) === []): ?><div class="text-secondary">Sin advertencias abiertas.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Checklist completo</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Control</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($readiness['checks'] ?? []) as $check): ?>
                                <tr>
                                    <td><?= esc($check['label']) ?></td>
                                    <td><?= ! empty($check['critical']) ? 'Critico' : 'Operativo' ?></td>
                                    <td><span class="badge text-bg-<?= ! empty($check['ok']) ? 'success' : (! empty($check['critical']) ? 'danger' : 'warning') ?>"><?= ! empty($check['ok']) ? 'OK' : 'Pendiente' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
