<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="card shadow-lg border-0 rounded-4">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 mb-3">Recuperar contrasena</h1>
        <p class="text-secondary">Ingresa tu correo y generaremos un enlace de restablecimiento para entorno local.</p>
        <form method="post" action="<?= site_url('forgot-password') ?>" class="d-grid gap-3">
            <?= csrf_field() ?>
            <div>
                <label class="form-label">Correo</label>
                <input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>" required>
            </div>
            <button class="btn btn-dark">Generar enlace</button>
            <a href="<?= site_url('login') ?>" class="small text-decoration-none">Volver al login</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
