<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h2 class="h5 mb-0">Impuestos</h2><p class="text-secondary mb-0 small">Libro IVA Digital, SICORE, Retenciones y Percepciones.</p></div>
</div>
<form class="row g-2 mb-4">
    <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($filters['from'] ?? '') ?>"></div>
    <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($filters['to'] ?? '') ?>"></div>
    <div class="col-auto"><button class="btn btn-outline-dark btn-sm"><i class="bi bi-search"></i> Filtrar</button></div>
</form>

<!-- IVA Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 text-center p-3"><div class="text-secondary small">IVA Ventas (Debito)</div><h4 class="text-primary mb-0"><?= number_format((float)($ivaVentas['totals']['iva'] ?? 0), 2, ',', '.') ?></h4><div class="text-secondary small"><?= $ivaVentas['count'] ?? 0 ?> comprobantes</div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 text-center p-3"><div class="text-secondary small">IVA Compras (Credito)</div><h4 class="text-danger mb-0"><?= number_format((float)($ivaCompras['totals']['iva'] ?? 0), 2, ',', '.') ?></h4><div class="text-secondary small"><?= $ivaCompras['count'] ?? 0 ?> comprobantes</div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 text-center p-3"><div class="text-secondary small">Posicion IVA</div><?php $posIva = (float)($ivaVentas['totals']['iva'] ?? 0) - (float)($ivaCompras['totals']['iva'] ?? 0); ?><h4 class="<?= $posIva >= 0 ? 'text-danger' : 'text-success' ?> mb-0"><?= number_format(abs($posIva), 2, ',', '.') ?></h4><div class="text-secondary small"><?= $posIva >= 0 ? 'A pagar' : 'Saldo a favor' ?></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 text-center p-3"><div class="text-secondary small">Retenciones + Percepciones</div><h4 class="text-warning mb-0"><?= number_format((float)($sicoreSummary['withholdings_total'] ?? 0) + (float)($sicoreSummary['perceptions_total'] ?? 0), 2, ',', '.') ?></h4><div class="text-secondary small"><?= ($sicoreSummary['withholdings_count'] ?? 0) + ($sicoreSummary['perceptions_count'] ?? 0) ?> aplicadas</div></div></div>
</div>

