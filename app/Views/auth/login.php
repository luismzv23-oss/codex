<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="card shadow-lg border-0 rounded-4">
    <div class="card-body p-4 p-lg-5">
        <p class="text-uppercase small text-secondary mb-2">Modulo Core</p>
        <h1 class="h2 mb-4">Iniciar sesion</h1>
        <form method="post" action="<?= site_url('login') ?>" class="d-grid gap-3">
            <?= csrf_field() ?>
            <div>
                <label class="form-label">Usuario o correo</label>
                <input type="text" name="login" class="form-control" value="<?= esc(old('login')) ?>" required>
            </div>
            <div>
                <label class="form-label">Contrasena</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-dark">Entrar</button>
            <a href="<?= site_url('forgot-password') ?>" class="small text-decoration-none">Recuperar contrasena</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
