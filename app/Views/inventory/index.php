<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Inventario</h1>
        <p class="text-secondary mb-0">Control operativo de stock, movimientos, depositos, alertas y trazabilidad.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (! empty($companies)): ?>
            <form method="get" action="<?= site_url('inventario') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
        <a href="<?= site_url('inventario/kardex' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Kardex</a>
        <?php if ($context['canManage']): ?>
            <a href="<?= site_url('inventario/reservas/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Reserva de stock" data-popup-subtitle="Comprometer existencias operativas por deposito.">Reservar stock</a>
            <a href="<?= site_url('inventario/movimientos/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Movimiento de stock" data-popup-subtitle="Registrar ingreso, egreso, transferencia o ajuste.">Nuevo movimiento</a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h4 mb-1"><?= esc($context['company']['name']) ?></h2>
                <p class="text-secondary mb-0">Sistema asignado con acceso <?= $context['canManage'] ? 'de gestion' : 'de consulta' ?>.</p>
            </div>
            <div class="small text-secondary">Alertas por email: <?= (int) ($settings['email_notifications'] ?? 0) === 1 ? 'activas' : 'inactivas' ?></div>
        </div>
        <div class="row g-3">
            <div class="col-md-3"><div class="border rounded-4 p-3 h-100"><div class="text-secondary small">Productos activos</div><div class="display-6 fw-semibold"><?= esc((string) $summary['products']) ?></div></div></div>
            <div class="col-md-3"><div class="border rounded-4 p-3 h-100"><div class="text-secondary small">Depositos</div><div class="display-6 fw-semibold"><?= esc((string) $summary['warehouses']) ?></div></div></div>
            <div class="col-md-3"><div class="border rounded-4 p-3 h-100"><div class="text-secondary small">Stock consolidado</div><div class="display-6 fw-semibold"><?= number_format((int) $summary['total_stock'], 0, ',', '.') ?></div><div class="small text-secondary mt-2">Reservado: <?= number_format((int) ($summary['reserved_stock'] ?? 0), 0, ',', '.') ?></div></div></div>
            <div class="col-md-3"><div class="border rounded-4 p-3 h-100"><div class="text-secondary small">Items criticos</div><div class="display-6 fw-semibold text-danger"><?= esc((string) $summary['critical_products']) ?></div><div class="small text-secondary mt-2">Reservas activas: <?= esc((string) ($summary['active_reservations'] ?? 0)) ?></div></div></div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-6"><div class="border rounded-4 p-3 h-100"><div class="text-secondary small">Ventas del dia</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['sales_today'] ?? 0)) ?></div></div></div>
            <div class="col-md-6"><div class="border rounded-4 p-3 h-100"><div class="text-secondary small">Tickets kiosco del dia</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['sales_kiosk_today'] ?? 0)) ?></div></div></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Alertas inteligentes</h2>
                <div class="mb-3">
                    <div class="small text-secondary mb-2">Stock bajo minimo</div>
                    <?php if (! empty($alerts['critical'])): ?>
                        <?php foreach ($alerts['critical'] as $row): ?>
                            <div class="border rounded-3 p-2 mb-2">
                                <strong><?= esc($row['name']) ?></strong>
                                <div class="small text-secondary">Stock: <?= number_format((int) $row['total_stock'], 0, ',', '.') ?> / Minimo: <?= number_format((int) $row['min_stock'], 0, ',', '.') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">No hay productos bajo minimo.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <div class="small text-secondary mb-2">Movimientos inusuales</div>
                    <?php if (! empty($alerts['unusual'])): ?>
                        <?php foreach ($alerts['unusual'] as $row): ?>
                            <div class="small mb-2"><strong><?= esc($row['product_name']) ?></strong> <span class="text-secondary"><?= esc($row['movement_type']) ?> por <?= number_format((int) $row['quantity'], 0, ',', '.') ?></span></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">Sin movimientos fuera del umbral configurado.</div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="small text-secondary mb-2">Sin stock</div>
                    <?php if (! empty($alerts['out_of_stock'])): ?>
                        <?php foreach (array_slice($alerts['out_of_stock'], 0, 4) as $row): ?>
                            <div class="small mb-2"><strong><?= esc($row['name']) ?></strong> <span class="text-secondary">Disponible: <?= number_format((int) ($row['available_stock'] ?? 0), 0, ',', '.') ?></span></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">No hay productos sin stock.</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <div class="small text-secondary mb-2">Productos sin rotacion</div>
                    <?php if (! empty($alerts['no_rotation'])): ?>
                        <?php foreach ($alerts['no_rotation'] as $row): ?>
                            <div class="small mb-2"><strong><?= esc($row['name']) ?></strong> <span class="text-secondary"><?= esc($row['last_movement'] ? date('d/m/Y', strtotime($row['last_movement'])) : 'Sin movimientos') ?></span></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">No hay alertas de rotacion pendientes.</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <div class="small text-secondary mb-2">Sobre stock</div>
                    <?php if (! empty($alerts['overstock'])): ?>
                        <?php foreach (array_slice($alerts['overstock'], 0, 4) as $row): ?>
                            <div class="small mb-2"><strong><?= esc($row['name']) ?></strong> <span class="text-secondary">Actual: <?= number_format((int) $row['total_stock'], 0, ',', '.') ?> / Maximo: <?= number_format((int) $row['max_stock'], 0, ',', '.') ?></span></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">Sin alertas de sobre stock.</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <div class="small text-secondary mb-2">Reservas activas</div>
                    <?php if (! empty($alerts['reservations'])): ?>
                        <?php foreach ($alerts['reservations'] as $row): ?>
                            <div class="small mb-2"><strong><?= esc($row['product_name']) ?></strong> <span class="text-secondary"><?= number_format((int) $row['quantity'], 0, ',', '.') ?> en <?= esc($row['warehouse_name']) ?></span></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-secondary small">No hay stock comprometido.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Stock por producto</h2>
                        <p class="text-secondary mb-0">Visualizacion consolidada por producto y trazabilidad operativa.</p>
                    </div>
                    <?php if ($context['canConfigure']): ?>
                        <a href="<?= site_url('inventario/productos/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Producto" data-popup-subtitle="Registrar productos inventariables para la empresa." title="Nuevo producto" aria-label="Nuevo producto"><i class="bi bi-plus-lg"></i></a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>SKU</th><th>Producto</th><th>Unidad</th><th>Stock</th><th>Reservado</th><th>Disponible</th><th>Min/Max</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= esc($product['sku']) ?></td>
                                    <td><?= esc($product['name']) ?><div class="small text-secondary"><?= esc(trim(($product['category'] ?? '') . ' ' . ($product['brand'] ?? ''))) ?></div></td>
                                    <td><?= esc($product['unit']) ?></td>
                                    <td><?= number_format((int) $product['total_stock'], 0, ',', '.') ?></td>
                                    <td><?= number_format((int) ($product['reserved_stock'] ?? 0), 0, ',', '.') ?></td>
                                    <td><?= number_format((int) ($product['available_stock'] ?? 0), 0, ',', '.') ?></td>
                                    <td><?= number_format((int) $product['min_stock'], 0, ',', '.') ?> / <?= number_format((int) ($product['max_stock'] ?? 0), 0, ',', '.') ?></td>
                                    <td class="<?= $product['is_critical'] ? 'text-danger' : (($product['is_overstock'] ?? false) ? 'text-warning' : 'text-success') ?>"><?= $product['is_critical'] ? 'Critico' : (($product['is_overstock'] ?? false) ? 'Sobre stock' : 'Saludable') ?></td>
                                    <td class="text-end">
                                        <?php if ($context['canManage']): ?>
                                            <a href="<?= site_url('inventario/movimientos/nuevo?popup=1' . (! empty($companies) ? '&company_id=' . $selectedCompanyId : '') . '&product_id=' . $product['id'] . '&movement_type=ajuste&adjustment_mode=increase&reason=Ajuste%20manual%20de%20stock') ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar stock" data-popup-subtitle="Ajustar stock del producto con trazabilidad." title="Editar stock" aria-label="Editar stock"><i class="bi bi-pencil-square"></i></a>
                                        <?php endif; ?>
                                        <a href="<?= site_url('inventario/productos/' . $product['id'] . '/trazabilidad' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Trazabilidad del producto" data-popup-subtitle="Historial, stock por deposito y responsables." title="Ver trazabilidad" aria-label="Ver trazabilidad"><i class="bi bi-diagram-3"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($products === []): ?><tr><td colspan="9" class="text-secondary">Todavia no hay productos registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <h2 class="h4 mb-3">Reservas activas</h2>
                        <?php foreach ($activeReservations as $row): ?>
                            <div class="border rounded-3 p-3 mb-2">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <strong><?= esc($row['product_name']) ?></strong>
                                        <div class="small text-secondary"><?= esc($row['warehouse_name']) ?> / <?= esc($row['reference'] ?: 'Sin referencia') ?></div>
                                        <div class="small text-secondary">Reservado por <?= esc($row['reserved_by_name']) ?> el <?= esc(date('d/m/Y H:i', strtotime($row['reserved_at']))) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold"><?= number_format((int) $row['quantity'], 0, ',', '.') ?></div>
                                        <?php if ($context['canManage']): ?>
                                            <form method="post" action="<?= site_url('inventario/reservas/' . $row['id'] . '/liberar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="mt-2">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-success icon-btn" title="Liberar reserva" aria-label="Liberar reserva"><i class="bi bi-unlock"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($activeReservations === []): ?><div class="text-secondary">No hay reservas activas.</div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h2 class="h4 mb-1">Depositos</h2>
                                <p class="text-secondary mb-0">Existencias independientes por ubicacion operativa.</p>
                            </div>
                            <?php if ($context['canConfigure']): ?>
                                <a href="<?= site_url('inventario/depositos/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Deposito" data-popup-subtitle="Registrar un deposito operativo para Inventario." title="Nuevo deposito" aria-label="Nuevo deposito"><i class="bi bi-plus-lg"></i></a>
                            <?php endif; ?>
                        </div>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <div class="border rounded-3 p-3 mb-2">
                                <div class="d-flex justify-content-between gap-2">
                                    <div>
                                        <strong><?= esc($warehouse['name']) ?></strong>
                                        <div class="small text-secondary"><?= esc($warehouse['code']) ?> / <?= esc($warehouse['type']) ?></div>
                                    </div>
                                    <span class="small <?= (int) $warehouse['active'] === 1 ? 'text-success' : 'text-danger' ?>"><?= (int) $warehouse['active'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                                </div>
                                <div class="small text-secondary mt-2">Stock: <?= number_format((int) $warehouse['total_stock'], 0, ',', '.') ?> / Productos: <?= esc((string) $warehouse['product_count']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <h2 class="h4 mb-3">Ultimos movimientos</h2>
                        <?php foreach ($recentMovements as $row): ?>
                            <div class="border rounded-3 p-3 mb-2">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between gap-2 mb-1">
                                            <strong><?= esc($row['product_name']) ?></strong>
                                            <span class="small text-secondary text-end"><?= esc(date('d/m/Y', strtotime($row['occurred_at']))) ?><br><?= esc(date('H:i', strtotime($row['occurred_at']))) ?></span>
                                        </div>
                                        <div class="small text-secondary">
                                            <?= esc(ucfirst($row['movement_type'])) ?> / <?= number_format((int) $row['quantity'], 0, ',', '.') ?>
                                            <?php if (! empty($row['source_name'])): ?> / Origen: <?= esc($row['source_name']) ?><?php endif; ?>
                                            <?php if (! empty($row['destination_name'])): ?> / Destino: <?= esc($row['destination_name']) ?><?php endif; ?>
                                        </div>
                                        <div class="small text-secondary">Responsable: <?= esc($row['user_name']) ?><?= ! empty($row['reason']) ? ' / ' . esc($row['reason']) : '' ?></div>
                                    </div>
                                    <a href="<?= site_url('inventario/productos/' . $row['product_id'] . '/trazabilidad' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn flex-shrink-0" data-popup="true" data-popup-title="Trazabilidad del producto" data-popup-subtitle="Historial, stock por deposito y responsables." title="Ver detalle del movimiento" aria-label="Ver detalle del movimiento"><i class="bi bi-diagram-3"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($recentMovements === []): ?><div class="text-secondary">Todavia no hay movimientos registrados.</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
