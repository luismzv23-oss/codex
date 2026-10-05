<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/cash-dashboard.css') ?>">

<div class="insight-shell cash-shell" id="cash-dashboard">
    <header class="insight-hero">
        <div>
            <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / CAJA Y TESORERÍA</div>
            <h1>Caja y Tesorería</h1>
            <p>Apertura, arqueos, cierres y seguimiento operativo de ingresos y egresos en tiempo real.</p>
            <div class="insight-identity">
                <i class="bi bi-wallet2" aria-hidden="true"></i>
                <span>Gestión de tesorería · <?= esc($companies[0]['name'] ?? 'Empresa activa') ?></span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn icon-btn" id="cash-dashboard-refresh" title="Actualizar resumen" aria-label="Actualizar resumen"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
            <?php if (! empty($context['canManage'])): ?>
                <a href="<?= site_url('caja/sesiones/apertura/nueva' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Apertura de caja" data-popup-subtitle="Registrar una nueva sesión activa." title="Abrir caja" aria-label="Abrir caja"><i class="bi bi-box-arrow-in-up"></i></a>
                <a href="<?= site_url('caja/movimientos/nuevo' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn icon-btn" data-popup="true" data-popup-title="Movimiento de caja" data-popup-subtitle="Registrar un movimiento manual." title="Nuevo movimiento" aria-label="Nuevo movimiento"><i class="bi bi-plus-lg"></i></a>
                <a href="<?= site_url('caja/cheques/nuevo' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn icon-btn" data-popup="true" data-popup-title="Cheque" data-popup-subtitle="Registrar cheque recibido o emitido." title="Nuevo cheque" aria-label="Nuevo cheque"><i class="bi bi-journal-check"></i></a>
                <a href="<?= site_url('caja/conciliaciones/nueva' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn icon-btn" data-popup="true" data-popup-title="Conciliación de caja" data-popup-subtitle="Conciliar por medio de pago la sesión abierta." title="Nueva conciliación" aria-label="Nueva conciliación"><i class="bi bi-bank"></i></a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ((auth_user()['role_slug'] ?? '') === 'vendedor' && empty($hasAnyOpenSessionByMe)): ?>
        <div class="alert alert-info">No tienes una caja abierta. Selecciona una caja disponible y pulsa <strong>Abrir caja</strong> para comenzar a operar.</div>
    <?php endif; ?>

    <form id="cash-dashboard-filters" method="get" action="<?= site_url('caja') ?>" class="insight-filters">
        <?php if (! empty($companies)): ?>
            <label>
                Empresa activa
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>
            Filtrar por caja
            <select name="cash_register_id" class="form-select">
                <option value="">Todas las cajas (Consolidado)</option>
                <?php foreach ($registers as $r): ?>
                    <option value="<?= esc($r['id']) ?>" <?= ($selectedRegisterId ?? '') === $r['id'] ? 'selected' : '' ?>><?= esc($r['name']) ?> (<?= esc($r['register_type']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Resumen desde
            <input id="cash-from" class="form-control" type="date" name="from" value="<?= esc($dashboard['from']) ?>" required>
        </label>
        <label>
            Resumen hasta
            <input id="cash-to" class="form-control" type="date" name="to" value="<?= esc($dashboard['to']) ?>" required>
        </label>
        <div class="insight-filter-actions">
            <button class="btn insight-action" title="Aplicar filtros y actualizar" aria-label="Aplicar filtros y actualizar"><i class="bi bi-arrow-repeat"></i></button>
        </div>
    </form>

    <div class="insight-section-label">
        <span>01 / RESUMEN OPERATIVO</span>
        <span><?= date('d/m/Y', strtotime($dashboard['from'])) ?> — <?= date('d/m/Y', strtotime($dashboard['to'])) ?></span>
    </div>

    <div id="cash-dashboard-panel"><?= view('cash/dashboard', ['dashboard' => $dashboard]) ?></div>
    <script src="<?= base_url('assets/js/cash-dashboard.js') ?>" defer></script>

    <!-- Search Toolbar for Upper Panels -->
    <div class="insight-section-label mt-4">
        <span>02 / OPERACIONES Y REGISTROS</span>
        <span><?= count($registers) ?> cajas · <?= count($sessions) ?> sesiones · <?= count($movements) ?> movimientos</span>
    </div>

    <div class="cash-search-panel">
        <div class="cash-search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="cashSearchInput" placeholder="Filtrar cajas, sesiones, cheques o conciliaciones..." aria-label="Buscar en tablas operativas">
            <button type="button" id="clearCashSearchBtn" class="cash-search-clear" style="display: none;" title="Limpiar búsqueda"><i class="bi bi-x-circle-fill"></i></button>
        </div>
        <div class="cash-search-hint">
            <i class="bi bi-lightning-charge-fill text-warning"></i> Filtrado en tiempo real en las tablas operativas
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Cajas configuradas -->
        <div class="col-lg-5">
            <section class="insight-panel h-100">
                <div class="insight-panel-heading">
                    <div>
                        <span class="insight-overline">CONFIGURACIÓN</span>
                        <h2>Cajas configuradas</h2>
                        <p>Puntos de cobro y terminales operativas</p>
                    </div>
                    <?php if (! empty($context['canManage']) && (auth_user()['role_slug'] ?? '') !== 'vendedor'): ?>
                        <a href="<?= site_url('caja/cajas/nueva' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Nueva caja" data-popup-subtitle="Crear una nueva caja operativa." title="Nueva caja" aria-label="Nueva caja"><i class="bi bi-plus-lg"></i></a>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="registers-table">
                        <thead>
                            <tr>
                                <th>Caja</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($registers as $register): ?>
                            <?php $activeSession = $activeSessionsMap[$register['id']] ?? null; ?>
                            <tr class="data-row">
                                <td>
                                    <strong><?= esc($register['name']) ?></strong>
                                    <small class="text-muted"><?= esc($register['code']) ?></small>
                                </td>
                                <td><span class="insight-tag"><?= esc(ucfirst($register['register_type'])) ?></span></td>
                                <td>
                                    <?php if ((int) ($register['active'] ?? 0) === 0): ?>
                                        <span class="insight-badge insight-badge-danger">Inactiva</span>
                                    <?php elseif ($activeSession): ?>
                                        <span class="insight-badge insight-badge-success">Abierta</span>
                                    <?php else: ?>
                                        <span class="insight-badge insight-badge-secondary">Cerrada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (! empty($context['canManage'])): ?>
                                        <div class="d-inline-flex gap-1">
                                            <?php if ((int) ($register['active'] ?? 0) === 1): ?>
                                                <?php if ($activeSession): ?>
                                                    <a href="<?= site_url('caja/sesiones/' . $activeSession['id'] . '/cierre' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Cierre de caja" data-popup-subtitle="Registrar arqueo y cierre de la sesión." title="Cerrar caja" aria-label="Cerrar caja"><i class="bi bi-box-arrow-down"></i></a>
                                                <?php else: ?>
                                                    <?php if (empty($hasAnyOpenSessionByMe)): ?>
                                                        <a href="<?= site_url('caja/sesiones/apertura/nueva?cash_register_id=' . $register['id'] . (! empty($selectedCompanyId) ? '&company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-success icon-btn" title="Abrir caja" aria-label="Abrir caja"><i class="bi bi-box-arrow-up"></i></a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ((auth_user()['role_slug'] ?? '') !== 'vendedor'): ?>
                                                <a href="<?= site_url('caja/cajas/' . $register['id'] . '/editar' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Editar caja" data-popup-subtitle="Modificar datos de la caja." title="Editar caja" aria-label="Editar caja"><i class="bi bi-pencil"></i></a>
                                                <a href="<?= site_url('caja/cajas/' . $register['id'] . '/eliminar' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-danger icon-btn" onclick="return confirm('¿Está seguro de eliminar o desactivar esta caja?')" title="Eliminar caja" aria-label="Eliminar caja"><i class="bi bi-trash"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="4" class="text-secondary text-center py-3">No se encontraron cajas con ese criterio.</td></tr>
                        <?php if ($registers === []): ?><tr class="no-data-row"><td colspan="4"><div class="insight-empty py-4"><i class="bi bi-inbox" aria-hidden="true"></i><strong>No hay cajas registradas</strong><span>Crea una nueva caja para comenzar a operar.</span></div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Sesiones y cierres -->
        <div class="col-lg-7">
            <section class="insight-panel h-100">
                <div class="insight-panel-heading">
                    <div>
                        <span class="insight-overline">HISTORIAL DE TURNOS</span>
                        <h2>Sesiones y cierres recientes</h2>
                        <p>Aperturas, cierres y arqueos auditados</p>
                    </div>
                    <span class="insight-tag"><?= count($sessions) ?> sesiones</span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="sessions-table">
                        <thead>
                            <tr>
                                <th>Caja</th>
                                <th>Apertura / Cierre</th>
                                <th>Estado</th>
                                <th>Esperado / Real</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr class="data-row">
                                <td>
                                    <strong><?= esc($session['register_name']) ?></strong>
                                    <small class="text-muted"><?= esc(ucfirst($session['register_type'])) ?></small>
                                </td>
                                <td class="text-nowrap">
                                    <div><i class="bi bi-clock me-1 text-muted"></i>Ap: <?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?></div>
                                    <?php if ($session['status'] === 'closed'): ?>
                                        <small class="text-muted"><i class="bi bi-check2-circle me-1"></i>Cie: <?= esc(date('d/m/Y H:i', strtotime($session['closed_at']))) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($session['status'] === 'open'): ?>
                                        <span class="insight-badge insight-badge-success">Abierta</span>
                                    <?php else: ?>
                                        <span class="insight-badge insight-badge-secondary">Cerrada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <div>Esp: $<?= number_format((float) ($session['expected_closing_amount'] ?? 0), 2, ',', '.') ?></div>
                                    <?php if ($session['status'] === 'closed'): ?>
                                        <small class="text-muted">Real: $<?= number_format((float) ($session['actual_closing_amount'] ?? 0), 2, ',', '.') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap" style="width: 1%;">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="<?= site_url('caja/sesiones/' . $session['id'] . '/pdf') ?>" target="_blank" class="btn btn-sm btn-outline-dark icon-btn" title="Imprimir reporte de caja (PDF)" aria-label="Imprimir"><i class="bi bi-printer"></i></a>
                                        <?php if ($session['status'] === 'open' && ! empty($context['canManage'])): ?>
                                            <a href="<?= site_url('caja/sesiones/' . $session['id'] . '/cierre' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Cierre de caja" data-popup-subtitle="Registrar arqueo y cierre de la sesión." title="Cerrar caja" aria-label="Cerrar caja"><i class="bi bi-box-arrow-down"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="5" class="text-secondary text-center py-3">No se encontraron sesiones.</td></tr>
                        <?php if ($sessions === []): ?><tr class="no-data-row"><td colspan="5"><div class="insight-empty py-4"><i class="bi bi-clock-history" aria-hidden="true"></i><strong>No hay sesiones registradas</strong><span>Abre una sesión para comenzar a registrar turnos.</span></div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Cheques -->
        <div class="col-lg-6">
            <section class="insight-panel h-100">
                <div class="insight-panel-heading">
                    <div>
                        <span class="insight-overline">VALORES EN CARTERA</span>
                        <h2>Cheques</h2>
                        <p>Cheques recibidos, depositados o endosados</p>
                    </div>
                    <span class="insight-tag"><?= count($checks) ?> registros</span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="checks-table">
                        <thead>
                            <tr>
                                <th>Cheque</th>
                                <th>Tercero</th>
                                <th>Vence</th>
                                <th>Estado</th>
                                <th>Monto</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($checks as $check): ?>
                            <tr class="data-row">
                                <td>
                                    <strong><?= esc($check['check_number']) ?></strong>
                                    <small class="text-muted text-truncate" style="max-width: 90px;" title="<?= esc($check['bank_name']) ?>"><?= esc($check['bank_name']) ?></small>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 100px;" title="<?= esc($check['customer_name'] ?: ($check['supplier_name'] ?: '-')) ?>">
                                        <?= esc($check['customer_name'] ?: ($check['supplier_name'] ?: '-')) ?>
                                    </div>
                                </td>
                                <td class="text-nowrap"><?= esc(! empty($check['due_date']) ? date('d/m/Y', strtotime($check['due_date'])) : '-') ?></td>
                                <td class="text-nowrap">
                                    <?php
                                    $badgeClass = 'insight-badge-secondary';
                                    $statusLabel = esc($check['status']);
                                    if (in_array($check['status'], ['portfolio', 'received'], true)) {
                                        $badgeClass = 'insight-badge-success';
                                        $statusLabel = 'En cartera';
                                    } elseif ($check['status'] === 'deposited') {
                                        $badgeClass = 'insight-badge-primary';
                                        $statusLabel = 'Depositado';
                                    } elseif ($check['status'] === 'endorsed') {
                                        $badgeClass = 'insight-badge-info';
                                        $statusLabel = 'Endosado';
                                    } elseif ($check['status'] === 'rejected') {
                                        $badgeClass = 'insight-badge-danger';
                                        $statusLabel = 'Rechazado';
                                    }
                                    ?>
                                    <span class="insight-badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                                </td>
                                <td class="text-nowrap"><strong>$<?= number_format((float) ($check['amount'] ?? 0), 2, ',', '.') ?></strong></td>
                                <td class="text-end text-nowrap" style="width: 1%;">
                                    <?php if (! empty($context['canManage'])): ?>
                                        <div class="d-inline-flex gap-1 justify-content-end">
                                            <?php if (in_array($check['status'], ['portfolio', 'received'], true)): ?>
                                                <a href="<?= site_url('caja/cheques/' . $check['id'] . '/endosar' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Endosar cheque" data-popup-subtitle="Endosar el cheque a un proveedor." title="Endosar cheque" aria-label="Endosar cheque"><i class="bi bi-person-fill-check"></i></a>
                                                <a href="<?= site_url('caja/cheques/' . $check['id'] . '/depositar' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Depositar cheque" data-popup-subtitle="Depositar cheque en sesión de caja." title="Depositar cheque" aria-label="Depositar cheque"><i class="bi bi-bank"></i></a>
                                            <?php elseif (in_array($check['status'], ['deposited', 'endorsed'], true)): ?>
                                                <a href="<?= site_url('caja/cheques/' . $check['id'] . '/rechazar' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-danger icon-btn" data-popup="true" data-popup-title="Rechazar cheque" data-popup-subtitle="Registrar rechazo del cheque." title="Rechazar cheque" aria-label="Rechazar cheque"><i class="bi bi-exclamation-triangle"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="6" class="text-secondary text-center py-3">No se encontraron cheques.</td></tr>
                        <?php if ($checks === []): ?><tr class="no-data-row"><td colspan="6"><div class="insight-empty py-4"><i class="bi bi-journal-check" aria-hidden="true"></i><strong>No hay cheques registrados</strong><span>Los cheques registrados en cobranzas y pagos aparecerán aquí.</span></div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Conciliaciones -->
        <div class="col-lg-6">
            <section class="insight-panel h-100">
                <div class="insight-panel-heading">
                    <div>
                        <span class="insight-overline">AUDITORÍA</span>
                        <h2>Conciliaciones recientes</h2>
                        <p>Arqueos y diferencias por medio de pago</p>
                    </div>
                    <span class="insight-tag"><?= count($reconciliations) ?> registros</span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="reconciliations-table">
                        <thead>
                            <tr>
                                <th>Sesión</th>
                                <th>Medio</th>
                                <th>Esperado</th>
                                <th>Real</th>
                                <th class="text-end">Dif.</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reconciliations as $row): ?>
                            <tr class="data-row">
                                <td>
                                    <strong><?= esc($row['register_name']) ?></strong>
                                    <small class="text-muted text-nowrap"><?= esc(! empty($row['opened_at']) ? date('d/m/Y H:i', strtotime($row['opened_at'])) : '-') ?></small>
                                </td>
                                <td><span class="insight-tag"><?= esc($row['payment_method']) ?></span></td>
                                <td class="text-nowrap">$<?= number_format((float) ($row['expected_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td class="text-nowrap">$<?= number_format((float) ($row['actual_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td class="text-end text-nowrap <?= (float) ($row['difference_amount'] ?? 0) === 0.0 ? 'text-muted' : ((float) ($row['difference_amount'] ?? 0) > 0 ? 'text-success font-monospace' : 'text-danger font-monospace') ?>">
                                    $<?= number_format((float) ($row['difference_amount'] ?? 0), 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="5" class="text-secondary text-center py-3">No se encontraron conciliaciones.</td></tr>
                        <?php if ($reconciliations === []): ?><tr class="no-data-row"><td colspan="5"><div class="insight-empty py-4"><i class="bi bi-calculator" aria-hidden="true"></i><strong>Sin conciliaciones registradas</strong><span>Las conciliaciones de arqueo realizadas figurarán aquí.</span></div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <!-- Movimientos recientes with search and filters in header -->
    <div class="row g-4">
        <div class="col-12">
            <section class="insight-panel">
                <div class="insight-panel-heading d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <span class="insight-overline">REGISTRO CONTABLE</span>
                        <h2 class="mb-1">Movimientos recientes</h2>
                        <p class="mb-0">Libro diario de ingresos, egresos y transferencias de caja</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                        <div class="cash-header-search">
                            <i class="bi bi-search"></i>
                            <input type="text" id="movementSearchInput" class="form-control form-control-sm" placeholder="Buscar movimiento..." aria-label="Buscar movimientos">
                            <button type="button" id="clearMovementSearchBtn" class="cash-header-clear" style="display: none;" title="Limpiar"><i class="bi bi-x"></i></button>
                        </div>
                        <select id="movementTypeFilter" class="form-select form-select-sm" style="width: auto; min-width: 120px;" aria-label="Filtrar por tipo">
                            <option value="">Todos los tipos</option>
                            <option value="ingreso">Ingresos</option>
                            <option value="egreso">Egresos</option>
                            <option value="transferencia">Transferencias</option>
                        </select>
                        <select id="movementRegisterFilter" class="form-select form-select-sm" style="width: auto; min-width: 130px;" aria-label="Filtrar por caja">
                            <option value="">Todas las cajas</option>
                            <?php foreach ($registers as $r): ?>
                                <option value="<?= esc(strtolower($r['name'])) ?>"><?= esc($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="insight-tag" id="movement-count-badge"><?= count($movements) ?> movimientos</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="movements-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Caja</th>
                                <th>Referencia</th>
                                <th>Canal / Medio</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($movements as $movement): 
                            $mTypeRaw = strtolower($movement['movement_type'] ?? '');
                            $regRaw = strtolower($movement['register_name'] ?? '');
                        ?>
                            <tr class="data-row" data-type="<?= esc($mTypeRaw) ?>" data-register="<?= esc($regRaw) ?>">
                                <td>
                                    <strong><?= esc(date('d/m/Y', strtotime($movement['occurred_at']))) ?></strong>
                                    <small class="text-muted"><?= esc(date('H:i', strtotime($movement['occurred_at']))) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $typeBadge = strpos($mTypeRaw, 'ingreso') !== false ? 'insight-badge-success' : (strpos($mTypeRaw, 'egreso') !== false ? 'insight-badge-danger' : 'insight-badge-primary');
                                    ?>
                                    <span class="insight-badge <?= $typeBadge ?>"><?= esc(ucfirst($movement['movement_type'])) ?></span>
                                </td>
                                <td><?= esc($movement['register_name']) ?></td>
                                <td><code><?= esc($movement['reference_number'] ?: '-') ?></code></td>
                                <td>
                                    <span class="insight-tag">
                                        <?= esc($movement['gateway_name'] ?: ($movement['check_number'] ? 'Cheque ' . $movement['check_number'] : ($movement['payment_method'] ?: '-'))) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <strong class="<?= (float) ($movement['amount'] ?? 0) >= 0 ? 'text-success font-monospace' : 'text-danger font-monospace' ?>">
                                        <?= (float) ($movement['amount'] ?? 0) >= 0 ? '+' : '' ?>$<?= number_format((float) ($movement['amount'] ?? 0), 2, ',', '.') ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="6" class="text-secondary text-center py-3">No se encontraron movimientos con los filtros seleccionados.</td></tr>
                        <?php if ($movements === []): ?><tr class="no-data-row"><td colspan="6"><div class="insight-empty py-4"><i class="bi bi-cash-stack" aria-hidden="true"></i><strong>Sin movimientos recientes</strong><span>No se han registrado operaciones en el período.</span></div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <footer class="insight-footer mt-4">
        <span><i class="bi bi-shield-check" aria-hidden="true"></i> Operaciones y arqueos de caja auditados en tiempo real · CODEX Tesorería</span>
    </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    class PaginatedTable {
        constructor(tableId, pageSize = 5, searchInputId = 'cashSearchInput', filterFn = null) {
            this.table = document.getElementById(tableId);
            if (!this.table) return;
            this.pageSize = pageSize;
            this.currentPage = 1;
            this.searchInputId = searchInputId;
            this.filterFn = filterFn;
            this.tbody = this.table.tBodies[0];
            if (!this.tbody) return;
            this.allRows = Array.from(this.tbody.querySelectorAll('tr.data-row'));
            this.noResultsRow = this.tbody.querySelector('tr.no-results-row');
            this.noDataRow = this.tbody.querySelector('tr.no-data-row');

            // Create pagination wrapper
            const tableResponsive = this.table.closest('.table-responsive');
            this.paginationWrapper = document.createElement('div');
            this.paginationWrapper.className = 'codex-pagination';
            tableResponsive.after(this.paginationWrapper);

            // Listen for input search if specified
            if (searchInputId) {
                const searchInput = document.getElementById(searchInputId);
                if (searchInput) {
                    searchInput.addEventListener('input', () => {
                        this.currentPage = 1;
                        this.update();
                    });
                }
            }

            this.update();
        }

        refreshRows() {
            if (!this.table) return;
            this.tbody = this.table.querySelector('tbody');
            if (!this.tbody) return;
            this.allRows = Array.from(this.tbody.querySelectorAll('tr.data-row'));
            this.noResultsRow = this.tbody.querySelector('tr.no-results-row');
            this.noDataRow = this.tbody.querySelector('tr.no-data-row');
            this.currentPage = 1;
            this.update();
        }

        update() {
            const query = this.searchInputId ? (document.getElementById(this.searchInputId)?.value.toLowerCase().trim() || '') : '';
            
            if (this.allRows.length === 0) {
                if (this.noDataRow) this.noDataRow.style.display = '';
                if (this.noResultsRow) this.noResultsRow.style.display = 'none';
                this.paginationWrapper.innerHTML = '';
                return;
            }

            let matchedRows = [];

            this.allRows.forEach(row => {
                let matches = true;
                if (query) {
                    const text = row.textContent.toLowerCase();
                    if (!text.includes(query)) matches = false;
                }

                if (matches && typeof this.filterFn === 'function') {
                    matches = Boolean(this.filterFn(row));
                }

                if (matches) {
                    row.style.display = '';
                    matchedRows.push(row);
                } else {
                    row.style.display = 'none';
                }
            });

            const totalCount = matchedRows.length;
            if (totalCount === 0) {
                if (this.noResultsRow) this.noResultsRow.style.display = '';
                if (this.noDataRow) this.noDataRow.style.display = 'none';
                this.paginationWrapper.innerHTML = '';
            } else {
                if (this.noResultsRow) this.noResultsRow.style.display = 'none';
                if (this.noDataRow) this.noDataRow.style.display = 'none';

                const pageCount = Math.ceil(totalCount / this.pageSize);
                if (this.currentPage > pageCount) {
                    this.currentPage = Math.max(1, pageCount);
                }

                const startIndex = (this.currentPage - 1) * this.pageSize;
                const endIndex = startIndex + this.pageSize;

                matchedRows.forEach((row, index) => {
                    if (index >= startIndex && index < endIndex) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                this.renderPagination(totalCount, pageCount);
            }

            if (this.table.id === 'movements-table') {
                const badge = document.getElementById('movement-count-badge');
                if (badge) {
                    badge.textContent = `${totalCount} movimiento${totalCount === 1 ? '' : 's'}`;
                }
            }
        }

        renderPagination(totalCount, pageCount) {
            this.paginationWrapper.innerHTML = '';
            if (pageCount <= 1) {
                const summary = document.createElement('div');
                summary.className = 'codex-pagination__summary';
                summary.textContent = `Mostrando ${totalCount} registro${totalCount === 1 ? '' : 's'}`;
                this.paginationWrapper.appendChild(summary);
                return;
            }

            const summary = document.createElement('div');
            summary.className = 'codex-pagination__summary';
            const startIndex = (this.currentPage - 1) * this.pageSize;
            const endIndex = Math.min(startIndex + this.pageSize, totalCount);
            summary.textContent = `Mostrando ${startIndex + 1}-${endIndex} de ${totalCount} registros`;

            const controls = document.createElement('div');
            controls.className = 'codex-pagination__controls';

            // First page button (<<)
            if (pageCount > 4) {
                const firstBtn = document.createElement('button');
                firstBtn.type = 'button';
                firstBtn.className = 'codex-pagination__btn';
                firstBtn.innerHTML = '<i class="bi bi-chevron-double-left"></i>';
                firstBtn.disabled = this.currentPage === 1;
                firstBtn.title = 'Primera página';
                firstBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (this.currentPage > 1) {
                        this.currentPage = 1;
                        this.update();
                    }
                });
                controls.appendChild(firstBtn);
            }

            // Previous button (<)
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'codex-pagination__btn';
            prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prev.disabled = this.currentPage === 1;
            prev.title = 'Página anterior';
            prev.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.update();
                }
            });
            controls.appendChild(prev);

            const pages = document.createElement('div');
            pages.className = 'codex-pagination__pages';

            const startPage = Math.max(1, this.currentPage - 2);
            const endPage = Math.min(pageCount, this.currentPage + 2);

            for (let p = startPage; p <= endPage; p++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `codex-pagination__btn${p === this.currentPage ? ' is-active' : ''}`;
                btn.textContent = String(p);
                btn.title = `Página ${p}`;
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.currentPage = p;
                    this.update();
                });
                pages.appendChild(btn);
            }
            controls.appendChild(pages);

            // Next button (>)
            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'codex-pagination__btn';
            next.innerHTML = '<i class="bi bi-chevron-right"></i>';
            next.disabled = this.currentPage === pageCount;
            next.title = 'Página siguiente';
            next.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentPage < pageCount) {
                    this.currentPage++;
                    this.update();
                }
            });
            controls.appendChild(next);

            // Last page button (>>)
            if (pageCount > 4) {
                const lastBtn = document.createElement('button');
                lastBtn.type = 'button';
                lastBtn.className = 'codex-pagination__btn';
                lastBtn.innerHTML = '<i class="bi bi-chevron-double-right"></i>';
                lastBtn.disabled = this.currentPage === pageCount;
                lastBtn.title = 'Última página';
                lastBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (this.currentPage < pageCount) {
                        this.currentPage = pageCount;
                        this.update();
                    }
                });
                controls.appendChild(lastBtn);
            }

            this.paginationWrapper.appendChild(summary);
            this.paginationWrapper.appendChild(controls);
        }
    }

    // Movements Table Filter Function & Initialization
    const movementFilterFn = (row) => {
        const typeFilter = document.getElementById('movementTypeFilter')?.value.toLowerCase() || '';
        const registerFilter = document.getElementById('movementRegisterFilter')?.value.toLowerCase() || '';
        
        if (typeFilter && !row.getAttribute('data-type')?.includes(typeFilter)) {
            return false;
        }
        if (registerFilter && !row.getAttribute('data-register')?.includes(registerFilter)) {
            return false;
        }
        return true;
    };

    // Table instances registry
    const allPaginatedTables = [
        new PaginatedTable('registers-table', 5, 'cashSearchInput'),
        new PaginatedTable('sessions-table', 5, 'cashSearchInput'),
        new PaginatedTable('checks-table', 5, 'cashSearchInput'),
        new PaginatedTable('reconciliations-table', 5, 'cashSearchInput'),
        new PaginatedTable('movements-table', 5, 'movementSearchInput', movementFilterFn)
    ];
    window.codexCashTables = allPaginatedTables;

    document.getElementById('movementTypeFilter')?.addEventListener('change', () => {
        const movTable = allPaginatedTables.find(t => t.table?.id === 'movements-table');
        if (movTable) {
            movTable.currentPage = 1;
            movTable.update();
        }
    });

    document.getElementById('movementRegisterFilter')?.addEventListener('change', () => {
        const movTable = allPaginatedTables.find(t => t.table?.id === 'movements-table');
        if (movTable) {
            movTable.currentPage = 1;
            movTable.update();
        }
    });

    // Clear search handler for Upper Tables
    const clearBtn = document.getElementById('clearCashSearchBtn');
    const searchInput = document.getElementById('cashSearchInput');
    if (clearBtn && searchInput) {
        searchInput.addEventListener('input', () => {
            clearBtn.style.display = searchInput.value ? 'block' : 'none';
        });
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            searchInput.value = '';
            clearBtn.style.display = 'none';
            searchInput.dispatchEvent(new Event('input'));
        });
    }

    // Clear search handler for Movements Table
    const clearMovBtn = document.getElementById('clearMovementSearchBtn');
    const movSearchInput = document.getElementById('movementSearchInput');
    if (clearMovBtn && movSearchInput) {
        movSearchInput.addEventListener('input', () => {
            clearMovBtn.style.display = movSearchInput.value ? 'block' : 'none';
        });
        clearMovBtn.addEventListener('click', (e) => {
            e.preventDefault();
            movSearchInput.value = '';
            clearMovBtn.style.display = 'none';
            movSearchInput.dispatchEvent(new Event('input'));
        });
    }

    // Seamless AJAX refresh without page reload
    let isCashReloading = false;
    async function reloadCashData() {
        if (isCashReloading) return;
        isCashReloading = true;
        const refreshBtn = document.getElementById('cash-dashboard-refresh');
        if (refreshBtn) {
            refreshBtn.classList.add('is-loading');
            refreshBtn.disabled = true;
        }

        try {
            const response = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('Network error');
            
            const text = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');

            // 1. Update Cash Dashboard Panel (KPIs + Breakdown)
            const newPanel = doc.getElementById('cash-dashboard-panel');
            const currentPanel = document.getElementById('cash-dashboard-panel');
            if (newPanel && currentPanel) {
                currentPanel.innerHTML = newPanel.innerHTML;
            }

            // 2. Update Table Bodies
            const tableIds = ['registers-table', 'sessions-table', 'checks-table', 'reconciliations-table', 'movements-table'];
            tableIds.forEach(id => {
                const newTable = doc.getElementById(id);
                const currentTable = document.getElementById(id);
                if (newTable && currentTable) {
                    const newTbody = newTable.querySelector('tbody');
                    const currentTbody = currentTable.querySelector('tbody');
                    if (newTbody && currentTbody) {
                        currentTbody.innerHTML = newTbody.innerHTML;
                    }
                }
            });

            // 3. Update Counts and Badges
            const newMovCount = doc.getElementById('movement-count-badge');
            const currentMovCount = document.getElementById('movement-count-badge');
            if (newMovCount && currentMovCount) {
                currentMovCount.textContent = newMovCount.textContent;
            }

            // Update panel tags (e.g. "X sesiones", "X registros")
            const docPanels = doc.querySelectorAll('.insight-panel');
            const currentPanels = document.querySelectorAll('.insight-panel');
            docPanels.forEach((dPanel, idx) => {
                const cPanel = currentPanels[idx];
                if (cPanel) {
                    const dTag = dPanel.querySelector('.insight-tag');
                    const cTag = cPanel.querySelector('.insight-tag');
                    if (dTag && cTag) {
                        cTag.textContent = dTag.textContent;
                    }
                }
            });

            // Update Section 02 summary label if present
            const docSectionLabels = doc.querySelectorAll('.insight-section-label');
            const currentSectionLabels = document.querySelectorAll('.insight-section-label');
            if (docSectionLabels.length > 1 && currentSectionLabels.length > 1) {
                currentSectionLabels[1].innerHTML = docSectionLabels[1].innerHTML;
            }

            // Update Hero Action Buttons if active session state changed
            const docHeroActions = doc.querySelector('.insight-hero .d-flex');
            const currentHeroActions = document.querySelector('.insight-hero .d-flex');
            if (docHeroActions && currentHeroActions) {
                currentHeroActions.innerHTML = docHeroActions.innerHTML;
            }

            // 4. Refresh all Paginated Tables to display new data on page 1
            allPaginatedTables.forEach(t => t.refreshRows());

            // 5. Re-bind popups in updated elements if needed
            if (typeof window.initCodexPopups === 'function') {
                window.initCodexPopups();
            }
        } catch (err) {
            console.error('Error al actualizar datos de caja:', err);
        } finally {
            isCashReloading = false;
            if (refreshBtn) {
                refreshBtn.classList.remove('is-loading');
                refreshBtn.disabled = false;
            }
        }
    }
    window.reloadCashData = reloadCashData;

    // Listeners for seamless updates when modals/popups close
    window.addEventListener('codex:item-saved', () => {
        reloadCashData();
    });
    window.addEventListener('codex:cash-saved', () => {
        reloadCashData();
    });
    window.addEventListener('message', (event) => {
        if (event.origin === window.location.origin && event.data && (event.data.type === 'codex-popup-saved' || event.data.type === 'codex-popup-close')) {
            reloadCashData();
        }
    });

    // Manual refresh button
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('#cash-dashboard-refresh');
        if (btn) {
            e.preventDefault();
            reloadCashData();
        }
    });
});
</script>
<?= $this->endSection() ?>
