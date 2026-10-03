<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Inventario</h1>
        <p class="text-secondary mb-0">Control operativo de stock, movimientos, depositos, alertas y trazabilidad.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($companies)): ?>
            <form method="get" action="<?= site_url('inventario') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>>
                            <?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i
                        class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
        <a href="<?= site_url('inventario/kardex' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
            class="btn btn-outline-dark icon-btn" title="Kardex" aria-label="Kardex"><i
                class="bi bi-journal-list"></i></a>
        <?php if ($context['canManage']): ?>
            <a href="<?= site_url('inventario/reservas/nueva' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Reserva de stock"
                data-popup-subtitle="Comprometer existencias operativas por deposito." title="Reservar stock"
                aria-label="Reservar stock"><i class="bi bi-shield-lock"></i></a>
            <a href="<?= site_url('inventario/movimientos/nuevo' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Movimiento de stock"
                data-popup-subtitle="Registrar ingreso, egreso, transferencia o ajuste." title="Nuevo movimiento"
                aria-label="Nuevo movimiento"><i class="bi bi-arrow-left-right"></i></a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4" id="inventory-summary-card">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h4 mb-1"><?= esc($context['company']['name']) ?></h2>
                <p class="text-secondary mb-0">Sistema asignado con acceso
                    <?= $context['canManage'] ? 'de gestion' : 'de consulta' ?>.</p>
            </div>
            <div class="small text-secondary">Alertas por email:
                <?= (int) ($settings['email_notifications'] ?? 0) === 1 ? 'activas' : 'inactivas' ?></div>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-secondary small">Productos activos</div>
                    <div class="display-6 fw-semibold"><?= esc((string) $summary['products']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-secondary small">Depositos</div>
                    <div class="display-6 fw-semibold"><?= esc((string) $summary['warehouses']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-secondary small">Stock consolidado</div>
                    <div class="display-6 fw-semibold"><?= number_format((int) $summary['total_stock'], 0, ',', '.') ?>
                    </div>
                    <div class="small text-secondary mt-2">Reservado:
                        <?= number_format((int) ($summary['reserved_stock'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-secondary small">Items criticos</div>
                    <div class="display-6 fw-semibold text-danger"><?= esc((string) $summary['critical_products']) ?>
                    </div>
                    <div class="small text-secondary mt-2">Reservas activas:
                        <?= esc((string) ($summary['active_reservations'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-secondary small">Ventas del dia</div>
                    <div class="display-6 fw-semibold"><?= esc((string) ($summary['sales_today'] ?? 0)) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-secondary small">Tickets kiosco del dia</div>
                    <div class="display-6 fw-semibold"><?= esc((string) ($summary['sales_kiosk_today'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" id="inventory-alerts-card">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Alertas inteligentes</h2>
                <div class="mb-3">
                    <div class="small text-secondary mb-2">Stock bajo minimo</div>
                    <?php if (!empty($alerts['critical'])): ?>
                        <?php foreach ($alerts['critical'] as $row): ?>
                            <div class="border rounded-3 p-2 mb-2 alert-search-trigger border-danger-subtle"
                                style="border-left: 4px solid #dc3545 !important;"
                                data-search-term="<?= esc(!empty($row['sku']) ? $row['sku'] : $row['name']) ?>">
                                <strong><?= esc($row['name']) ?></strong>
                                <div class="small text-secondary">Stock:
                                    <?= number_format((int) $row['total_stock'], 0, ',', '.') ?> / Minimo:
                                    <?= number_format((int) $row['min_stock'], 0, ',', '.') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">No hay productos bajo minimo.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <div class="small text-secondary mb-2">Movimientos inusuales</div>
                    <?php if (!empty($alerts['unusual'])): ?>
                        <?php foreach ($alerts['unusual'] as $row): ?>
                            <div class="border rounded-3 p-2 mb-2 alert-search-trigger border-info-subtle"
                                style="border-left: 4px solid #0dcaf0 !important;"
                                data-search-term="<?= esc(!empty($row['sku']) ? $row['sku'] : $row['product_name']) ?>">
                                <strong><?= esc($row['product_name']) ?></strong>
                                <div class="small text-secondary"><?= esc(ucfirst($row['movement_type'])) ?> por
                                    <?= number_format((int) $row['quantity'], 0, ',', '.') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">Sin movimientos fuera del umbral configurado.</div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="small text-secondary mb-2">Sin stock</div>
                    <?php if (!empty($alerts['out_of_stock'])): ?>
                        <?php foreach (array_slice($alerts['out_of_stock'], 0, 4) as $row): ?>
                            <div class="border rounded-3 p-2 mb-2 alert-search-trigger border-dark-subtle"
                                style="border-left: 4px solid #212529 !important;"
                                data-search-term="<?= esc(!empty($row['sku']) ? $row['sku'] : $row['name']) ?>">
                                <strong><?= esc($row['name']) ?></strong>
                                <div class="small text-secondary">Disponible:
                                    <?= number_format((int) ($row['available_stock'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">No hay productos sin stock.</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <div class="small text-secondary mb-2">Productos sin rotacion</div>
                    <?php if (!empty($alerts['no_rotation'])): ?>
                        <?php foreach ($alerts['no_rotation'] as $row): ?>
                            <div class="border rounded-3 p-2 mb-2 alert-search-trigger border-secondary-subtle"
                                style="border-left: 4px solid #6c757d !important;"
                                data-search-term="<?= esc(!empty($row['sku']) ? $row['sku'] : $row['name']) ?>">
                                <strong><?= esc($row['name']) ?></strong>
                                <div class="small text-secondary">
                                    <?= esc($row['last_movement'] ? date('d/m/Y', strtotime($row['last_movement'])) : 'Sin movimientos') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">No hay alertas de rotacion pendientes.</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <div class="small text-secondary mb-2">Sobre stock</div>
                    <?php if (!empty($alerts['overstock'])): ?>
                        <?php foreach (array_slice($alerts['overstock'], 0, 4) as $row): ?>
                            <div class="border rounded-3 p-2 mb-2 alert-search-trigger border-warning-subtle"
                                style="border-left: 4px solid #ffc107 !important;"
                                data-search-term="<?= esc(!empty($row['sku']) ? $row['sku'] : $row['name']) ?>">
                                <strong><?= esc($row['name']) ?></strong>
                                <div class="small text-secondary">Actual:
                                    <?= number_format((int) $row['total_stock'], 0, ',', '.') ?> / Maximo:
                                    <?= number_format((int) $row['max_stock'], 0, ',', '.') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">Sin alertas de sobre stock.</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <div class="small text-secondary mb-2">Reservas activas</div>
                    <?php if (!empty($alerts['reservations'])): ?>
                        <?php foreach ($alerts['reservations'] as $row): ?>
                            <div class="border rounded-3 p-2 mb-2 alert-search-trigger border-success-subtle"
                                style="border-left: 4px solid #20c997 !important;"
                                data-search-term="<?= esc(!empty($row['sku']) ? $row['sku'] : $row['product_name']) ?>">
                                <strong><?= esc($row['product_name']) ?></strong>
                                <div class="small text-secondary"><?= number_format((int) $row['quantity'], 0, ',', '.') ?> en
                                    <?= esc($row['warehouse_name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">No hay stock comprometido.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4" id="inventory-products-card">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1">Stock por producto</h2>
                        <p class="text-secondary mb-0">Visualizacion consolidada por producto y trazabilidad operativa.
                        </p>
                    </div>
                    <?php if ($context['canConfigure']): ?>
                        <a href="<?= site_url('inventario/productos/nuevo' . (!empty($companies) ? '?company_id=' . $selectedCompanyId . '&redirect_to=' . urlencode(site_url('inventario?company_id=' . $selectedCompanyId)) : '')) ?>"
                            class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Producto"
                            data-popup-subtitle="Registrar productos inventariables para la empresa." title="Nuevo producto"
                            aria-label="Nuevo producto"><i class="bi bi-plus-lg"></i></a>
                    <?php endif; ?>

                </div>

                <!-- Modern Toolbar: Search Input + Status Filter Pills -->
                <div
                    class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 bg-light p-3 rounded-4">
                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text bg-white border-end-0 text-secondary"><i
                                class="bi bi-search"></i></span>
                        <input type="text" id="productSearchInput" class="form-control border-start-0 ps-0 shadow-none"
                            placeholder="Buscar por SKU, nombre, marca..." aria-label="Buscar producto">
                        <button class="btn btn-white border border-start-0 text-secondary" type="button"
                            id="clearSearchBtn" style="display: none;" title="Limpiar busqueda"><i
                                class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="d-flex flex-wrap gap-2" id="filterPillsContainer">
                        <button class="btn btn-sm btn-dark filter-pill rounded-pill px-3 py-1.5 active"
                            data-filter="all">Todos</button>
                        <button class="btn btn-sm btn-outline-danger filter-pill rounded-pill px-3 py-1.5"
                            data-filter="critical"><i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Críticos</button>
                        <button class="btn btn-sm btn-outline-warning filter-pill rounded-pill px-3 py-1.5"
                            data-filter="overstock"><i class="bi bi-graph-up-arrow me-1"></i> Sobre stock</button>
                        <button class="btn btn-sm btn-outline-success filter-pill rounded-pill px-3 py-1.5"
                            data-filter="healthy"><i class="bi bi-check-circle-fill me-1"></i> Saludables</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="inventory-products-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Producto</th>
                                <th>Unidad</th>
                                <th>Stock</th>
                                <th>Reservado</th>
                                <th>Disponible</th>
                                <th>Min/Max</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr class="product-row" data-product-id="<?= esc($product['id']) ?>"
                                    data-sku="<?= esc($product['sku']) ?>"
                                    data-name="<?= esc($product['name']) ?>"
                                    data-category-brand="<?= esc(trim(($product['category'] ?? '') . ' ' . ($product['brand'] ?? ''))) ?>"
                                    data-status="<?= $product['is_critical'] ? 'critical' : (($product['is_overstock'] ?? false) ? 'overstock' : 'healthy') ?>"
                                    data-active="<?= (int) ($product['active'] ?? 1) ?>">
                                    <td><?= esc($product['sku']) ?></td>
                                    <td><?= esc($product['name']) ?>
                                        <div class="small text-secondary">
                                            <?= esc(trim(($product['category'] ?? '') . ' ' . ($product['brand'] ?? ''))) ?>
                                        </div>
                                    </td>
                                    <td><?= esc($product['unit']) ?></td>
                                    <td><?= number_format((int) $product['total_stock'], 0, ',', '.') ?></td>
                                    <td><?= number_format((int) ($product['reserved_stock'] ?? 0), 0, ',', '.') ?></td>
                                    <td><?= number_format((int) ($product['available_stock'] ?? 0), 0, ',', '.') ?></td>
                                    <td><?= number_format((int) $product['min_stock'], 0, ',', '.') ?> /
                                        <?= number_format((int) ($product['max_stock'] ?? 0), 0, ',', '.') ?></td>
                                    <td>
                                        <?php if ((int) ($product['active'] ?? 1) === 0): ?>
                                             <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">Inactivo</span>
                                        <?php else: ?>
                                            <span class="<?= $product['is_critical'] ? 'text-danger fw-semibold' : (($product['is_overstock'] ?? false) ? 'text-warning fw-semibold' : 'text-success fw-semibold') ?>">
                                                <?= $product['is_critical'] ? 'Critico' : (($product['is_overstock'] ?? false) ? 'Sobre stock' : 'Saludable') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($context['canManage'] && (int) ($product['active'] ?? 1) === 1): ?>
                                            <a href="<?= site_url('inventario/movimientos/nuevo?popup=1' . (!empty($companies) ? '&company_id=' . $selectedCompanyId : '') . '&product_id=' . $product['id'] . '&movement_type=ajuste&adjustment_mode=increase&reason=Ajuste%20manual%20de%20stock') ?>"
                                                class="btn btn-sm btn-outline-dark icon-btn" data-popup="true"
                                                data-popup-title="Editar stock"
                                                data-popup-subtitle="Ajustar stock del producto con trazabilidad."
                                                title="Editar stock" aria-label="Editar stock"><i
                                                    class="bi bi-pencil-square"></i></a>
                                        <?php elseif ($context['canManage']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary icon-btn opacity-50" disabled
                                                title="Producto inactivo. Debe habilitarse desde Configuración de inventario para ajustar existencias."
                                                aria-label="Producto inactivo"><i class="bi bi-pencil-square"></i></button>
                                        <?php endif; ?>
                                        <a href="<?= site_url('inventario/productos/' . $product['id'] . '/trazabilidad' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                                            class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true"
                                            data-popup-title="Trazabilidad del producto"
                                            data-popup-subtitle="Historial, stock por deposito y responsables."
                                            title="Ver trazabilidad" aria-label="Ver trazabilidad"><i
                                                class="bi bi-diagram-3"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="no-results-row" style="display: none;">
                                <td colspan="9" class="text-secondary text-center py-4">No se encontraron productos que
                                    coincidan con la búsqueda o filtro.</td>
                            </tr>
                            <?php if ($products === []): ?>
                                <tr id="no-products-row">
                                    <td colspan="9" class="text-secondary">Todavia no hay productos registrados.</td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100" id="inventory-reservations-card">
                    <div class="card-body p-4">
                        <h2 class="h4 mb-3">Reservas activas</h2>
                        <div id="inventory-reservations-list">
                            <?php foreach ($activeReservations as $row): ?>
                                <div class="border rounded-3 p-3 mb-2 reservation-item">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <strong><?= esc($row['product_name']) ?></strong>
                                            <div class="small text-secondary"><?= esc($row['warehouse_name']) ?> /
                                                <?= esc($row['reference'] ?: 'Sin referencia') ?></div>
                                            <div class="small text-secondary">Reservado por <?= esc($row['reserved_by_name']) ?>
                                                el <?= esc(date('d/m/Y H:i', strtotime($row['reserved_at']))) ?></div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-semibold"><?= number_format((int) $row['quantity'], 0, ',', '.') ?>
                                            </div>
                                            <?php if ($context['canManage']): ?>
                                                <form method="post"
                                                    action="<?= site_url('inventario/reservas/' . $row['id'] . '/liberar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                                                    class="mt-2 release-reservation-form">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-success icon-btn" title="Liberar reserva"
                                                        aria-label="Liberar reserva"><i class="bi bi-unlock"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($activeReservations === []): ?>
                                <div class="text-secondary">No hay reservas activas.</div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100" id="inventory-warehouses-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h2 class="h4 mb-1">Depositos</h2>
                                <p class="text-secondary mb-0">Existencias independientes por ubicacion operativa.</p>
                            </div>
                            <?php if ($context['canConfigure']): ?>
                                <a href="<?= site_url('inventario/depositos/nuevo' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                                    class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Deposito"
                                    data-popup-subtitle="Registrar un deposito operativo para Inventario."
                                    title="Nuevo deposito" aria-label="Nuevo deposito"><i class="bi bi-plus-lg"></i></a>
                            <?php endif; ?>
                        </div>
                        <div id="inventory-warehouses-list">
                            <?php foreach ($warehouses as $warehouse): ?>
                                <div class="border rounded-3 p-3 mb-2 warehouse-item">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <strong><?= esc($warehouse['name']) ?></strong>
                                            <div class="small text-secondary"><?= esc($warehouse['code']) ?> /
                                                <?= esc($warehouse['type']) ?></div>
                                        </div>
                                        <span
                                            class="small <?= (int) $warehouse['active'] === 1 ? 'text-success' : 'text-danger' ?>"><?= (int) $warehouse['active'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                                    </div>
                                    <div class="small text-secondary mt-2">Stock:
                                        <?= number_format((int) $warehouse['total_stock'], 0, ',', '.') ?> / Productos:
                                        <?= esc((string) $warehouse['product_count']) ?></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($warehouses === []): ?>
                                <div class="text-secondary">No hay depósitos registrados.</div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 h-100" id="inventory-movements-card">
                    <div class="card-body p-4">
                        <h2 class="h4 mb-3">Ultimos movimientos</h2>
                        <div id="recent-movements-list">
                            <?php foreach ($recentMovements as $row): ?>
                                <div class="border rounded-3 p-3 mb-2 recent-movement-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between gap-2 mb-1">
                                                <strong><?= esc($row['product_name']) ?></strong>
                                                <span
                                                    class="small text-secondary text-end"><?= esc(date('d/m/Y', strtotime($row['occurred_at']))) ?><br><?= esc(date('H:i', strtotime($row['occurred_at']))) ?></span>
                                            </div>
                                            <div class="small text-secondary">
                                                <?= esc(ucfirst($row['movement_type'])) ?> /
                                                <?= number_format((int) $row['quantity'], 0, ',', '.') ?>
                                                <?php if (!empty($row['source_name'])): ?> / Origen:
                                                    <?= esc($row['source_name']) ?>    <?php endif; ?>
                                                <?php if (!empty($row['destination_name'])): ?> / Destino:
                                                    <?= esc($row['destination_name']) ?>    <?php endif; ?>
                                            </div>
                                            <div class="small text-secondary">Responsable:
                                                <?= esc($row['user_name']) ?>    <?= !empty($row['reason']) ? ' / ' . esc($row['reason']) : '' ?>
                                            </div>
                                        </div>
                                        <a href="<?= site_url('inventario/productos/' . $row['product_id'] . '/trazabilidad' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                                            class="btn btn-sm btn-outline-dark icon-btn flex-shrink-0" data-popup="true"
                                            data-popup-title="Trazabilidad del producto"
                                            data-popup-subtitle="Historial, stock por deposito y responsables."
                                            title="Ver detalle del movimiento" aria-label="Ver detalle del movimiento"><i
                                                class="bi bi-diagram-3"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($recentMovements === []): ?>
                                <div class="text-secondary">Todavia no hay movimientos registrados.</div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .alert-search-trigger {
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .alert-search-trigger:hover {
        transform: translateX(6px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
        background-color: var(--bs-light, #f8f9fa);
    }

    .filter-pill {
        transition: all 0.2s ease-in-out;
    }

    .filter-pill:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    }

    /* CODEX Pagination */
    .codex-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 14px;
        border-top: 1px solid var(--bs-border-color, #e9ecef);
        margin-top: 14px;
    }
    .codex-pagination__summary {
        font-size: 11px;
        color: var(--bs-secondary, #6c757d);
        font-weight: 500;
    }
    .codex-pagination__controls {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .codex-pagination__pages {
        display: flex;
        align-items: center;
        gap: 3px;
    }
    .codex-pagination__btn {
        min-width: 28px;
        height: 28px;
        padding: 0 6px;
        border-radius: 7px;
        border: 1px solid var(--bs-border-color, #dee2e6);
        background: #fff;
        color: var(--bs-body-color, #212529);
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .codex-pagination__btn:hover:not(:disabled) {
        background: #f8f9fa;
        border-color: #ced4da;
        color: #212529;
    }
    .codex-pagination__btn.is-active {
        background: #212529;
        border-color: #212529;
        color: #fff;
    }
    .codex-pagination__btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const table = document.getElementById('inventory-products-table');
        if (!table) return;

        const companyQuery = '<?= !empty($companies) ? '&company_id=' . $selectedCompanyId : '' ?>';
        const siteBase = '<?= rtrim(site_url(), '/') ?>';
        const canManage = <?= $context['canManage'] ? 'true' : 'false' ?>;

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatInt(val) {
            return Number(val || 0).toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        function buildInventoryProductRowHtml(item) {
            const isActive = Number(item.active ?? 1) === 1;
            const isCritical = Boolean(item.is_critical);
            const isOverstock = Boolean(item.is_overstock);
            const statusLabel = isCritical ? 'Critico' : (isOverstock ? 'Sobre stock' : 'Saludable');
            const statusClass = isCritical ? 'text-danger fw-semibold' : (isOverstock ? 'text-warning fw-semibold' : 'text-success fw-semibold');

            let statusHtml = '';
            if (!isActive) {
                statusHtml = '<span class="badge bg-secondary-subtle text-secondary border px-2 py-1">Inactivo</span>';
            } else {
                statusHtml = `<span class="${statusClass}">${statusLabel}</span>`;
            }

            let manageBtn = '';
            if (canManage && isActive) {
                manageBtn = `<a href="${siteBase}/inventario/movimientos/nuevo?popup=1${companyQuery}&product_id=${item.id}&movement_type=ajuste&adjustment_mode=increase&reason=Ajuste%20manual%20de%20stock" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar stock" data-popup-subtitle="Ajustar stock del producto con trazabilidad." title="Editar stock" aria-label="Editar stock"><i class="bi bi-pencil-square"></i></a>`;
            } else if (canManage) {
                manageBtn = `<button type="button" class="btn btn-sm btn-outline-secondary icon-btn opacity-50" disabled title="Producto inactivo. Debe habilitarse desde Configuración de inventario para ajustar existencias." aria-label="Producto inactivo"><i class="bi bi-pencil-square"></i></button>`;
            }

            const catBrand = ((item.category || '') + ' ' + (item.brand || '')).trim();

            return `
                <td>${escapeHtml(item.sku || '')}</td>
                <td>${escapeHtml(item.name || '')}
                    <div class="small text-secondary">${escapeHtml(catBrand)}</div>
                </td>
                <td>${escapeHtml(item.unit || 'unidad')}</td>
                <td>${formatInt(item.total_stock)}</td>
                <td>${formatInt(item.reserved_stock)}</td>
                <td>${formatInt(item.available_stock)}</td>
                <td>${formatInt(item.min_stock)} / ${formatInt(item.max_stock)}</td>
                <td>${statusHtml}</td>
                <td class="text-end">
                    ${manageBtn}
                    <a href="${siteBase}/inventario/productos/${item.id}/trazabilidad${companyQuery ? '?' + companyQuery.substring(1) : ''}" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Trazabilidad del producto" data-popup-subtitle="Historial, stock por deposito y responsables." title="Ver trazabilidad" aria-label="Ver trazabilidad"><i class="bi bi-diagram-3"></i></a>
                </td>
            `;
        }

        const searchInput = document.getElementById('productSearchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const filterPills = document.querySelectorAll('.filter-pill');
        const tableBody = table.querySelector('tbody');
        let allRows = Array.from(tableBody.querySelectorAll('.product-row'));
        const noResultsRow = document.getElementById('no-results-row');
        const noProductsRow = document.getElementById('no-products-row');
        const pageSize = 5;
        let currentPage = 1;
        let currentFilter = 'all';
        let searchQuery = '';

        // Create pagination container for products
        const tableResponsive = table.closest('.table-responsive');
        const paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'codex-pagination mt-4';
        tableResponsive.after(paginationWrapper);

        function refreshInventoryProducts(targetRow = null) {
            allRows = Array.from(tableBody.querySelectorAll('.product-row'));
            
            if (targetRow) {
                const query = searchInput.value.toLowerCase().trim();
                const matched = allRows.filter(row => {
                    const sku = (row.dataset.sku || '').toLowerCase();
                    const name = (row.dataset.name || '').toLowerCase();
                    const catBrand = (row.dataset.categoryBrand || '').toLowerCase();
                    const status = row.dataset.status || '';

                    const matchesSearch = !query ||
                        sku.includes(query) ||
                        name.includes(query) ||
                        catBrand.includes(query);

                    const matchesStatus = currentFilter === 'all' || status === currentFilter;
                    return matchesSearch && matchesStatus;
                });

                const idx = matched.indexOf(targetRow);
                if (idx !== -1) {
                    currentPage = Math.floor(idx / pageSize) + 1;
                }
            }

            updateFilters();
        }

        function updateFilters() {
            searchQuery = searchInput.value.toLowerCase().trim();
            if (searchQuery) {
                clearSearchBtn.style.display = 'block';
            } else {
                clearSearchBtn.style.display = 'none';
            }

            let matchedCount = 0;
            const matchedRows = [];

            allRows.forEach(row => {
                const sku = (row.dataset.sku || '').toLowerCase();
                const name = (row.dataset.name || '').toLowerCase();
                const catBrand = (row.dataset.categoryBrand || '').toLowerCase();
                const status = row.dataset.status || '';

                // Check if matches search
                const matchesSearch = !searchQuery ||
                    sku.includes(searchQuery) ||
                    name.includes(searchQuery) ||
                    catBrand.includes(searchQuery);

                // Check if matches pill filter
                const matchesStatus = currentFilter === 'all' || status === currentFilter;

                if (matchesSearch && matchesStatus) {
                    matchedRows.push(row);
                    matchedCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (matchedCount === 0) {
                if (noResultsRow) {
                    noResultsRow.style.display = '';
                }
                if (noProductsRow) {
                    noProductsRow.style.display = 'none';
                }
                paginationWrapper.innerHTML = '';
            } else {
                if (noResultsRow) {
                    noResultsRow.style.display = 'none';
                }
                // Paginate matched rows
                const pageCount = Math.ceil(matchedRows.length / pageSize);
                if (currentPage > pageCount) {
                    currentPage = Math.max(1, pageCount);
                }

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = startIndex + pageSize;

                matchedRows.forEach((row, index) => {
                    if (index >= startIndex && index < endIndex) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                renderPagination(matchedRows.length, pageCount);
            }
        }

        function renderPagination(totalCount, pageCount) {
            paginationWrapper.innerHTML = '';
            if (pageCount <= 1) {
                const summary = document.createElement('div');
                summary.className = 'codex-pagination__summary';
                summary.textContent = `Mostrando ${totalCount} registro${totalCount === 1 ? '' : 's'}`;
                paginationWrapper.appendChild(summary);
                return;
            }

            const summary = document.createElement('div');
            summary.className = 'codex-pagination__summary';
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalCount);
            summary.textContent = `Mostrando ${startIndex + 1}-${endIndex} de ${totalCount} registros`;

            const controls = document.createElement('div');
            controls.className = 'codex-pagination__controls';

            // First button (<<)
            if (pageCount > 4) {
                const first = document.createElement('button');
                first.type = 'button';
                first.className = 'codex-pagination__btn';
                first.innerHTML = '<i class="bi bi-chevron-double-left"></i>';
                first.disabled = currentPage === 1;
                first.title = 'Primera página';
                first.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage = 1;
                        updateFilters();
                    }
                });
                controls.appendChild(first);
            }

            // Prev button (<)
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'codex-pagination__btn';
            prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prev.disabled = currentPage === 1;
            prev.title = 'Página anterior';
            prev.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    updateFilters();
                }
            });
            controls.appendChild(prev);

            // Page numbers
            const pages = document.createElement('div');
            pages.className = 'codex-pagination__pages';

            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(pageCount, currentPage + 2);

            for (let p = startPage; p <= endPage; p++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `codex-pagination__btn${p === currentPage ? ' is-active' : ''}`;
                btn.textContent = String(p);
                btn.title = `Página ${p}`;
                btn.addEventListener('click', () => {
                    currentPage = p;
                    updateFilters();
                });
                pages.appendChild(btn);
            }
            controls.appendChild(pages);

            // Next button (>)
            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'codex-pagination__btn';
            next.innerHTML = '<i class="bi bi-chevron-right"></i>';
            next.disabled = currentPage === pageCount;
            next.title = 'Página siguiente';
            next.addEventListener('click', () => {
                if (currentPage < pageCount) {
                    currentPage++;
                    updateFilters();
                }
            });
            controls.appendChild(next);

            // Last button (>>)
            if (pageCount > 4) {
                const last = document.createElement('button');
                last.type = 'button';
                last.className = 'codex-pagination__btn';
                last.innerHTML = '<i class="bi bi-chevron-double-right"></i>';
                last.disabled = currentPage === pageCount;
                last.title = 'Última página';
                last.addEventListener('click', () => {
                    if (currentPage < pageCount) {
                        currentPage = pageCount;
                        updateFilters();
                    }
                });
                controls.appendChild(last);
            }

            paginationWrapper.appendChild(summary);
            paginationWrapper.appendChild(controls);
        }

        // Input event for search
        searchInput.addEventListener('input', () => {
            currentPage = 1;
            updateFilters();
        });

        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            currentPage = 1;
            updateFilters();
        });

        // Pill click handlers
        filterPills.forEach(pill => {
            pill.addEventListener('click', () => {
                filterPills.forEach(p => {
                    p.classList.remove('active');
                    const filterType = p.dataset.filter;
                    if (filterType === 'all') {
                        p.classList.remove('btn-dark');
                        p.classList.add('btn-outline-secondary');
                    } else if (filterType === 'critical') {
                        p.classList.remove('btn-danger');
                        p.classList.add('btn-outline-danger');
                    } else if (filterType === 'overstock') {
                        p.classList.remove('btn-warning', 'text-dark');
                        p.classList.add('btn-outline-warning');
                    } else if (filterType === 'healthy') {
                        p.classList.remove('btn-success');
                        p.classList.add('btn-outline-success');
                    }
                });

                // Set active classes
                pill.classList.add('active');
                const filterType = pill.dataset.filter;
                if (filterType === 'all') {
                    pill.classList.remove('btn-outline-secondary');
                    pill.classList.add('btn-dark');
                } else if (filterType === 'critical') {
                    pill.classList.remove('btn-outline-danger');
                    pill.classList.add('btn-danger');
                } else if (filterType === 'overstock') {
                    pill.classList.remove('btn-outline-warning');
                    pill.classList.add('btn-warning', 'text-dark');
                } else if (filterType === 'healthy') {
                    pill.classList.remove('btn-outline-success');
                    pill.classList.add('btn-success');
                }

                currentFilter = filterType;
                currentPage = 1;
                updateFilters();
            });
        });

        // Connect smart alerts
        function connectAlertTriggers() {
            document.querySelectorAll('.alert-search-trigger').forEach(trigger => {
                trigger.onclick = () => {
                    const searchTerm = trigger.dataset.searchTerm || '';
                    searchInput.value = searchTerm;

                    // Highlight search input briefly
                    searchInput.focus();
                    searchInput.classList.add('is-valid');
                    setTimeout(() => {
                        searchInput.classList.remove('is-valid');
                    }, 1000);

                    // Reset status filter to "all"
                    const allPill = document.querySelector('.filter-pill[data-filter="all"]');
                    if (allPill) {
                        allPill.click();
                    } else {
                        currentFilter = 'all';
                        currentPage = 1;
                        updateFilters();
                    }
                };
            });
        }
        connectAlertTriggers();

        // Initial render for products table
        updateFilters();

        // Generic Client-side Card List Pagination (5 items per page)
        function setupListPagination(containerId, itemSelector, pageSize = 5) {
            const container = document.getElementById(containerId);
            if (!container) return null;

            let paginationWrapper = container.parentNode.querySelector(`.codex-pagination[data-for="${containerId}"]`);
            if (!paginationWrapper) {
                paginationWrapper = document.createElement('div');
                paginationWrapper.className = 'codex-pagination mt-3';
                paginationWrapper.dataset.for = containerId;
                container.after(paginationWrapper);
            }

            let currentPage = 1;

            function render() {
                const items = Array.from(container.querySelectorAll(itemSelector));
                const totalItems = items.length;

                if (totalItems <= pageSize) {
                    items.forEach(item => { item.style.display = ''; });
                    paginationWrapper.innerHTML = '';
                    return;
                }

                const pageCount = Math.ceil(totalItems / pageSize);
                if (currentPage > pageCount) currentPage = pageCount;
                if (currentPage < 1) currentPage = 1;

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = startIndex + pageSize;

                items.forEach((item, index) => {
                    if (index >= startIndex && index < endIndex) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });

                paginationWrapper.innerHTML = '';

                const summary = document.createElement('div');
                summary.className = 'codex-pagination__summary';
                summary.textContent = `Mostrando ${startIndex + 1}-${Math.min(endIndex, totalItems)} de ${totalItems} registros`;

                const controls = document.createElement('div');
                controls.className = 'codex-pagination__controls';

                // First (<<)
                if (pageCount > 4) {
                    const first = document.createElement('button');
                    first.type = 'button';
                    first.className = 'codex-pagination__btn';
                    first.innerHTML = '<i class="bi bi-chevron-double-left"></i>';
                    first.disabled = currentPage === 1;
                    first.title = 'Primera página';
                    first.addEventListener('click', () => {
                        if (currentPage > 1) {
                            currentPage = 1;
                            render();
                        }
                    });
                    controls.appendChild(first);
                }

                // Prev (<)
                const prev = document.createElement('button');
                prev.type = 'button';
                prev.className = 'codex-pagination__btn';
                prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
                prev.disabled = currentPage === 1;
                prev.title = 'Página anterior';
                prev.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage--;
                        render();
                    }
                });
                controls.appendChild(prev);

                // Page numbers
                const pages = document.createElement('div');
                pages.className = 'codex-pagination__pages';

                const startPage = Math.max(1, currentPage - 2);
                const endPage = Math.min(pageCount, currentPage + 2);

                for (let p = startPage; p <= endPage; p++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `codex-pagination__btn${p === currentPage ? ' is-active' : ''}`;
                    btn.textContent = String(p);
                    btn.title = `Página ${p}`;
                    btn.addEventListener('click', () => {
                        currentPage = p;
                        render();
                    });
                    pages.appendChild(btn);
                }
                controls.appendChild(pages);

                // Next (>)
                const next = document.createElement('button');
                next.type = 'button';
                next.className = 'codex-pagination__btn';
                next.innerHTML = '<i class="bi bi-chevron-right"></i>';
                next.disabled = currentPage === pageCount;
                next.title = 'Página siguiente';
                next.addEventListener('click', () => {
                    if (currentPage < pageCount) {
                        currentPage++;
                        render();
                    }
                });
                controls.appendChild(next);

                // Last (>>)
                if (pageCount > 4) {
                    const last = document.createElement('button');
                    last.type = 'button';
                    last.className = 'codex-pagination__btn';
                    last.innerHTML = '<i class="bi bi-chevron-double-right"></i>';
                    last.disabled = currentPage === pageCount;
                    last.title = 'Última página';
                    last.addEventListener('click', () => {
                        if (currentPage < pageCount) {
                            currentPage = pageCount;
                            render();
                        }
                    });
                    controls.appendChild(last);
                }

                paginationWrapper.appendChild(summary);
                paginationWrapper.appendChild(controls);
            }

            render();

            return {
                refresh: () => {
                    currentPage = 1;
                    render();
                },
                render
            };
        }

        // Initialize 5-items pagination on all card lists
        const reservationsPaginator = setupListPagination('inventory-reservations-list', '.reservation-item', 5);
        const warehousesPaginator = setupListPagination('inventory-warehouses-list', '.warehouse-item', 5);
        const movementsPaginator = setupListPagination('recent-movements-list', '.recent-movement-item', 5);

        // Bind liberation form handlers for instant AJAX execution without full reload
        function bindReservationReleaseForms() {
            document.querySelectorAll('.release-reservation-form').forEach(form => {
                form.onsubmit = async (e) => {
                    e.preventDefault();
                    const submitBtn = form.querySelector('button[type="submit"], button');
                    if (submitBtn) submitBtn.disabled = true;
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (window.showCodexToast) {
                            window.showCodexToast('Reserva liberada correctamente.', 'success');
                        }
                        await reloadInventoryData();
                    } catch (err) {
                        console.error('Error al liberar reserva:', err);
                        form.submit();
                    }
                };
            });
        }
        bindReservationReleaseForms();

        // Seamless AJAX refresh of the entire Inventory Dashboard
        let isInventoryReloading = false;
        async function reloadInventoryData() {
            if (isInventoryReloading) return;
            isInventoryReloading = true;

            try {
                const response = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });
                if (!response.ok) throw new Error('Error al recargar dashboard');

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // 1. Update KPI Summary Cards
                const newSummary = doc.getElementById('inventory-summary-card');
                const curSummary = document.getElementById('inventory-summary-card');
                if (newSummary && curSummary) {
                    curSummary.innerHTML = newSummary.innerHTML;
                }

                // 2. Update Smart Alerts Card
                const newAlerts = doc.getElementById('inventory-alerts-card');
                const curAlerts = document.getElementById('inventory-alerts-card');
                if (newAlerts && curAlerts) {
                    curAlerts.innerHTML = newAlerts.innerHTML;
                    connectAlertTriggers();
                }

                // 3. Update Products Table Body
                const newProductsTable = doc.getElementById('inventory-products-table');
                if (newProductsTable) {
                    const newTbody = newProductsTable.querySelector('tbody');
                    if (newTbody) {
                        tableBody.innerHTML = newTbody.innerHTML;
                        allRows = Array.from(tableBody.querySelectorAll('.product-row'));
                        updateFilters();
                    }
                }

                // 4. Update Reservations List
                const newReservations = doc.getElementById('inventory-reservations-list');
                const curReservations = document.getElementById('inventory-reservations-list');
                if (newReservations && curReservations) {
                    curReservations.innerHTML = newReservations.innerHTML;
                    reservationsPaginator?.refresh();
                    bindReservationReleaseForms();
                }

                // 5. Update Warehouses List
                const newWarehouses = doc.getElementById('inventory-warehouses-list');
                const curWarehouses = document.getElementById('inventory-warehouses-list');
                if (newWarehouses && curWarehouses) {
                    curWarehouses.innerHTML = newWarehouses.innerHTML;
                    warehousesPaginator?.refresh();
                }

                // 6. Update Recent Movements List
                const newMovements = doc.getElementById('recent-movements-list');
                const curMovements = document.getElementById('recent-movements-list');
                if (newMovements && curMovements) {
                    curMovements.innerHTML = newMovements.innerHTML;
                    movementsPaginator?.refresh();
                }

                // 7. Re-bind popups in updated elements
                if (typeof window.initCodexPopups === 'function') {
                    window.initCodexPopups();
                }
            } catch (err) {
                console.error('Error al actualizar datos de inventario:', err);
            } finally {
                isInventoryReloading = false;
            }
        }
        window.reloadInventoryData = reloadInventoryData;

        // Listen for real-time item creation/updates across the inventory module
        window.addEventListener('codex:product-saved', () => reloadInventoryData());
        window.addEventListener('codex:movement-saved', () => reloadInventoryData());
        window.addEventListener('codex:reservation-saved', () => reloadInventoryData());
        window.addEventListener('codex:warehouse-saved', () => reloadInventoryData());
        window.addEventListener('codex:item-saved', () => reloadInventoryData());
        window.addEventListener('message', (event) => {
            if (event.origin === window.location.origin && event.data && (event.data.type === 'codex-popup-saved' || event.data.type === 'codex-popup-close')) {
                reloadInventoryData();
            }
        });
    });
</script>
<?= $this->endSection() ?>