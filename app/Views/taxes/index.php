<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('taxes/header', get_defined_vars()) ?>
<?= view('taxes/overview', get_defined_vars()) ?>
<!-- Libro IVA Ventas -->
<div id="tax-sales" class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header taxes-list-heading"><h2>Libro IVA Ventas</h2><div class="taxes-list-controls"></div></div>
    <div class="card-body p-0">
        <table data-tax-table class="table table-hover table-sm align-middle mb-0">
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
                            <td><code class="small"><?= esc($r['cae'] ?? '-') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr data-tax-total class="fw-bold"><td colspan="5">Totales</td><td class="text-end"><?= number_format((float)($ivaVentas['totals']['neto_gravado'] ?? 0), 2, ',', '.') ?></td><td class="text-end"><?= number_format((float)($ivaVentas['totals']['iva'] ?? 0), 2, ',', '.') ?></td><td class="text-end"><?= number_format((float)($ivaVentas['totals']['total'] ?? 0), 2, ',', '.') ?></td><td></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Libro IVA Compras -->
<div id="tax-purchases" class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header taxes-list-heading"><h2>Libro IVA Compras</h2><div class="taxes-list-controls"></div></div>
    <div class="card-body p-0">
        <table data-tax-table class="table table-hover table-sm align-middle mb-0">
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
                    <tr data-tax-total class="fw-bold"><td colspan="5">Totales</td><td class="text-end"><?= number_format((float)($ivaCompras['totals']['neto_gravado'] ?? 0), 2, ',', '.') ?></td><td class="text-end"><?= number_format((float)($ivaCompras['totals']['iva'] ?? 0), 2, ',', '.') ?></td><td class="text-end"><?= number_format((float)($ivaCompras['totals']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SICORE -->
<div id="tax-sicore" class="card border-0 shadow-sm rounded-4">
    <div class="card-header taxes-list-heading"><h2>SICORE: Retenciones y percepciones</h2><div class="taxes-list-controls"></div></div>
    <div class="card-body p-0">
        <table data-tax-table class="table table-hover table-sm align-middle mb-0">
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

</div></div>
<script src="<?= base_url('assets/js/taxes-dashboard.js') ?>"></script>
<?= $this->endSection() ?>
