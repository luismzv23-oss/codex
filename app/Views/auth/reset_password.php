<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="card shadow-lg border-0 rounded-4">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 mb-3">Nueva contrasena</h1>
        <form method="post" action="<?= site_url('reset-password/' . $selector . '/' . $token) ?>" class="d-grid gap-3">
            <?= csrf_field() ?>
            <div>
                <label class="form-label">Contrasena</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Confirmar contrasena</label>
                <input type="password" name="password_confirm" class="form-control" required>
            </div>
            <button class="btn btn-dark">Actualizar contrasena</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
