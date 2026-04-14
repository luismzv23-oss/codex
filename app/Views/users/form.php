<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="mb-3">
                    <h2 class="h5 mb-1"><?= empty($userRow) ? 'Usuario nuevo' : 'Editar usuario' ?></h2>
                    <p class="text-secondary mb-0">Configura credenciales, empresa, sucursal y rol operativo del usuario.</p>
                </div>
                <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
                    <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="name" value="<?= esc(old('name', $userRow['name'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Usuario</label><input class="form-control" name="username" value="<?= esc(old('username', $userRow['username'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" type="email" name="email" value="<?= esc(old('email', $userRow['email'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Contrasena <?= $userRow ? '(opcional)' : '' ?></label><input class="form-control" type="password" name="password" <?= $userRow ? '' : 'required' ?>></div>
                    <div class="col-md-6">
                        <label class="form-label">Empresa</label>
                        <select class="form-select" name="company_id" id="company_id" required>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= esc($company['id']) ?>" <?= old('company_id', $userRow['company_id'] ?? '') === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sucursal</label>
                        <select class="form-select" name="branch_id" id="branch_id" required></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="role_slug" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= esc($role['slug']) ?>" <?= old('role_slug', $userRow['role_slug'] ?? '') === $role['slug'] ? 'selected' : '' ?>><?= esc($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="active"><option value="1" <?= (string) old('active', $userRow['active'] ?? '1') === '1' ? 'selected' : '' ?>>Activo</option><option value="0" <?= (string) old('active', $userRow['active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactivo</option></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cambio clave</label>
                        <select class="form-select" name="must_change_password"><option value="0" <?= (string) old('must_change_password', $userRow['must_change_password'] ?? '0') === '0' ? 'selected' : '' ?>>No</option><option value="1" <?= (string) old('must_change_password', $userRow['must_change_password'] ?? '0') === '1' ? 'selected' : '' ?>>Si</option></select>
                    </div>
                    <div class="col-12 d-flex gap-2 pt-2">
                        <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                        <?php if ($isPopup): ?>
                            <button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('usuarios')) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i></button>
                        <?php else: ?>
                            <a href="<?= site_url('usuarios') ?>" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    (() => {
        const companySelect = document.getElementById('company_id');
        const branchSelect = document.getElementById('branch_id');
        const branchesByCompany = <?= json_encode($branchesByCompany, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const selectedBranch = <?= json_encode(old('branch_id', $userRow['branch_id'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        if (!companySelect || !branchSelect) return;
        const renderBranches = () => {
            const companyId = companySelect.value;
            const branches = branchesByCompany[companyId] || [];
            branchSelect.innerHTML = '';
            if (branches.length === 0) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Sin sucursales disponibles';
                branchSelect.appendChild(option);
                return;
            }
            branches.forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = `${branch.name} (${branch.code})`;
                option.selected = branch.id === selectedBranch;
                branchSelect.appendChild(option);
            });
        };
        companySelect.addEventListener('change', renderBranches);
        renderBranches();
    })();
</script>
<?= $this->endSection() ?>
