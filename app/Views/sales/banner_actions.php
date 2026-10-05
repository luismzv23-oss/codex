<?php $toolbarCompanyId = (string)($context['company']['id'] ?? $companyId ?? $selectedCompanyId ?? ''); ?>
<?php if (!(($isPopup ?? false) || service('request')->getGet('popup') === '1')): ?>
<div class="sales-hero-actions"><button type="button" class="btn btn-outline-dark icon-btn" data-sales-refresh title="Actualizar resumen" aria-label="Actualizar resumen"><i class="bi bi-arrow-clockwise"></i></button>
        
        <?php $isVendedorAccess = ($user['role_slug'] ?? auth_user()['role_slug'] ?? '') === 'vendedor' || ($context['access_level'] ?? '') === 'vendedor'; ?>
        <?php if (!$isVendedorAccess): ?>
            <a href="<?= site_url('ventas/diarios' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" title="Diarios" aria-label="Diarios"><i class="bi bi-journal-text"></i></a>
            <a href="<?= site_url('ventas/reportes' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" title="Reportes" aria-label="Reportes"><i class="bi bi-graph-up-arrow"></i></a>
            <a href="<?= site_url('ventas/cobranzas' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" title="Cobranzas" aria-label="Cobranzas"><i class="bi bi-cash-coin"></i></a>
        <?php endif; ?>
        <?php if (($context['canManage'] ?? false)): ?>
            <a href="<?= site_url('ventas/vendedores/nuevo' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Vendedor"
                data-popup-subtitle="Registrar responsable comercial." title="Vendedores" aria-label="Vendedores"><i class="bi bi-people"></i></a>
            <a href="<?= site_url('ventas/zonas/nueva' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Zona comercial"
                data-popup-subtitle="Registrar zona comercial." title="Zonas" aria-label="Zonas"><i class="bi bi-geo-alt"></i></a>
            <a href="<?= site_url('ventas/condiciones/nueva' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Condicion comercial"
                data-popup-subtitle="Registrar condicion de venta." title="Condiciones" aria-label="Condiciones"><i class="bi bi-file-earmark-check"></i></a>
        <?php endif; ?>
        <?php if ($isVendedorAccess || ($context['canManage'] ?? false)): ?>
            <a href="<?= site_url('ventas/pos' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" title="POS" aria-label="POS"><i class="bi bi-display"></i></a>
            <a href="<?= site_url('ventas/kiosco' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" title="Kiosco" aria-label="Kiosco"><i class="bi bi-shop"></i></a>
        <?php endif; ?>
        <?php if (($context['canManage'] ?? false)): ?>
            <a href="<?= site_url('ventas/listas-precio/nueva' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Lista de precios"
                data-popup-subtitle="Configurar precios comerciales por producto." title="Lista de precios" aria-label="Lista de precios"><i class="bi bi-tags"></i></a>
            <a href="<?= site_url('ventas/promociones/nueva' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Promocion"
                data-popup-subtitle="Crear promociones comerciales activas." title="Promociones" aria-label="Promociones"><i class="bi bi-percent"></i></a>
            <a href="<?= site_url('ventas/presupuestos/nuevo' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Presupuesto"
                data-popup-subtitle="Crear nuevo presupuesto comercial." title="Nuevo Presupuesto" aria-label="Nuevo Presupuesto"><i class="bi bi-file-earmark-text"></i></a>
            <a href="<?= site_url('ventas/pedidos/nuevo' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Pedido"
                data-popup-subtitle="Crear nueva orden de pedido." title="Nuevo Pedido" aria-label="Nuevo Pedido"><i class="bi bi-cart-check"></i></a>
            <a href="<?= site_url('ventas/remitos/nuevo' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
                class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Remito"
                data-popup-subtitle="Crear nuevo remito de entrega." title="Nuevo Remito" aria-label="Nuevo Remito"><i class="bi bi-truck"></i></a>
        <?php endif; ?>
        <a href="<?= site_url('ventas/clientes/nuevo' . ($toolbarCompanyId !== '' ? '?' . http_build_query(['company_id'=>$toolbarCompanyId]) : '')) ?>"
            class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Cliente"
            data-popup-subtitle="Alta rapida de cliente para ventas." title="Nuevo cliente" aria-label="Nuevo cliente"><i class="bi bi-person-plus"></i></a>
        <?php if (!empty($salesReportExports)): ?>
            <?php $exportQuery = http_build_query(['company_id'=>$toolbarCompanyId] + ($filters ?? [])); ?>
            <a href="<?= site_url('ventas/reportes/csv?' . $exportQuery) ?>" class="btn btn-outline-dark icon-btn" title="Exportar CSV" aria-label="Exportar CSV"><i class="bi bi-filetype-csv"></i></a>
            <a href="<?= site_url('ventas/reportes/pdf?' . $exportQuery) ?>" class="btn btn-outline-dark icon-btn" target="_blank" rel="noopener" title="Exportar PDF" aria-label="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
        <?php endif; ?>
    </div>
<?php endif; ?>
