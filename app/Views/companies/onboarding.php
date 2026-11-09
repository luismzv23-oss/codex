<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/wizard-steps.css') . '?v=' . filemtime(FCPATH . 'assets/css/wizard-steps.css') ?>">
<div class="wizard-shell" id="company-onboarding">
    <header class="wizard-head">
        <div>
            <span class="wizard-eyebrow">EMPRESAS / ALTA</span>
            <h1>Asistente de alta de empresa</h1>
            <p>Registra la empresa con todo lo que necesita para operar: datos fiscales, módulos, administrador, estructura, impuestos, medios de pago, facturación electrónica y contabilidad.</p>
        </div>
        <a href="<?= site_url(($config['role'] ?? '') === 'superadmin' ? 'empresas' : 'configuracion') ?>" class="btn btn-outline-dark icon-btn" title="Volver" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
    </header>
    <ol class="wizard-steps" id="onboarding-steps" aria-label="Pasos del alta"></ol>
    <div id="onboarding-message" class="wizard-message" role="status" aria-live="polite" hidden></div>
    <section class="wizard-card">
        <div class="wizard-card-title">
            <div><span class="wizard-eyebrow" id="onboarding-caption">PASO 1 DE 9</span><h2 id="onboarding-title">Empresa</h2></div>
            <span class="wizard-chip" id="onboarding-company"></span>
        </div>
        <form id="onboarding-form" class="wizard-body" novalidate></form>
        <div class="wizard-actions">
            <button type="button" id="onboarding-prev" class="btn btn-outline-dark icon-btn" title="Paso anterior" aria-label="Paso anterior"><i class="bi bi-arrow-left"></i></button>
            <span id="onboarding-help" class="small text-secondary"></span>
            <div class="d-flex gap-2">
                <button type="button" id="onboarding-skip" class="btn btn-outline-secondary" hidden>Configurar más tarde</button>
                <button type="button" id="onboarding-save" class="btn btn-dark"><i class="bi bi-check2 me-1" aria-hidden="true"></i><span>Guardar y continuar</span></button>
            </div>
        </div>
    </section>
</div>
<script id="onboarding-config" type="application/json"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script type="module" src="<?= base_url('assets/js/company-onboarding.js') . '?v=' . filemtime(FCPATH . 'assets/js/company-onboarding.js') ?>"></script>
<?= $this->endSection() ?>
