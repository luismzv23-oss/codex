<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $salesLive = true; ?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<?php if (!(($isPopup ?? false) || service('request')->getGet('popup') === '1')): ?>
<header class="insight-hero sales-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / VENTAS</div><h1 class="h2 mb-1">Configuracion de Ventas</h1>
        <p class="text-secondary mb-0">Perfil Argentina ARCA, monedas habilitadas, modos de facturacion y servicios disponibles.</p><div class="insight-identity"><i class="bi bi-bag-check" aria-hidden="true"></i><span>Gestión comercial · <?= esc($context['company']['name']) ?></span></div>
    </div>
    <?= view('sales/banner_actions', get_defined_vars()) ?>
</header>
<div class="sales-page-tools d-flex justify-content-end flex-wrap gap-2 mb-3">
        <form method="post" action="<?= site_url('ventas/arca/diagnostico' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-outline-primary" title="Diagnosticar certificados" aria-label="Diagnosticar certificados"><i class="bi bi-shield-check"></i></button>
        </form>
        <form method="post" action="<?= site_url('ventas/arca/test' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-outline-dark" title="Probar ARCA" aria-label="Probar ARCA"><i class="bi bi-plug"></i></button>
        </form>
        <a href="<?= site_url('ventas/configuracion/editar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark" data-popup="true" data-popup-title="Configuracion de Ventas" data-popup-subtitle="Ajustar perfil comercial y ARCA." title="Editar" aria-label="Editar"><i class="bi bi-pencil"></i></a>
    </div>
<?php endif; ?>
<form method="get" action="<?= site_url('ventas/configuracion') ?>" class="insight-filters sales-filters"><label>Empresa activa<select name="company_id" class="form-select"><?php foreach (($companies ?: [$context['company']]) as $option): ?><option value="<?= esc($option['id']) ?>" <?= $selectedCompanyId === $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select></label><button class="btn insight-action" title="Aplicar empresa" aria-label="Aplicar empresa"><i class="bi bi-arrow-repeat"></i></button></form>
<div id="sales-status" role="status" aria-live="polite" class="small text-secondary mb-2"></div>
<div id="sales-content">

