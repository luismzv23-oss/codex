<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/cash-dashboard.css') ?>">
<div class="insight-shell cash-shell">
    <?php if (!(($isPopup ?? false) || service('request')->getGet('popup') === '1')): ?>
    <header class="insight-hero">
        <div>
            <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / CAJA</div>
            <h1>Mi caja</h1>
            <p>Apertura y cierre de tu caja.</p>
            <div class="insight-identity"><i class="bi bi-wallet2" aria-hidden="true"></i><span><?= esc($context['company']['name']) ?></span></div>
        </div>
    </header>
    <?php endif; ?>
    <section class="insight-panel mt-4" id="seller-cash" data-company="<?= esc($context['company']['id']) ?>">
        <div class="insight-panel-heading">
            <div>
                <h2>Gestión de cajas</h2>
                <p><?= $hasOpenSession ? 'Esta es la caja que abriste. Al finalizar tu turno, registra el cierre.' : 'No tienes una caja abierta. Elige una caja disponible para comenzar.' ?></p>
            </div>
        </div>
        <div data-cash-registers>
            <?php foreach ($registers as $register): ?>
                <?php $active = $activeSessionsMap[$register['id']] ?? null; ?>
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap border-bottom py-3" data-cash-register>
                    <div>
                        <strong><?= esc($register['name']) ?></strong>
                        <div class="small text-secondary"><?= esc($register['code']) ?> · <?= $active ? 'Abierta' : 'Disponible' ?></div>
                        <?php if ($active): ?><div class="small text-secondary">Apertura: <?= esc(date('d/m/Y H:i', strtotime($active['opened_at']))) ?></div><?php endif; ?>
                    </div>
                    <?php if (!empty($context['canManage'])): ?>
                        <?php if ($active): ?>
                            <a href="<?= site_url('caja/sesiones/' . $active['id'] . '/cierre') ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Cierre de caja"><i class="bi bi-box-arrow-down me-2" aria-hidden="true"></i>Cerrar caja</a>
                        <?php elseif (!$hasOpenSession): ?>
                            <a href="<?= site_url('caja/sesiones/apertura/nueva?cash_register_id=' . rawurlencode($register['id'])) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Apertura de caja"><i class="bi bi-box-arrow-up me-2" aria-hidden="true"></i>Abrir caja</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($registers === []): ?><p class="text-secondary mb-0">No hay cajas disponibles para abrir. Contacta al administrador.</p><?php endif; ?>
        </div>
        <div class="d-flex align-items-center justify-content-between gap-2 mt-3" data-cash-pagination hidden>
            <span class="small text-secondary" data-cash-page-label></span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-cash-prev aria-label="Página anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-cash-next aria-label="Página siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
    </section>
    <div class="small text-secondary mt-2" id="seller-cash-status" role="status" aria-live="polite"></div>
</div>
<script src="<?= base_url('assets/js/seller-cash.js') ?>" defer></script>
<?= $this->endSection() ?>
