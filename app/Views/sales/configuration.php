<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Configuracion de Ventas</h1>
        <p class="text-secondary mb-0">Perfil Argentina ARCA, monedas habilitadas, modos de facturacion y servicios disponibles.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="post" action="<?= site_url('ventas/arca/diagnostico' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-outline-primary">Diagnosticar certificados</button>
        </form>
        <form method="post" action="<?= site_url('ventas/arca/test' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-outline-dark">Probar ARCA</button>
        </form>
        <a href="<?= site_url('ventas/configuracion/editar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark" data-popup="true" data-popup-title="Configuracion de Ventas" data-popup-subtitle="Ajustar perfil comercial y ARCA.">Editar</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h2 class="h4 mb-1">Readiness fiscal</h2>
                    <p class="text-secondary mb-0"><?= esc($arcaReadiness['summary'] ?? 'Sin resumen fiscal.') ?></p>
                </div>
                <div class="display-6 fw-semibold <?= ! empty($arcaReadiness['ready']) ? 'text-success' : 'text-warning' ?>"><?= esc((string) ($arcaReadiness['progress'] ?? 0)) ?>%</div>
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
            <h2 class="h4 mb-3">Comprobantes</h2>
            <?php foreach ($documentTypes as $documentType): ?>
                <div class="border rounded-3 p-3 mb-2">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <strong><?= esc($documentType['name']) ?></strong>
                            <div class="small text-secondary"><?= esc($documentType['code']) ?> / <?= esc($documentType['sequence_key']) ?> / <?= esc($documentType['channel']) ?></div>
                        </div>
                        <span class="small <?= (int) ($documentType['impacts_stock'] ?? 0) === 1 ? 'text-success' : 'text-secondary' ?>"><?= (int) ($documentType['impacts_stock'] ?? 0) === 1 ? 'Impacta stock' : 'Sin stock' ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h4 mb-3">Puntos de venta y cuenta corriente</h2>
            <?php foreach ($pointsOfSale as $pointOfSale): ?>
                <div class="border rounded-3 p-3 mb-2">
                    <strong><?= esc($pointOfSale['name']) ?></strong>
                    <div class="small text-secondary"><?= esc($pointOfSale['code']) ?> / <?= esc($pointOfSale['channel']) ?></div>
                </div>
            <?php endforeach; ?>
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
            <?php foreach ($deviceSettings as $device): ?>
                <div class="border rounded-3 p-3 mb-2">
                    <strong><?= esc($device['device_name']) ?></strong>
                    <div class="small text-secondary"><?= esc($device['channel']) ?> / <?= esc($device['device_type']) ?> / <?= esc($device['device_code']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if ($deviceSettings === []): ?><div class="text-secondary">Todavia no hay dispositivos configurados.</div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <h2 class="h4 mb-3">Bitacora de hardware</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Fecha</th><th>Canal</th><th>Evento</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php foreach ($hardwareLogs as $log): ?>
                            <tr>
                                <td><?= esc(! empty($log['created_at']) ? date('d/m/Y H:i', strtotime($log['created_at'])) : '-') ?></td>
                                <td><?= esc($log['channel']) ?></td>
                                <td><?= esc($log['event_type']) ?><div class="small text-secondary"><?= esc($log['device_type']) ?></div></td>
                                <td><?= esc($log['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($hardwareLogs === []): ?><tr><td colspan="4" class="text-secondary">Todavia no hay eventos de hardware.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <h2 class="h4 mb-3">Eventos ARCA recientes</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Fecha</th><th>Evento</th><th>Servicio</th><th>Comprobante</th><th>Estado</th><th>Mensaje</th></tr></thead>
                    <tbody>
                        <?php foreach ($arcaEvents as $event): ?>
                            <tr>
                                <td><?= esc(! empty($event['performed_at']) ? date('d/m/Y H:i', strtotime($event['performed_at'])) : '-') ?></td>
                                <td><?= esc($event['event_type']) ?></td>
                                <td><?= esc(strtoupper((string) $event['service_slug'])) ?></td>
                                <td><?= esc(trim((string) (($event['document_type_name'] ?? 'Comprobante') . ' ' . ($event['sale_number'] ?? '')))) ?></td>
                                <td><?= esc($event['status']) ?></td>
                                <td><?= esc($event['message'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($arcaEvents === []): ?><tr><td colspan="6" class="text-secondary">Todavia no hay eventos fiscales registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
