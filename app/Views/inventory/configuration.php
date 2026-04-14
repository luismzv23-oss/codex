<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Configuracion de Inventario</h1>
        <p class="text-secondary mb-0">Parametros del sistema, depositos operativos y base de productos por empresa.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (! empty($companies)): ?>
            <form method="get" action="<?= site_url('inventario/configuracion') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
        <a href="<?= site_url('inventario' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Volver</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Parametros del sistema</h2>
                        <p class="text-secondary mb-0"><?= esc($context['company']['name']) ?></p>
                    </div>
                    <a href="<?= site_url('inventario/configuracion/parametros/editar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Parametros de inventario" data-popup-subtitle="Configurar alertas, umbrales y politicas del sistema." title="Editar parametros" aria-label="Editar parametros"><i class="bi bi-sliders"></i></a>
                </div>
                <div class="border rounded-4 p-3 mb-3"><div class="small text-secondary">Email de alertas</div><div class="fw-semibold"><?= esc($settings['alert_email'] ?: '-') ?></div></div>
                <div class="border rounded-4 p-3 mb-3"><div class="small text-secondary">Umbral de movimiento inusual</div><div class="fw-semibold"><?= number_format((float) ($settings['unusual_movement_threshold'] ?? 0), 2, ',', '.') ?></div></div>
                <div class="border rounded-4 p-3 mb-3"><div class="small text-secondary">Dias sin rotacion</div><div class="fw-semibold"><?= esc((string) ($settings['no_rotation_days'] ?? 30)) ?></div></div>
                <div class="border rounded-4 p-3 mb-3"><div class="small text-secondary">Metodo de costeo</div><div class="fw-semibold"><?=
                    esc(match ($settings['valuation_method'] ?? 'weighted_average') {
                        'fifo' => 'FIFO',
                        'lifo' => 'LIFO',
                        default => 'Promedio ponderado',
                    })
                ?></div></div>
                <div class="small text-secondary">
                    Stock negativo: <?= (int) ($settings['allow_negative_stock'] ?? 0) === 1 ? 'Permitido' : 'Bloqueado' ?><br>
                    Alcance: <?= esc($settings['negative_stock_scope'] ?? 'global') ?><br>
                    Ventas: <?= (int) ($settings['allow_negative_on_sales'] ?? 0) === 1 ? 'Permitido' : 'Bloqueado' ?><br>
                    Transferencias: <?= (int) ($settings['allow_negative_on_transfers'] ?? 0) === 1 ? 'Permitido' : 'Bloqueado' ?><br>
                    Ajustes: <?= (int) ($settings['allow_negative_on_adjustments'] ?? 0) === 1 ? 'Permitido' : 'Bloqueado' ?><br>
                    Alertas internas: <?= (int) ($settings['internal_notifications'] ?? 0) === 1 ? 'Activas' : 'Inactivas' ?><br>
                    Alertas por email: <?= (int) ($settings['email_notifications'] ?? 0) === 1 ? 'Activas' : 'Inactivas' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Depositos</h2>
                        <p class="text-secondary mb-0">Estructura fisica y operativa del inventario por empresa.</p>
                    </div>
                    <a href="<?= site_url('inventario/depositos/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo deposito" data-popup-subtitle="Registrar un deposito operativo del sistema." title="Nuevo deposito" aria-label="Nuevo deposito"><i class="bi bi-plus-lg"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Deposito</th><th>Sucursal</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <tr>
                                    <td><?= esc($warehouse['name']) ?> <span class="small text-secondary"><?= esc($warehouse['code']) ?></span></td>
                                    <td><?= esc($warehouse['branch_name'] ?: '-') ?></td>
                                    <td><?= esc($warehouse['type']) ?><?= (int) $warehouse['is_default'] === 1 ? ' / Base' : '' ?></td>
                                    <td><?= (int) $warehouse['active'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                                    <td class="text-end">
                                        <a href="<?= site_url('inventario/depositos/' . $warehouse['id'] . '/editar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Deposito" data-popup-subtitle="Editar datos operativos del deposito." title="Editar deposito" aria-label="Editar deposito"><i class="bi bi-pencil-square"></i></a>
                                        <form method="post" action="<?= site_url('inventario/depositos/' . $warehouse['id'] . '/toggle' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm <?= (int) $warehouse['active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?> icon-btn" title="<?= (int) $warehouse['active'] === 1 ? 'Deshabilitar deposito' : 'Habilitar deposito' ?>" aria-label="<?= (int) $warehouse['active'] === 1 ? 'Deshabilitar deposito' : 'Habilitar deposito' ?>"><i class="bi <?= (int) $warehouse['active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i></button>
                                        </form>
                                        <form method="post" action="<?= site_url('inventario/depositos/' . $warehouse['id'] . '/eliminar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline" onsubmit="return confirm('Se eliminara el deposito. Deseas continuar?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar deposito" aria-label="Eliminar deposito"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($warehouses === []): ?><tr><td colspan="5" class="text-secondary">No hay depositos registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Ubicaciones internas</h2>
                        <p class="text-secondary mb-0">Sectores, racks y niveles dentro de cada deposito.</p>
                    </div>
                    <a href="<?= site_url('inventario/ubicaciones/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Ubicacion interna" data-popup-subtitle="Registrar una ubicacion operativa para el deposito." title="Nueva ubicacion" aria-label="Nueva ubicacion"><i class="bi bi-plus-lg"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Ubicacion</th><th>Deposito</th><th>Zona/Rack/Nivel</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($locations as $location): ?>
                                <tr>
                                    <td><?= esc($location['name']) ?> <span class="small text-secondary"><?= esc($location['code']) ?></span></td>
                                    <td><?= esc($location['warehouse_name']) ?> <span class="small text-secondary"><?= esc($location['warehouse_code']) ?></span></td>
                                    <td><?= esc(trim(($location['zone'] ?: '-') . ' / ' . ($location['rack'] ?: '-') . ' / ' . ($location['level'] ?: '-'))) ?></td>
                                    <td><?= (int) $location['active'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                                    <td class="text-end">
                                        <a href="<?= site_url('inventario/ubicaciones/' . $location['id'] . '/editar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Ubicacion interna" data-popup-subtitle="Editar datos de la ubicacion." title="Editar ubicacion" aria-label="Editar ubicacion"><i class="bi bi-pencil-square"></i></a>
                                        <form method="post" action="<?= site_url('inventario/ubicaciones/' . $location['id'] . '/toggle' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm <?= (int) $location['active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?> icon-btn" title="<?= (int) $location['active'] === 1 ? 'Deshabilitar ubicacion' : 'Habilitar ubicacion' ?>" aria-label="<?= (int) $location['active'] === 1 ? 'Deshabilitar ubicacion' : 'Habilitar ubicacion' ?>"><i class="bi <?= (int) $location['active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i></button>
                                        </form>
                                        <form method="post" action="<?= site_url('inventario/ubicaciones/' . $location['id'] . '/eliminar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline" onsubmit="return confirm('Se eliminara la ubicacion. Deseas continuar?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar ubicacion" aria-label="Eliminar ubicacion"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($locations === []): ?><tr><td colspan="5" class="text-secondary">No hay ubicaciones internas registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Reservas activas</h2>
                        <p class="text-secondary mb-0">Stock comprometido por producto y deposito, con referencia operativa.</p>
                    </div>
                    <a href="<?= site_url('inventario/reservas/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Reserva de stock" data-popup-subtitle="Comprometer existencias por deposito." title="Nueva reserva" aria-label="Nueva reserva"><i class="bi bi-plus-lg"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Producto</th><th>Deposito</th><th>Cantidad</th><th>Referencia</th><th>Responsable</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?= esc($reservation['product_name']) ?> <span class="small text-secondary"><?= esc($reservation['sku']) ?></span></td>
                                    <td><?= esc($reservation['warehouse_name']) ?> <span class="small text-secondary"><?= esc($reservation['warehouse_code']) ?></span></td>
                                    <td><?= number_format((float) $reservation['quantity'], 2, ',', '.') ?></td>
                                    <td><?= esc($reservation['reference'] ?: '-') ?></td>
                                    <td><?= esc($reservation['reserved_by_name']) ?><div class="small text-secondary"><?= esc(date('d/m/Y H:i', strtotime($reservation['reserved_at']))) ?></div></td>
                                    <td class="text-end">
                                        <form method="post" action="<?= site_url('inventario/reservas/' . $reservation['id'] . '/liberar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-success icon-btn" title="Liberar reserva" aria-label="Liberar reserva"><i class="bi bi-unlock"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($reservations === []): ?><tr><td colspan="6" class="text-secondary">No hay reservas activas registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Productos inventariables</h2>
                        <p class="text-secondary mb-0">Catalogo operativo con minimos, stock consolidado y trazabilidad por producto.</p>
                    </div>
                    <a href="<?= site_url('inventario/productos/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Producto" data-popup-subtitle="Registrar un producto para control de stock." title="Nuevo producto" aria-label="Nuevo producto"><i class="bi bi-plus-lg"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>SKU</th><th>Producto</th><th>Clasificacion</th><th>Unidad</th><th>Min/Max</th><th>Stock</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= esc($product['sku']) ?></td>
                                    <td><?= esc($product['name']) ?></td>
                                    <td><?= esc(trim(($product['category'] ?? '-') . ' / ' . ($product['brand'] ?? '-'))) ?><div class="small text-secondary"><?= esc($product['product_type'] ?? 'simple') ?><?= ! empty($product['barcode']) ? ' / ' . esc($product['barcode']) : '' ?><?= (int) ($product['lot_control'] ?? 0) === 1 ? ' / Lote' : '' ?><?= (int) ($product['serial_control'] ?? 0) === 1 ? ' / Serie' : '' ?><?= (int) ($product['expiration_control'] ?? 0) === 1 ? ' / Vence' : '' ?></div></td>
                                    <td><?= esc($product['unit']) ?></td>
                                    <td><?= number_format((float) $product['min_stock'], 2, ',', '.') ?> / <?= number_format((float) ($product['max_stock'] ?? 0), 2, ',', '.') ?></td>
                                    <td><?= number_format((float) $product['total_stock'], 2, ',', '.') ?><div class="small text-secondary">Disponible: <?= number_format((float) ($product['available_stock'] ?? 0), 2, ',', '.') ?></div></td>
                                    <td><?= (int) $product['active'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                                    <td class="text-end">
                                        <a href="<?= site_url('inventario/productos/' . $product['id'] . '/trazabilidad' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Trazabilidad del producto" data-popup-subtitle="Historial, stock por deposito y responsables." title="Ver trazabilidad" aria-label="Ver trazabilidad"><i class="bi bi-diagram-3"></i></a>
                                        <a href="<?= site_url('inventario/productos/' . $product['id'] . '/editar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Producto" data-popup-subtitle="Editar datos del producto y sus minimos." title="Editar producto" aria-label="Editar producto"><i class="bi bi-pencil-square"></i></a>
                                        <form method="post" action="<?= site_url('inventario/productos/' . $product['id'] . '/toggle' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm <?= (int) $product['active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?> icon-btn" title="<?= (int) $product['active'] === 1 ? 'Deshabilitar producto' : 'Habilitar producto' ?>" aria-label="<?= (int) $product['active'] === 1 ? 'Deshabilitar producto' : 'Habilitar producto' ?>"><i class="bi <?= (int) $product['active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i></button>
                                        </form>
                                        <form method="post" action="<?= site_url('inventario/productos/' . $product['id'] . '/eliminar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline" onsubmit="return confirm('Se eliminara el producto. Deseas continuar?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar producto" aria-label="Eliminar producto"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($products === []): ?><tr><td colspan="8" class="text-secondary">No hay productos registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Capas de costo</h2>
                        <p class="text-secondary mb-0">Base de costeo generada desde ingresos, transferencias y ajustes positivos.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Producto</th><th>Deposito/Ubicacion</th><th>Tipo</th><th>Cantidad</th><th>Costo</th><th>Fecha</th></tr></thead>
                        <tbody>
                            <?php foreach ($costLayers as $layer): ?>
                                <tr>
                                    <td><?= esc($layer['product_name']) ?> <span class="small text-secondary"><?= esc($layer['sku']) ?></span></td>
                                    <td><?= esc($layer['warehouse_name'] ?: '-') ?><div class="small text-secondary"><?= esc($layer['location_name'] ?: 'Sin ubicacion') ?></div></td>
                                    <td><?= esc($layer['layer_type']) ?></td>
                                    <td><?= number_format((float) $layer['quantity'], 2, ',', '.') ?><div class="small text-secondary">Restante: <?= number_format((float) $layer['remaining_quantity'], 2, ',', '.') ?></div></td>
                                    <td><?= number_format((float) $layer['unit_cost'], 4, ',', '.') ?><div class="small text-secondary">Total: <?= number_format((float) $layer['total_cost'], 2, ',', '.') ?></div></td>
                                    <td><?= esc(date('d/m/Y H:i', strtotime($layer['occurred_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($costLayers === []): ?><tr><td colspan="6" class="text-secondary">Todavia no hay capas de costo registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Ensambles y desensambles</h2>
                        <p class="text-secondary mb-0">Transformacion operativa de kits y productos compuestos.</p>
                    </div>
                    <a href="<?= site_url('inventario/ensambles/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Ensamble" data-popup-subtitle="Registrar ensamble o desensamble de stock." title="Nuevo ensamble" aria-label="Nuevo ensamble"><i class="bi bi-boxes"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Numero</th><th>Producto</th><th>Deposito</th><th>Tipo</th><th>Cantidad</th><th>Costo</th></tr></thead>
                        <tbody>
                            <?php foreach ($assemblies as $assembly): ?>
                                <tr>
                                    <td><?= esc($assembly['assembly_number']) ?><div class="small text-secondary"><?= esc(! empty($assembly['issued_at']) ? date('d/m/Y H:i', strtotime($assembly['issued_at'])) : '-') ?></div></td>
                                    <td><?= esc($assembly['product_name']) ?> <span class="small text-secondary"><?= esc($assembly['sku']) ?></span></td>
                                    <td><?= esc($assembly['warehouse_name'] ?: '-') ?></td>
                                    <td><?= esc($assembly['assembly_type']) ?></td>
                                    <td><?= number_format((float) ($assembly['quantity'] ?? 0), 2, ',', '.') ?></td>
                                    <td><?= number_format((float) ($assembly['total_cost'] ?? 0), 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($assemblies === []): ?><tr><td colspan="6" class="text-secondary">Todavia no hay ensambles registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Cierres de periodo</h2>
                        <p class="text-secondary mb-0">Bloqueo operativo y corte interno por deposito o empresa.</p>
                    </div>
                    <a href="<?= site_url('inventario/cierres/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Cierre de inventario" data-popup-subtitle="Registrar un cierre operativo por periodo." title="Nuevo cierre" aria-label="Nuevo cierre"><i class="bi bi-calendar-check"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Periodo</th><th>Deposito</th><th>Rango</th><th>Estado</th></tr></thead>
                        <tbody>
                            <?php foreach ($periodClosures as $closure): ?>
                                <tr>
                                    <td><?= esc($closure['period_code']) ?></td>
                                    <td><?= esc($closure['warehouse_name'] ?: 'Global') ?></td>
                                    <td><?= esc(date('d/m/Y', strtotime($closure['start_date']))) ?> - <?= esc(date('d/m/Y', strtotime($closure['end_date']))) ?></td>
                                    <td><?= esc($closure['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($periodClosures === []): ?><tr><td colspan="4" class="text-secondary">Todavia no hay cierres registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Revalorizaciones</h2>
                        <p class="text-secondary mb-0">Actualizacion del costo operativo por producto y deposito.</p>
                    </div>
                    <a href="<?= site_url('inventario/revalorizaciones/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Revalorizacion" data-popup-subtitle="Actualizar el costo operativo de capas vigentes." title="Nueva revalorizacion" aria-label="Nueva revalorizacion"><i class="bi bi-graph-up-arrow"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Fecha</th><th>Producto</th><th>Deposito</th><th>Costo anterior</th><th>Nuevo costo</th><th>Diferencia</th></tr></thead>
                        <tbody>
                            <?php foreach ($revaluations as $revaluation): ?>
                                <tr>
                                    <td><?= esc(! empty($revaluation['issued_at']) ? date('d/m/Y H:i', strtotime($revaluation['issued_at'])) : '-') ?></td>
                                    <td><?= esc($revaluation['product_name']) ?> <span class="small text-secondary"><?= esc($revaluation['sku']) ?></span></td>
                                    <td><?= esc($revaluation['warehouse_name'] ?: '-') ?></td>
                                    <td><?= number_format((float) ($revaluation['previous_unit_cost'] ?? 0), 4, ',', '.') ?></td>
                                    <td><?= number_format((float) ($revaluation['new_unit_cost'] ?? 0), 4, ',', '.') ?></td>
                                    <td><?= number_format((float) ($revaluation['difference_amount'] ?? 0), 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($revaluations === []): ?><tr><td colspan="6" class="text-secondary">Todavia no hay revalorizaciones registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