<div class="row g-4">
    <!-- Interactive Search Toolbar with Company Filter -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 bg-light">
            <div class="card-body p-3 d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3 flex-grow-1">
                    <?php if (! empty($companies)): ?>

                    <?php endif; ?>
                    <div class="input-group flex-grow-1" style="max-width: 420px;">
                        <span class="input-group-text bg-white border-end-0 text-secondary"><i class="bi bi-search"></i></span>
                        <input type="text" id="salesSearchInput" class="form-control border-start-0 ps-0 shadow-none bg-white" placeholder="Filtrar comprobantes, puntos de venta, dispositivos..." aria-label="Buscar en configuración de ventas">
                        <button class="btn btn-white border border-start-0 text-secondary" type="button" id="clearSalesSearchBtn" style="display: none;" title="Limpiar busqueda"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="small text-secondary text-nowrap d-none d-lg-block">
                    <i class="bi bi-info-circle me-1"></i> Escribe para buscar en tiempo real en todos los listados de la página.
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h2 class="h4 mb-1">Readiness fiscal</h2>
                    <p class="text-secondary mb-0"><?= esc($arcaReadiness['summary'] ?? 'Sin resumen fiscal.') ?></p>
                </div>
                <div class="sales-kpi-value fw-semibold <?= ! empty($arcaReadiness['ready']) ? 'text-success' : 'text-warning' ?>"><?= esc((string) ($arcaReadiness['progress'] ?? 0)) ?>%</div>
            </div>
            <div class="row g-3 mt-1">
                <?php foreach (($arcaReadiness['checks'] ?? []) as $check): ?>
                    <div class="col-md-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary"><?= esc($check['label']) ?></div>
                            <div class="fw-semibold <?= ! empty($check['ok']) ? 'text-success' : 'text-danger' ?>"><?= ! empty($check['ok']) ? 'Listo' : 'Pendiente' ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h4 mb-3">Facturacion y monedas</h2>
            <dl class="row mb-0">
                <dt class="col-md-5">Perfil</dt><dd class="col-md-7"><?= esc($settings['profile'] ?? 'argentina_arca') ?></dd>
                <dt class="col-md-5">Modo estandar</dt><dd class="col-md-7"><?= (int) ($settings['invoice_mode_standard_enabled'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?></dd>
                <dt class="col-md-5">Modo kiosco</dt><dd class="col-md-7"><?= (int) ($settings['invoice_mode_kiosk_enabled'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?></dd>
                <dt class="col-md-5">Moneda por defecto</dt><dd class="col-md-7"><?= esc($settings['default_currency_code'] ?? '-') ?></dd>
                <dt class="col-md-5">Monedas empresa</dt><dd class="col-md-7"><?= esc(implode(', ', array_map(static fn($row) => $row['code'], $currencies))) ?></dd>
                <dt class="col-md-5">Punto venta estandar</dt><dd class="col-md-7"><?= esc((string) ($settings['point_of_sale_standard'] ?? 1)) ?></dd>
                <dt class="col-md-5">Punto venta kiosco</dt><dd class="col-md-7"><?= esc((string) ($settings['point_of_sale_kiosk'] ?? 2)) ?></dd>
                <dt class="col-md-5">Documento kiosco</dt><dd class="col-md-7"><?= esc($settings['kiosk_document_label'] ?? 'Ticket Consumidor Final') ?></dd>
                <dt class="col-md-5">Autorizacion automatica</dt><dd class="col-md-7"><?= (int) ($settings['arca_auto_authorize'] ?? 0) === 1 ? 'Activa' : 'Manual' ?></dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h4 mb-3">Integracion ARCA</h2>
            <dl class="row mb-3">
                <dt class="col-md-5">Integracion</dt><dd class="col-md-7"><?= (int) ($settings['arca_enabled'] ?? 0) === 1 ? 'Activa' : 'Pendiente' ?></dd>
                <dt class="col-md-5">Ambiente</dt><dd class="col-md-7"><?= esc($settings['arca_environment'] ?? 'homologacion') ?></dd>
                <dt class="col-md-5">CUIT</dt><dd class="col-md-7"><?= esc($settings['arca_cuit'] ?? '-') ?></dd>
                <dt class="col-md-5">IVA</dt><dd class="col-md-7"><?= esc($settings['arca_iva_condition'] ?? '-') ?></dd>
                <dt class="col-md-5">IIBB</dt><dd class="col-md-7"><?= esc($settings['arca_iibb'] ?? '-') ?></dd>
                <dt class="col-md-5">Alias</dt><dd class="col-md-7"><?= esc($settings['arca_alias'] ?? '-') ?></dd>
                <dt class="col-md-5">Ultimo WSAA</dt><dd class="col-md-7"><?= esc(! empty($settings['arca_last_wsaa_at']) ? date('d/m/Y H:i', strtotime($settings['arca_last_wsaa_at'])) : '-') ?></dd>
                <dt class="col-md-5">Vence TA</dt><dd class="col-md-7"><?= esc(! empty($settings['arca_last_ticket_expires_at']) ? date('d/m/Y H:i', strtotime($settings['arca_last_ticket_expires_at'])) : '-') ?></dd>
                <dt class="col-md-5">Ultimo error</dt><dd class="col-md-7"><?= esc($settings['arca_last_error'] ?? '-') ?></dd>
            </dl>
            <ul class="list-group list-group-flush">
                <?php foreach ($arcaServices as $service): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between"><span><?= esc($service['name']) ?></span><span class="<?= $service['enabled'] ? 'text-success' : 'text-secondary' ?>"><?= $service['enabled'] ? 'Habilitado' : 'No habilitado' ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h4 mb-3">Diagnostico de certificados</h2>
            <p class="text-secondary mb-3"><?= esc($arcaDiagnostics['summary'] ?? 'Sin diagnostico disponible.') ?></p>
            <dl class="row mb-3">
                <dt class="col-md-4">Bundle</dt><dd class="col-md-8"><?= ! empty($arcaDiagnostics['bundle_valid']) ? 'Valido' : 'Invalido' ?></dd>
                <dt class="col-md-4">Subject</dt><dd class="col-md-8"><?= esc($arcaDiagnostics['metadata']['subject'] ?? '-') ?></dd>
                <dt class="col-md-4">Issuer</dt><dd class="col-md-8"><?= esc($arcaDiagnostics['metadata']['issuer'] ?? '-') ?></dd>
                <dt class="col-md-4">Serial</dt><dd class="col-md-8"><?= esc($arcaDiagnostics['metadata']['serial'] ?? '-') ?></dd>
                <dt class="col-md-4">Vigencia</dt><dd class="col-md-8"><?= esc($arcaDiagnostics['metadata']['valid_to'] ?? '-') ?></dd>
                <dt class="col-md-4">Dias restantes</dt><dd class="col-md-8"><?= esc((string) ($arcaDiagnostics['metadata']['days_remaining'] ?? '-')) ?></dd>
            </dl>
            <?php foreach (($arcaDiagnostics['checks'] ?? []) as $check): ?>
                <div class="border rounded-3 p-2 mb-2 d-flex justify-content-between align-items-center">
                    <span><?= esc($check['label']) ?></span>
                    <span class="badge text-bg-<?= ! empty($check['ok']) ? 'success' : 'danger' ?>"><?= ! empty($check['ok']) ? 'OK' : 'Pendiente' ?></span>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h4 mb-3">Ambientes fiscales</h2>
            <?php foreach (($arcaEnvironments ?? []) as $environment): ?>
                <div class="border rounded-3 p-3 mb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= esc($environment['label']) ?></strong>
                        <div class="small text-secondary">Readiness por ambiente fiscal.</div>
                    </div>
                    <span class="badge text-bg-<?= ! empty($environment['ready']) ? 'success' : 'warning' ?>"><?= ! empty($environment['ready']) ? 'Listo' : 'Pendiente' ?></span>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">Comprobantes</h2>
                <a href="<?= site_url('ventas/comprobantes/nuevo' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark btn-sm icon-btn" data-popup="true" data-popup-title="Nuevo comprobante" data-popup-subtitle="Registrar un tipo de comprobante para la empresa activa." title="Nuevo comprobante" aria-label="Nuevo comprobante"><i class="bi bi-plus-lg"></i></a>
            </div>
            <div id="document-types-list">
                <?php foreach ($documentTypes as $documentType): ?>
                    <?php $docCompanyId = esc($documentType['company_id'] ?? $selectedCompanyId ?? ''); ?>
                    <div class="border rounded-3 p-3 mb-2 data-item">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <strong><?= esc($documentType['name']) ?></strong>
                                <?php if (! empty($documentType['is_default'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-1" style="font-size:0.75rem;">Predeterminado</span>
                                <?php endif; ?>
                                <?php if (isset($documentType['active']) && (int) $documentType['active'] === 0): ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1" style="font-size:0.75rem;">Inactivo</span>
                                <?php endif; ?>
                                <div class="small text-secondary"><?= esc($documentType['code']) ?> / <?= esc($documentType['sequence_key']) ?> / <?= esc($documentType['channel']) ?></div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="small <?= (int) ($documentType['impacts_stock'] ?? 0) === 1 ? 'text-success' : 'text-secondary' ?>"><?= (int) ($documentType['impacts_stock'] ?? 0) === 1 ? 'Impacta stock' : 'Sin stock' ?></span>
                                <?php if (empty($documentType['is_default'])): ?>
                                    <form method="post" action="<?= site_url('ventas/comprobantes/' . $documentType['id'] . '/predeterminada?company_id=' . $docCompanyId) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="company_id" value="<?= $docCompanyId ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning icon-btn" title="Establecer como predeterminado" aria-label="Predeterminado"><i class="bi bi-star"></i></button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-warning icon-btn" title="Comprobante predeterminado" disabled><i class="bi bi-star-fill text-dark"></i></button>
                                <?php endif; ?>
                                <a href="<?= site_url('ventas/comprobantes/' . $documentType['id'] . '/editar?company_id=' . $docCompanyId) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar comprobante" data-popup-subtitle="Modificar parámetros del comprobante." title="Editar" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="<?= site_url('ventas/comprobantes/' . $documentType['id'] . '/eliminar?company_id=' . $docCompanyId) ?>" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este comprobante?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="company_id" value="<?= $docCompanyId ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="no-results-row text-secondary text-center py-3" style="display: none;">No se encontraron comprobantes.</div>
                <?php if ($documentTypes === []): ?><div class="no-data-row text-secondary text-center py-3">No hay comprobantes configurados.</div><?php endif; ?>
            </div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h4 mb-3">Puntos de venta y cuenta corriente</h2>
            <div id="points-of-sale-list">
                <?php foreach ($pointsOfSale as $pointOfSale): ?>
                    <div class="border rounded-3 p-3 mb-2 data-item">
                        <strong><?= esc($pointOfSale['name']) ?></strong>
                        <div class="small text-secondary"><?= esc($pointOfSale['code']) ?> / <?= esc($pointOfSale['channel']) ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="no-results-row text-secondary text-center py-3" style="display: none;">No se encontraron puntos de venta.</div>
                <?php if ($pointsOfSale === []): ?><div class="no-data-row text-secondary text-center py-3">No hay puntos de venta configurados.</div><?php endif; ?>
            </div>
            <hr>
            <div class="small text-secondary">Comprobantes pendientes</div>
            <div class="fs-4 fw-semibold"><?= esc((string) ($receivableSummary['pending'] ?? 0)) ?></div>
            <div class="small text-secondary mt-2">Saldo a cobrar</div>
            <div class="fs-5 fw-semibold"><?= number_format((float) ($receivableSummary['balance'] ?? 0), 2, ',', '.') ?></div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h2 class="h4 mb-0">Dispositivos de mostrador</h2>
                <a href="<?= site_url('ventas/dispositivos/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Dispositivo de mostrador" data-popup-subtitle="Registrar impresoras, lectores y perifericos."><i class="bi bi-plus-lg"></i></a>
            </div>
            <div id="devices-list">
                <?php foreach ($deviceSettings as $device): ?>
                    <div class="border rounded-3 p-3 mb-2 data-item">
                        <strong><?= esc($device['device_name']) ?></strong>
                        <div class="small text-secondary"><?= esc($device['channel']) ?> / <?= esc($device['device_type']) ?> / <?= esc($device['device_code']) ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="no-results-row text-secondary text-center py-3" style="display: none;">No se encontraron dispositivos.</div>
                <?php if ($deviceSettings === []): ?><div class="no-data-row text-secondary text-center py-3">Todavia no hay dispositivos configurados.</div><?php endif; ?>
            </div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h4 mb-3">Bitacora de hardware</h2>
            <div class="table-responsive">
                <table data-sales-table class="table align-middle mb-0" id="hardware-logs-table">
                    <thead><tr><th>Fecha</th><th>Canal</th><th>Evento</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php foreach ($hardwareLogs as $log): ?>
                            <tr class="data-row">
                                <td><?= esc(! empty($log['created_at']) ? date('d/m/Y H:i', strtotime($log['created_at'])) : '-') ?></td>
                                <td><?= esc($log['channel']) ?></td>
                                <td><?= esc($log['event_type']) ?><div class="small text-secondary"><?= esc($log['device_type']) ?></div></td>
                                <td><?= esc($log['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="4" class="text-secondary text-center py-3">No se encontraron eventos de hardware.</td></tr>
                        <?php if ($hardwareLogs === []): ?><tr class="no-data-row"><td colspan="4" class="text-secondary text-center py-3">Todavia no hay eventos de hardware.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <h2 class="h4 mb-3">Eventos ARCA recientes</h2>
            <div class="table-responsive">
                <table data-sales-table class="table align-middle mb-0" id="arca-events-table">
                    <thead><tr><th>Fecha</th><th>Evento</th><th>Servicio</th><th>Comprobante</th><th>Estado</th><th>Mensaje</th></tr></thead>
                    <tbody>
                        <?php foreach ($arcaEvents as $event): ?>
                            <tr class="data-row">
                                <td><?= esc(! empty($event['performed_at']) ? date('d/m/Y H:i', strtotime($event['performed_at'])) : '-') ?></td>
                                <td><?= esc($event['event_type']) ?></td>
                                <td><?= esc(strtoupper((string) $event['service_slug'])) ?></td>
                                <td><?= esc(trim((string) (($event['document_type_name'] ?? 'Comprobante') . ' ' . ($event['sale_number'] ?? '')))) ?></td>
                                <td><?= esc($event['status']) ?></td>
                                <td><?= esc($event['message'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="6" class="text-secondary text-center py-3">No se encontraron eventos fiscales.</td></tr>
                        <?php if ($arcaEvents === []): ?><tr class="no-data-row"><td colspan="6" class="text-secondary text-center py-3">Todavia no hay eventos fiscales registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>


</div>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
