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
                    <div style="max-width: 300px; width: 100%;">
                        <input type="text" id="list-customer-search" class="form-control form-control-sm" placeholder="Buscar cliente por nombre o documento...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover table-sm mb-0" id="list-customers-table" data-codex-pagination="10">
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
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Live client-side search/filter for the customer datatable
    const searchInput = document.getElementById('list-customer-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#list-customers-table tbody tr');
            rows.forEach(row => {
                const name = row.dataset.name || '';
                const doc = row.dataset.doc || '';
                if (name.includes(q) || doc.includes(q)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>
<?= $this->endSection() ?>
