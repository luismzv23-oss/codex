<div class="col-lg-6" id="settings-payment-methods-card" data-company-id="<?= esc($company['id']) ?>" data-refresh-url="<?= site_url('configuracion/medios-pago/listado?company_id=' . $company['id']) ?>">
    <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h2 class="h4 mb-0">Medios de Pagos</h2>
                <?php if (auth_can('settings.manage')): ?>
                    <a href="<?= site_url('configuracion/medios-pago/nuevo?company_id=' . $company['id']) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo medio de pago" data-popup-subtitle="Configurar un medio de pago para la empresa activa." title="Nuevo medio de pago" aria-label="Nuevo medio de pago"><i class="bi bi-plus-lg" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
            <ul class="list-group list-group-flush" id="settings-payment-methods" data-settings-pagination="4" aria-label="Medios de Pagos">
                <?php if (empty($paymentMethods)): ?>
                    <li class="list-group-item px-0 text-secondary" data-pagination-empty>No hay medios de pago registrados.</li>
                <?php endif; ?>
                <?php foreach ($paymentMethods as $method): ?>
                    <li class="list-group-item px-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2" data-record-id="<?= esc($method['id']) ?>" tabindex="-1">
                        <div class="text-break">
                            <span class="fw-medium"><?= esc($method['name']) ?></span> <span class="text-secondary">(<?= esc($method['code']) ?>)</span>
                            <div class="small text-secondary"><?= esc(\App\Libraries\CompanyPaymentMethodService::TYPES[$method['type']] ?? $method['type']) ?> · <?= (int) $method['active'] === 1 ? 'Activo' : 'Inactivo' ?></div>
                        </div>
                        <?php if (auth_can('settings.manage')): ?>
                            <div class="d-flex gap-2">
                                <a href="<?= site_url('configuracion/medios-pago/' . $method['id'] . '/editar?company_id=' . $company['id']) ?>" class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar medio de pago" title="Editar medio de pago" aria-label="Editar medio de pago"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                <form method="post" action="<?= site_url('configuracion/medios-pago/' . $method['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar este medio del catálogo? Se conservará su registro histórico.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="company_id" value="<?= esc($company['id']) ?>">
                                    <button type="submit" class="btn btn-outline-danger icon-btn" title="Eliminar medio de pago" aria-label="Eliminar medio de pago"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