<!-- Libro IVA Ventas -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-light rounded-top-4 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-journal-text text-primary"></i> Libro IVA Ventas</span>
        <a href="<?= site_url('impuestos/iva-ventas/txt') ?>?from=<?= esc($filters['from']) ?>&to=<?= esc($filters['to']) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-download"></i> TXT AFIP (RG 4597)</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Tipo</th><th>Nro</th><th>CUIT</th><th>Razon Social</th><th class="text-end">Neto</th><th class="text-end">IVA</th><th class="text-end">Total</th><th>CAE</th></tr></thead>
            <tbody>
                <?php if (empty($ivaVentas['records'])): ?>
                    <tr><td colspan="9" class="text-center text-secondary py-3">Sin comprobantes de venta en el periodo.</td></tr>
                <?php else: ?>
                    <?php foreach ($ivaVentas['records'] as $r): ?>
                        <tr>
                            <td><?= esc($r['fecha'] ?? '') ?></td>
                            <td><span class="badge bg-secondary"><?= esc($r['tipo_cbte'] ?? '') ?></span></td>
                            <td><?= esc($r['punto_venta'] ?? '') ?>-<?= esc($r['numero_cbte'] ?? '') ?></td>
                            <td><?= esc($r['doc_nro'] ?? '') ?></td>
                            <td><?= esc($r['razon_social'] ?? '') ?></td>
                            <td class="text-end"><?= number_format((float)($r['neto_gravado'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float)($r['iva_21'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)($r['total'] ?? 0), 2, ',', '.') ?></td>
                            <td><code class="small"><?= esc(substr($r['cae'] ?? '', 0, 8)) ?>...</code></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-primary fw-bold"><td colspan="5">Totales</td><td class="text-end"><?= number_format((float)($ivaVentas['totals']['neto_gravado'] ?? 0), 2, ',', '.') ?></td><td class="text-end"><?= number_format((float)($ivaVentas['totals']['iva'] ?? 0), 2, ',', '.') ?></td><td class="text-end"><?= number_format((float)($ivaVentas['totals']['total'] ?? 0), 2, ',', '.') ?></td><td></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Libro IVA Compras -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-light rounded-top-4 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-journal-text text-danger"></i> Libro IVA Compras</span>
        <a href="<?= site_url('impuestos/iva-compras/txt') ?>?from=<?= esc($filters['from']) ?>&to=<?= esc($filters['to']) ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-download"></i> TXT AFIP (RG 4597)</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Tipo</th><th>Nro</th><th>CUIT</th><th>Razon Social</th><th class="text-end">Neto</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                <?php if (empty($ivaCompras['records'])): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-3">Sin comprobantes de compra en el periodo.</td></tr>
                <?php else: ?>
                    <?php foreach ($ivaCompras['records'] as $r): ?>
                        <tr>
                            <td><?= esc($r['fecha'] ?? '') ?></td>
                            <td><span class="badge bg-secondary"><?= esc($r['tipo_cbte'] ?? '') ?></span></td>
                            <td><?= esc($r['numero_cbte'] ?? '') ?></td>
                            <td><?= esc($r['doc_nro'] ?? '') ?></td>
                            <td><?= esc($r['razon_social'] ?? '') ?></td>
                            <td class="text-end"><?= number_format((float)($r['neto_gravado'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float)($r['iva_21'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)($r['total'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-danger fw-bold"><td colspan="5">Totales</td><td class="text-end"><?= number_format((float)($ivaCompras['totals']['neto_gravado'] ?? 0), 2, ',', '.') ?></td><td class="text-end"><?= number_format((float)($ivaCompras['totals']['iva'] ?? 0), 2, ',', '.') ?></td><td class="text-end"><?= number_format((float)($ivaCompras['totals']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SICORE -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-light rounded-top-4 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-file-earmark-text text-warning"></i> SICORE — Retenciones y Percepciones</span>
        <div>
            <a href="<?= site_url('impuestos/sicore/retenciones/txt') ?>?from=<?= esc($filters['from']) ?>&to=<?= esc($filters['to']) ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-download"></i> Retenciones TXT</a>
            <a href="<?= site_url('impuestos/sicore/percepciones/txt') ?>?from=<?= esc($filters['from']) ?>&to=<?= esc($filters['to']) ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-download"></i> Percepciones TXT</a>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Tipo</th><th>Impuesto</th><th>Nombre</th><th>Certificado</th><th class="text-end">Base</th><th class="text-end">Tasa</th><th class="text-end">Monto</th></tr></thead>
            <tbody>
                <?php $allItems = array_merge($sicoreSummary['withholdings'] ?? [], $sicoreSummary['perceptions'] ?? []); ?>
                <?php if (empty($allItems)): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-3">Sin retenciones ni percepciones en el periodo.</td></tr>
                <?php else: ?>
                    <?php foreach ($allItems as $item): ?>
                        <tr>
                            <td><?= esc(substr($item['applied_at'] ?? '', 0, 10)) ?></td>
                            <td><span class="badge bg-<?= isset($item['withholding_id']) ? 'info' : 'success' ?>"><?= isset($item['withholding_id']) ? 'Retencion' : 'Percepcion' ?></span></td>
                            <td><?= esc($item['tax_type'] ?? '') ?></td>
                            <td><?= esc($item['withholding_name'] ?? $item['perception_name'] ?? '') ?></td>
                            <td><code><?= esc($item['certificate_number'] ?? '-') ?></code></td>
                            <td class="text-end"><?= number_format((float)($item['base_amount'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float)($item['rate'] ?? 0), 2) ?>%</td>
                            <td class="text-end fw-semibold"><?= number_format((float)($item['amount'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
