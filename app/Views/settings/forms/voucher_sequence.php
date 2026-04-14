<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Numeracion nueva</h2>
            <p class="text-secondary mb-0">Define el documento, sucursal y correlativo inicial para comprobantes.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <div class="col-md-6"><label class="form-label">Tipo de documento</label><input class="form-control" name="document_type" required></div>
            <div class="col-md-6"><label class="form-label">Sucursal</label><select class="form-select" name="branch_id"><option value="">Todas</option><?php foreach ($branches as $branch): ?><option value="<?= esc($branch['id']) ?>"><?= esc($branch['name']) ?> (<?= esc($branch['code']) ?>)</option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Prefijo</label><input class="form-control" name="prefix"></div>
            <div class="col-md-6"><label class="form-label">Correlativo inicial</label><input class="form-control" name="current_number" value="1" required></div>
            <div class="col-md-6"><label class="form-label">Estado</label><select class="form-select" name="active"><option value="1">Activa</option><option value="0">Inactiva</option></select></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('configuracion?company_id=' . $companyId)) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
