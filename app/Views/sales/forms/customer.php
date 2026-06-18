<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$customer = $customer ?? null;
$customers = $customers ?? [];
?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="customerTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="form-tab" data-bs-toggle="tab" data-bs-target="#form-tab-pane" type="button" role="tab" aria-controls="form-tab-pane" aria-selected="true">
                    <?php if (!empty($customer)): ?>
                        <i class="bi bi-pencil-square me-1"></i> Editar cliente
                    <?php else: ?>
                        <i class="bi bi-person-plus me-1"></i> Cliente nuevo
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-tab-pane" type="button" role="tab" aria-controls="list-tab-pane" aria-selected="false">
                    <i class="bi bi-people me-1"></i> Listado de clientes
                </button>
            </li>
        </ul>

        <div class="tab-content" id="customerTabContent">
            <!-- Form Tab -->
            <div class="tab-pane fade show active" id="form-tab-pane" role="tabpanel" aria-labelledby="form-tab" tabindex="0">
                <div class="mb-3">
                    <h2 class="h5 mb-1"><?= !empty($customer) ? 'Editar cliente: ' . esc($customer['name']) : 'Cliente nuevo' ?></h2>
                    <p class="text-secondary mb-0">Alta y modificación rápida de clientes, límites y condiciones comerciales.</p>
                </div>
                <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
                    <?php if (!empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
                    <?php if (!empty($customer)): ?><input type="hidden" name="id" value="<?= esc($customer['id']) ?>"><?php endif; ?>

                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <input type="text" name="name" class="form-control" value="<?= esc(old('name', $customer['name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Razon social / Facturacion</label>
                        <input type="text" name="billing_name" class="form-control" value="<?= esc(old('billing_name', $customer['billing_name'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo doc.</label>
                        <select name="document_type" class="form-select">
                            <?php foreach (['DNI', 'CUIT', 'CUIL', 'PAS' => 'Pasaporte'] as $val => $lbl):
                                $val = is_numeric($val) ? $lbl : $val;
                                $selected = old('document_type', $customer['document_type'] ?? 'DNI') === $val ? 'selected' : '';
                                ?>
                                <option value="<?= esc($val) ?>" <?= $selected ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Documento</label>
                        <input type="text" name="document_number" class="form-control" value="<?= esc(old('document_number', $customer['document_number'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Perfil fiscal</label>
                        <select name="tax_profile" class="form-select">
                            <?php foreach (['consumidor_final' => 'Consumidor final', 'responsable_inscripto' => 'Responsable inscripto', 'monotributo' => 'Monotributo', 'exento' => 'Exento'] as $val => $lbl):
                                $selected = old('tax_profile', $customer['tax_profile'] ?? 'consumidor_final') === $val ? 'selected' : '';
                                ?>
                                <option value="<?= esc($val) ?>" <?= $selected ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Condicion IVA</label>
                        <input type="text" name="vat_condition" class="form-control" value="<?= esc(old('vat_condition', $customer['vat_condition'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= esc(old('email', $customer['email'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefono</label>
                        <input type="text" name="phone" class="form-control" value="<?= esc(old('phone', $customer['phone'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sucursal</label>
                        <select name="branch_id" class="form-select">
                            <option value="">Sin sucursal</option>
                            <?php foreach ($branches as $branch):
                                $selected = old('branch_id', $customer['branch_id'] ?? '') === $branch['id'] ? 'selected' : '';
                                ?>
                                <option value="<?= esc($branch['id']) ?>" <?= $selected ?>><?= esc($branch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Lista</label>
                        <select name="price_list_id" class="form-select">
                            <option value="">Lista General</option>
                            <?php foreach (($priceLists ?? []) as $priceList):
                                $selected = old('price_list_id', $customer['price_list_id'] ?? '') === $priceList['id'] ? 'selected' : '';
                                ?>
                                <option value="<?= esc($priceList['id']) ?>" <?= $selected ?>><?= esc($priceList['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="price_list_name" value="<?= esc(old('price_list_name', 'Lista General')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Credito</label>
                        <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="<?= esc(old('credit_limit', $customer['credit_limit'] ?? '0')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vendedor</label>
                        <select name="sales_agent_id" class="form-select">
                            <option value="">Sin vendedor</option>
                            <?php foreach (($agents ?? []) as $agent):
                                $selected = old('sales_agent_id', $customer['sales_agent_id'] ?? '') === $agent['id'] ? 'selected' : '';
                                ?>
                                <option value="<?= esc($agent['id']) ?>" <?= $selected ?>><?= esc($agent['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Zona</label>
                        <select name="sales_zone_id" class="form-select">
                            <option value="">Sin zona</option>
                            <?php foreach (($zones ?? []) as $zone):
                                $selected = old('sales_zone_id', $customer['sales_zone_id'] ?? '') === $zone['id'] ? 'selected' : '';
                                ?>
                                <option value="<?= esc($zone['id']) ?>" <?= $selected ?>><?= esc($zone['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Condicion</label>
                        <select name="sales_condition_id" class="form-select">
                            <option value="">Sin condicion</option>
                            <?php foreach (($conditions ?? []) as $condition):
                                $selected = old('sales_condition_id', $customer['sales_condition_id'] ?? '') === $condition['id'] ? 'selected' : '';
                                ?>
                                <option value="<?= esc($condition['id']) ?>" <?= $selected ?>><?= esc($condition['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Desc. %</label>
                        <input type="number" step="0.01" min="0" name="custom_discount_rate" class="form-control" value="<?= esc(old('custom_discount_rate', $customer['custom_discount_rate'] ?? '0')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Plazo dias</label>
                        <input type="number" min="0" name="payment_terms_days" class="form-control" value="<?= esc(old('payment_terms_days', $customer['payment_terms_days'] ?? '0')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="active" class="form-select">
                            <option value="1" <?= old('active', $customer['active'] ?? '1') === '1' ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= old('active', $customer['active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Direccion</label>
                        <textarea name="address" class="form-control" rows="3"><?= esc(old('address', $customer['address'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2 pt-2">
                        <button class="btn btn-dark icon-btn" title="Guardar cliente" aria-label="Guardar cliente"><i class="bi bi-check-lg"></i></button>
                        <?php if (!empty($customer)): ?>
                            <a href="<?= site_url('ventas/clientes/nuevo') ?><?= $isPopup ? '?popup=1' : '' ?>" class="btn btn-outline-danger icon-btn" title="Cancelar edición" aria-label="Cancelar edición"><i class="bi bi-x-circle"></i></a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                    </div>
                </form>
            </div>

            <!-- List Tab -->
            <div class="tab-pane fade" id="list-tab-pane" role="tabpanel" aria-labelledby="list-tab" tabindex="0">
                <div class="mb-3 d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1">Listado de clientes</h2>
                        <p class="text-secondary mb-0">Base de clientes de la empresa.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="max-width: 350px; width: 100%;">
                        <button type="button" class="btn btn-outline-success btn-sm icon-btn" title="Exportar a Excel" data-bs-toggle="modal" data-bs-target="#exportExcelModal" aria-label="Exportar a Excel">
                            <i class="bi bi-file-earmark-excel"></i>
                        </button>
                        <div class="flex-grow-1">
                            <input type="text" id="list-customer-search" class="form-control form-control-sm" placeholder="Buscar cliente por nombre o documento...">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover table-sm mb-0" id="list-customers-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Documento</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr data-name="<?= esc(strtolower($c['name'])) ?>" data-doc="<?= esc(strtolower($c['document_number'] ?? '')) ?>">
                                    <td>
                                        <strong><?= esc($c['name']) ?></strong>
                                        <?php if ($c['billing_name'] && $c['billing_name'] !== $c['name']): ?>
                                            <div class="small text-secondary"><?= esc($c['billing_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= esc($c['document_type']) ?></span>
                                        <span class="font-monospace text-secondary"><?= esc($c['document_number'] ?: '-') ?></span>
                                    </td>
                                    <td>
                                        <div class="small"><?= esc($c['email'] ?: '-') ?></div>
                                        <div class="small text-secondary"><?= esc($c['phone'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?= (int)$c['active'] === 1 ? 'success' : 'secondary' ?> rounded-pill">
                                            <?= (int)$c['active'] === 1 ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= site_url('ventas/clientes/nuevo?id=' . $c['id']) ?><?= $isPopup ? '&popup=1' : '' ?>" class="btn btn-sm btn-outline-dark icon-btn" title="Editar cliente" aria-label="Editar cliente">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="5" class="text-secondary text-center py-3">No hay clientes registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="customer-pagination-container"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Exportación a Excel -->
<div class="modal fade" id="exportExcelModal" tabindex="-1" aria-labelledby="exportExcelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="exportExcelModalLabel">Exportar clientes a Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= site_url('ventas/clientes/exportar') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <p class="text-secondary mb-3">Selecciona los datos que deseas incluir en la exportación a Excel (.xls):</p>
                    
                    <div class="mb-3 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-select-all">Seleccionar todos</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-deselect-all">Deseleccionar todos</button>
                    </div>

                    <div class="row g-2">
                        <?php
                        $exportFields = [
                            'name'                 => ['label' => 'Nombre del cliente', 'checked' => true],
                            'billing_name'         => ['label' => 'Razón social / Facturación', 'checked' => true],
                            'document_type'        => ['label' => 'Tipo de documento', 'checked' => true],
                            'document_number'      => ['label' => 'Número de documento', 'checked' => true],
                            'tax_profile'          => ['label' => 'Perfil fiscal', 'checked' => false],
                            'vat_condition'        => ['label' => 'Condición IVA', 'checked' => false],
                            'email'                => ['label' => 'Email', 'checked' => true],
                            'phone'                => ['label' => 'Teléfono', 'checked' => true],
                            'address'              => ['label' => 'Dirección', 'checked' => false],
                            'price_list_name'      => ['label' => 'Lista de precios', 'checked' => false],
                            'credit_limit'         => ['label' => 'Límite de crédito', 'checked' => false],
                            'custom_discount_rate' => ['label' => 'Tasa de descuento %', 'checked' => false],
                            'payment_terms_days'   => ['label' => 'Plazo de pago (Días)', 'checked' => false],
                            'branch_id'            => ['label' => 'Sucursal', 'checked' => false],
                            'sales_agent_id'       => ['label' => 'Vendedor', 'checked' => false],
                            'sales_zone_id'        => ['label' => 'Zona', 'checked' => false],
                            'sales_condition_id'   => ['label' => 'Condición de venta', 'checked' => false],
                            'active'               => ['label' => 'Estado (Activo/Inactivo)', 'checked' => true],
                        ];
                        foreach ($exportFields as $key => $info):
                        ?>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input export-field-checkbox" type="checkbox" name="fields[]" value="<?= esc($key) ?>" id="field_<?= esc($key) ?>" <?= $info['checked'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="field_<?= esc($key) ?>">
                                        <?= esc($info['label']) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i> Exportar a Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('list-customers-table');
    const searchInput = document.getElementById('list-customer-search');
    const paginationContainer = document.getElementById('customer-pagination-container');
    
    if (!table || !paginationContainer) return;
    
    const tbody = table.tBodies[0];
    if (!tbody) return;
    
    const rows = Array.from(tbody.querySelectorAll(':scope > tr'));
    const dataRows = rows.filter(row => !row.querySelector('[colspan]'));
    
    const pageSize = 10;
    let currentPage = 1;
    let filteredRows = [...dataRows];
    
    // Create pagination elements
    const wrapper = document.createElement('div');
    wrapper.className = 'codex-pagination';
    
    const summary = document.createElement('div');
    summary.className = 'codex-pagination__summary';
    
    const controls = document.createElement('div');
    controls.className = 'codex-pagination__controls';
    
    const prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'codex-pagination__btn';
    prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
    prev.setAttribute('aria-label', 'Pagina anterior');
    
    const pages = document.createElement('div');
    pages.className = 'codex-pagination__pages';
    
    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'codex-pagination__btn';
    next.innerHTML = '<i class="bi bi-chevron-right"></i>';
    next.setAttribute('aria-label', 'Pagina siguiente');
    
    controls.append(prev, pages, next);
    wrapper.append(summary, controls);
    paginationContainer.appendChild(wrapper);
    
    // Create a special row to display when no search results are found
    const noResultsRow = document.createElement('tr');
    noResultsRow.id = 'no-results-row';
    noResultsRow.style.display = 'none';
    noResultsRow.innerHTML = `<td colspan="5" class="text-secondary text-center py-3">No se encontraron clientes que coincidan con la búsqueda.</td>`;
    tbody.appendChild(noResultsRow);
    
    function renderPageButtons(pageCount) {
        pages.innerHTML = '';
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(pageCount, currentPage + 2);
        
        for (let page = start; page <= end; page++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `codex-pagination__btn${page === currentPage ? ' is-active' : ''}`;
            button.textContent = String(page);
            button.addEventListener('click', () => {
                currentPage = page;
                updateView();
            });
            pages.appendChild(button);
        }
    }
    
    function updateView() {
        const totalRows = filteredRows.length;
        
        if (totalRows === 0) {
            dataRows.forEach(row => row.style.display = 'none');
            noResultsRow.style.display = '';
            wrapper.style.display = 'none';
            return;
        }
        
        noResultsRow.style.display = 'none';
        
        const pageCount = Math.ceil(totalRows / pageSize);
        if (currentPage > pageCount) currentPage = pageCount || 1;
        if (currentPage < 1) currentPage = 1;
        
        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = startIndex + pageSize;
        
        dataRows.forEach(row => {
            const index = filteredRows.indexOf(row);
            if (index >= startIndex && index < endIndex) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        if (totalRows <= pageSize) {
            wrapper.style.display = 'none';
        } else {
            wrapper.style.display = 'flex';
            summary.textContent = `Mostrando ${startIndex + 1}-${Math.min(endIndex, totalRows)} de ${totalRows} registros`;
            prev.disabled = currentPage === 1;
            next.disabled = currentPage === pageCount;
            renderPageButtons(pageCount);
        }
    }
    
    prev.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            updateView();
        }
    });
    
    next.addEventListener('click', () => {
        const pageCount = Math.ceil(filteredRows.length / pageSize);
        if (currentPage < pageCount) {
            currentPage++;
            updateView();
        }
    });
    
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase().trim();
            
            filteredRows = dataRows.filter(row => {
                const name = row.dataset.name || '';
                const doc = row.dataset.doc || '';
                return name.includes(q) || doc.includes(q);
            });
            
            currentPage = 1;
            updateView();
        });
    }
    
    updateView();
    
    const btnSelectAll = document.getElementById('btn-select-all');
    const btnDeselectAll = document.getElementById('btn-deselect-all');
    if (btnSelectAll && btnDeselectAll) {
        btnSelectAll.addEventListener('click', () => {
            document.querySelectorAll('.export-field-checkbox').forEach(cb => cb.checked = true);
        });
        btnDeselectAll.addEventListener('click', () => {
            document.querySelectorAll('.export-field-checkbox').forEach(cb => cb.checked = false);
        });
    }
});
</script>
<?= $this->endSection() ?>
