<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle ?? 'Codex') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
    <script src="<?= base_url('assets/js/alpine-components.js') ?>" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body>
    <main class="container py-5">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-7 col-lg-5">
                <?php if (session()->getFlashdata('message')): ?>
                    <div class="alert alert-success alert-dismissible fade show" x-data="toastNotifier('<?= esc(session()->getFlashdata('message')) ?>', 'success')" x-show="show" x-transition>
                        <?= esc(session()->getFlashdata('message')) ?>
                        <button type="button" class="btn-close" @click="dismiss()" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" x-data="toastNotifier('<?= esc(session()->getFlashdata('error')) ?>', 'danger')" x-show="show" x-transition>
                        <?= esc(session()->getFlashdata('error')) ?>
                        <button type="button" class="btn-close" @click="dismiss()" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('errors')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" x-data="toastNotifier('', 'danger')" x-show="show" x-transition>
                        <?php foreach ((array) session()->getFlashdata('errors') as $error): ?>
                            <div><?= esc($error) ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" @click="dismiss()" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </main>
</body>
</html>
