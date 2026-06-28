<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Caja y Tesoreria</h1>
        <p class="text-secondary mb-0">Apertura, cierre y seguimiento diario de ingresos y egresos operativos.</p>
    </div>
    <?php if (! empty($context['canManage'])): ?>
        <div class="d-flex gap-2">
            <a href="<?= site_url('caja/sesiones/apertura/nueva' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Apertura de caja" data-popup-subtitle="Registrar una nueva sesion activa." title="Abrir caja" aria-label="Abrir caja"><i class="bi bi-box-arrow-in-up"></i></a>
            <a href="<?= site_url('caja/movimientos/nuevo' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Movimiento de caja" data-popup-subtitle="Registrar un movimiento manual." title="Nuevo movimiento" aria-label="Nuevo movimiento"><i class="bi bi-plus-lg"></i></a>
            <a href="<?= site_url('caja/cheques/nuevo' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Cheque" data-popup-subtitle="Registrar cheque recibido o emitido." title="Nuevo cheque" aria-label="Nuevo cheque"><i class="bi bi-journal-check"></i></a>
            <a href="<?= site_url('caja/conciliaciones/nueva' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Conciliacion de caja" data-popup-subtitle="Conciliar por medio de pago la sesion abierta." title="Nueva conciliacion" aria-label="Nueva conciliacion"><i class="bi bi-bank"></i></a>
        </div>
    <?php endif; ?>
</div>

