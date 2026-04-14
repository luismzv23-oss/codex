<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1"><?= empty($system) ? 'Sistema nuevo' : 'Editar sistema' ?></h2>
            <p class="text-secondary mb-0">Define identidad, punto de entrada y estado del sistema dentro del ecosistema.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="name" value="<?= esc(old('name', $system['name'] ?? '')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Slug</label><input class="form-control" name="slug" value="<?= esc(old('slug', $system['slug'] ?? '')) ?>" required></div>
            <div class="col-md-8"><label class="form-label">URL de entrada</label><input class="form-control" name="entry_url" value="<?= esc(old('entry_url', $system['entry_url'] ?? '')) ?>" placeholder="ventas o https://app.example.com"></div>
            <div class="col-md-4"><label class="form-label">Icono</label><input class="form-control" name="icon" value="<?= esc(old('icon', $system['icon'] ?? 'bi-grid')) ?>" placeholder="bi-grid"></div>
            <div class="col-12"><label class="form-label">Descripcion</label><textarea class="form-control" name="description" rows="3"><?= esc(old('description', $system['description'] ?? '')) ?></textarea></div>
            <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="active"><option value="1" <?= (string) old('active', $system['active'] ?? '1') === '1' ? 'selected' : '' ?>>Activo</option><option value="0" <?= (string) old('active', $system['active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactivo</option></select></div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <?php if ($isPopup): ?>
                    <button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('sistemas')) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i></button>
                <?php else: ?>
                    <a href="<?= site_url('sistemas') ?>" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