<?php if (! empty($companies)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('caja') ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Empresa activa</label>
                    <select name="company_id" class="form-select">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Cajas activas</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['registers'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Sesiones abiertas</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['sessions_open'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Ingresos del dia</div><div class="display-6 fw-semibold"><?= number_format((float) ($summary['today_income'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Saldo diario</div><div class="display-6 fw-semibold"><?= number_format((float) ($summary['today_balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Cheques en cartera</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['checks_portfolio'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Conciliaciones pendientes</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['reconciliations_pending'] ?? 0)) ?></div></div></div></div>
</div>

<!-- Interactive Search Toolbar -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="input-group" style="max-width: 400px;">
            <span class="input-group-text bg-white border-end-0 text-secondary"><i class="bi bi-search"></i></span>
            <input type="text" id="cashSearchInput" class="form-control border-start-0 ps-0 shadow-none" placeholder="Filtrar por caja, medio, banco, referencia..." aria-label="Buscar en caja y tesoreria">
            <button class="btn btn-white border border-start-0 text-secondary" type="button" id="clearCashSearchBtn" style="display: none;" title="Limpiar busqueda"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="small text-secondary">
            <i class="bi bi-info-circle me-1"></i> Escribe para buscar en tiempo real en todos los listados de la página.
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Cajas configuradas</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="registers-table">
                        <thead><tr><th>Caja</th><th>Tipo</th><th>Estado</th></tr></thead>
                        <tbody>
                        <?php foreach ($registers as $register): ?>
                            <tr class="data-row">
                                <td><?= esc($register['name']) ?><div class="small text-secondary"><?= esc($register['code']) ?></div></td>
                                <td><?= esc(ucfirst($register['register_type'])) ?></td>
                                <td><?= (int) ($register['active'] ?? 0) === 1 ? 'Activa' : 'Inactiva' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="3" class="text-secondary text-center py-3">No se encontraron cajas.</td></tr>
                        <?php if ($registers === []): ?><tr class="no-data-row"><td colspan="3" class="text-secondary">No hay cajas registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Sesiones abiertas</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="sessions-table">
                        <thead><tr><th>Caja</th><th>Apertura</th><th>Esperado</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr class="data-row">
                                <td><?= esc($session['register_name']) ?><div class="small text-secondary"><?= esc($session['register_type']) ?></div></td>
                                <td><?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?><div class="small text-secondary">Inicial: <?= number_format((float) ($session['opening_amount'] ?? 0), 2, ',', '.') ?></div></td>
                                <td><?= number_format((float) ($session['expected_closing_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <?php if (! empty($context['canManage'])): ?>
                                        <a href="<?= site_url('caja/sesiones/' . $session['id'] . '/cierre' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Cierre de caja" data-popup-subtitle="Registrar arqueo y cierre de la sesion." title="Cerrar caja" aria-label="Cerrar caja"><i class="bi bi-box-arrow-down"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="4" class="text-secondary text-center py-3">No se encontraron sesiones abiertas.</td></tr>
                        <?php if ($sessions === []): ?><tr class="no-data-row"><td colspan="4" class="text-secondary">No hay sesiones abiertas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Resumen por medio</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="payment-methods-table">
                        <thead><tr><th>Medio</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($paymentMethods as $row): ?>
                            <tr class="data-row">
                                <td><?= esc($row['payment_method'] ?: 'Sin medio') ?></td>
                                <td class="<?= (float) ($row['total'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format((float) ($row['total'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="2" class="text-secondary text-center py-3">No se encontraron medios de pago.</td></tr>
                        <?php if ($paymentMethods === []): ?><tr class="no-data-row"><td colspan="2" class="text-secondary">Todavia no hay movimientos conciliables por medio.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Cheques</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="checks-table">
                        <thead><tr><th>Cheque</th><th>Tercero</th><th>Vence</th><th>Estado</th><th>Monto</th><th class="text-end"></th></tr></thead>
                        <tbody>
                        <?php foreach ($checks as $check): ?>
                            <tr class="data-row">
                                <td><?= esc($check['check_number']) ?><div class="small text-secondary"><?= esc($check['bank_name']) ?></div></td>
                                <td><?= esc($check['customer_name'] ?: ($check['supplier_name'] ?: '-')) ?></td>
                                <td><?= esc(! empty($check['due_date']) ? date('d/m/Y', strtotime($check['due_date'])) : '-') ?></td>
                                <td>
                                    <?php
                                    $statusBadge = 'bg-secondary';
                                    if ($check['status'] === 'portfolio' || $check['status'] === 'received') $statusBadge = 'bg-success';
                                    elseif ($check['status'] === 'deposited') $statusBadge = 'bg-primary';
                                    elseif ($check['status'] === 'endorsed') $statusBadge = 'bg-info';
                                    elseif ($check['status'] === 'rejected') $statusBadge = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= esc($check['status']) ?></span>
                                </td>
                                <td><?= number_format((float) ($check['amount'] ?? 0), 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <?php if (! empty($context['canManage'])): ?>
                                        <?php if (in_array($check['status'], ['portfolio', 'received'], true)): ?>
                                            <a href="<?= site_url('caja/cheques/' . $check['id'] . '/endosar' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Endosar cheque" data-popup-subtitle="Endosar el cheque a un proveedor." title="Endosar cheque" aria-label="Endosar cheque"><i class="bi bi-person-fill-check"></i></a>
                                            <a href="<?= site_url('caja/cheques/' . $check['id'] . '/depositar' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Depositar cheque" data-popup-subtitle="Depositar cheque en sesión de caja." title="Depositar cheque" aria-label="Depositar cheque"><i class="bi bi-bank"></i></a>
                                        <?php elseif (in_array($check['status'], ['deposited', 'endorsed'], true)): ?>
                                            <a href="<?= site_url('caja/cheques/' . $check['id'] . '/rechazar' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-danger icon-btn" data-popup="true" data-popup-title="Rechazar cheque" data-popup-subtitle="Registrar rechazo del cheque." title="Rechazar cheque" aria-label="Rechazar cheque"><i class="bi bi-exclamation-triangle"></i></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="6" class="text-secondary text-center py-3">No se encontraron cheques.</td></tr>
                        <?php if ($checks === []): ?><tr class="no-data-row"><td colspan="6" class="text-secondary">No hay cheques registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Conciliaciones recientes</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="reconciliations-table">
                        <thead><tr><th>Sesion</th><th>Medio</th><th>Esperado</th><th>Real</th><th>Dif.</th></tr></thead>
                        <tbody>
                        <?php foreach ($reconciliations as $row): ?>
                            <tr class="data-row">
                                <td><?= esc($row['register_name']) ?><div class="small text-secondary"><?= esc(! empty($row['opened_at']) ? date('d/m/Y H:i', strtotime($row['opened_at'])) : '-') ?></div></td>
                                <td><?= esc($row['payment_method']) ?></td>
                                <td><?= number_format((float) ($row['expected_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td><?= number_format((float) ($row['actual_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td class="<?= (float) ($row['difference_amount'] ?? 0) === 0.0 ? '' : ((float) ($row['difference_amount'] ?? 0) > 0 ? 'text-success' : 'text-danger') ?>"><?= number_format((float) ($row['difference_amount'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="5" class="text-secondary text-center py-3">No se encontraron conciliaciones.</td></tr>
                        <?php if ($reconciliations === []): ?><tr class="no-data-row"><td colspan="5" class="text-secondary">Todavia no hay conciliaciones registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Movimientos recientes</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="movements-table">
                        <thead><tr><th>Fecha</th><th>Tipo</th><th>Caja</th><th>Referencia</th><th>Canal</th><th>Monto</th></tr></thead>
                        <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr class="data-row">
                                <td><?= esc(date('d/m/Y H:i', strtotime($movement['occurred_at']))) ?></td>
                                <td><?= esc($movement['movement_type']) ?></td>
                                <td><?= esc($movement['register_name']) ?></td>
                                <td><?= esc($movement['reference_number'] ?: '-') ?></td>
                                <td><?= esc($movement['gateway_name'] ?: ($movement['check_number'] ? 'Cheque ' . $movement['check_number'] : ($movement['payment_method'] ?: '-'))) ?></td>
                                <td class="<?= (float) ($movement['amount'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format((float) ($movement['amount'] ?? 0), 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="6" class="text-secondary text-center py-3">No se encontraron movimientos.</td></tr>
                        <?php if ($movements === []): ?><tr class="no-data-row"><td colspan="6" class="text-secondary">No hay movimientos registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    class PaginatedTable {
        constructor(tableId, pageSize, searchInputId) {
            this.table = document.getElementById(tableId);
            if (!this.table) return;
            this.pageSize = pageSize;
            this.currentPage = 1;
            this.tbody = this.table.tBodies[0];
            if (!this.tbody) return;
            this.allRows = Array.from(this.tbody.querySelectorAll('tr.data-row'));
            this.noResultsRow = this.tbody.querySelector('tr.no-results-row');
            this.noDataRow = this.tbody.querySelector('tr.no-data-row');

            // Create pagination wrapper
            const tableResponsive = this.table.closest('.table-responsive');
            this.paginationWrapper = document.createElement('div');
            this.paginationWrapper.className = 'codex-pagination mt-3';
            tableResponsive.after(this.paginationWrapper);

            // Listen for input search
            const searchInput = document.getElementById(searchInputId);
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    this.currentPage = 1;
                    this.update();
                });
            }

            this.update();
        }

        update() {
            const query = document.getElementById('cashSearchInput')?.value.toLowerCase().trim() || '';
            
            if (this.allRows.length === 0) {
                if (this.noDataRow) this.noDataRow.style.display = '';
                if (this.noResultsRow) this.noResultsRow.style.display = 'none';
                this.paginationWrapper.innerHTML = '';
                return;
            }

            let matchedRows = [];

            this.allRows.forEach(row => {
                let matches = false;
                if (!query) {
                    matches = true;
                } else {
                    const text = row.textContent.toLowerCase();
                    matches = text.includes(query);
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
        }

        renderPagination(totalCount, pageCount) {
            this.paginationWrapper.innerHTML = '';
            if (pageCount <= 1) {
                const summary = document.createElement('div');
                summary.className = 'codex-pagination__summary';
                summary.textContent = `Mostrando 1-${totalCount} de ${totalCount} registros`;
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

            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'codex-pagination__btn';
            prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prev.disabled = this.currentPage === 1;
            prev.addEventListener('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.update();
                }
            });

            const pages = document.createElement('div');
            pages.className = 'codex-pagination__pages';

            const startPage = Math.max(1, this.currentPage - 2);
            const endPage = Math.min(pageCount, this.currentPage + 2);

            for (let p = startPage; p <= endPage; p++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `codex-pagination__btn${p === this.currentPage ? ' is-active' : ''}`;
                btn.textContent = String(p);
                btn.addEventListener('click', () => {
                    this.currentPage = p;
                    this.update();
                });
                pages.appendChild(btn);
            }

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'codex-pagination__btn';
            next.innerHTML = '<i class="bi bi-chevron-right"></i>';
            next.disabled = this.currentPage === pageCount;
            next.addEventListener('click', () => {
                if (this.currentPage < pageCount) {
                    this.currentPage++;
                    this.update();
                }
            });

            controls.appendChild(prev);
            controls.appendChild(pages);
            controls.appendChild(next);

            this.paginationWrapper.appendChild(summary);
            this.paginationWrapper.appendChild(controls);
        }
    }

    // Initialize Paginated Tables
    const tables = [
        new PaginatedTable('registers-table', 5, 'cashSearchInput'),
        new PaginatedTable('sessions-table', 5, 'cashSearchInput'),
        new PaginatedTable('payment-methods-table', 5, 'cashSearchInput'),
        new PaginatedTable('checks-table', 5, 'cashSearchInput'),
        new PaginatedTable('reconciliations-table', 5, 'cashSearchInput'),
        new PaginatedTable('movements-table', 5, 'cashSearchInput')
    ];

    // Clear search handler
    const clearBtn = document.getElementById('clearCashSearchBtn');
    const searchInput = document.getElementById('cashSearchInput');
    if (clearBtn && searchInput) {
        searchInput.addEventListener('input', () => {
            clearBtn.style.display = searchInput.value ? 'block' : 'none';
        });
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            searchInput.dispatchEvent(new Event('input'));
        });
    }
});
</script>
<?= $this->endSection() ?>
