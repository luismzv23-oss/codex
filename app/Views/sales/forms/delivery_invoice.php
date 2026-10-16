<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$salesLive = false; $requestKey = $input['request_key'] ?? app_uuid();
$selectedDocument = null;
foreach ($documentTypes as $document) {
    if ($document['id'] === ($input['document_type_id'] ?? '')) { $selectedDocument = $document; break; }
}
$validPoints = $selectedDocument ? array_values(array_filter($pointsOfSale, static fn($point) => \App\Libraries\DeliveryNoteInvoicingService::pointSupportsDocument($point, $selectedDocument))) : [];
$selectedPoint = $input['point_of_sale_id'] ?? '';
if (!in_array($selectedPoint, array_column($validPoints, 'id'), true)) $selectedPoint = count($validPoints) === 1 ? $validPoints[0]['id'] : '';
?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<section class="card border-0 rounded-4 delivery-invoice-form"><div class="card-body p-4">
<h2 class="h5"><?= $mode === 'total' ? 'Facturar totalmente' : 'Facturar parcialmente' ?> · <?= esc($note['number']) ?></h2>
<p class="text-secondary"><?= esc($note['customer_name_snapshot'] ?? 'Consumidor Final') ?> · <?= esc($note['billing_status']) ?></p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif; ?>
<?php if ($note['invoice_reason']): ?><div class="alert alert-warning"><?= esc($note['invoice_reason']) ?></div><?php endif; ?>
<form method="post" action="<?= esc($formAction) ?>" data-delivery-invoice>
<?= csrf_field() ?>
<input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
<input type="hidden" name="popup" value="<?= $isPopup ? '1' : '0' ?>">
<input type="hidden" name="request_key" value="<?= esc($requestKey) ?>">
<input type="hidden" name="price_version" value="<?= esc(\App\Libraries\DeliveryNoteInvoicingService::priceVersion($note)) ?>">
<input type="hidden" name="mode" value="<?= esc($mode) ?>">
<?php if ($draft): ?><input type="hidden" name="draft" value="<?= esc($draft['id']) ?>"><div class="alert alert-info">Borrador <?= esc($draft['sale_number']) ?>. Se volverán a validar las cantidades al confirmar.</div><?php endif; ?>
<div class="row g-3 mb-4">
<label class="col-md-6">Tipo de factura<select name="document_type_id" class="form-select" required data-delivery-document>
<option value="">Selecciona un comprobante</option>
<?php foreach ($documentTypes as $doc): ?>
<option value="<?= esc($doc['id']) ?>" data-channel="<?= esc($doc['channel'] ?? 'standard') ?>" <?= ($input['document_type_id'] ?? '') === $doc['id'] ? 'selected' : '' ?> <?= $draft && $draft['document_type_id'] !== $doc['id'] ? 'disabled' : '' ?>><?= esc($doc['name']) ?></option>
<?php endforeach; ?></select></label>
<label class="col-md-6">Punto de venta<select name="point_of_sale_id" class="form-select" required data-delivery-point <?= !$pointsOfSale ? 'disabled' : '' ?> aria-describedby="delivery-point-hint">
<option value="">Selecciona un punto de venta</option>
<?php foreach ($pointsOfSale as $point):
$compatible = $selectedDocument && \App\Libraries\DeliveryNoteInvoicingService::pointSupportsDocument($point, $selectedDocument);
$pointLabel = implode(' · ', array_filter([$point['code'] ?? '', $point['name'], !empty($point['afip_pos_number']) ? 'PV ' . str_pad((string) $point['afip_pos_number'], 5, '0', STR_PAD_LEFT) : '']));
?>
<option value="<?= esc($point['id']) ?>" data-channel="<?= esc($point['channel'] ?? 'standard') ?>" data-document="<?= esc($point['document_type_id'] ?? '') ?>" data-label="<?= esc($pointLabel) ?>" <?= $selectedPoint === $point['id'] ? 'selected' : '' ?> <?= !$compatible ? 'disabled' : '' ?>><?= esc($pointLabel . ($selectedDocument && !$compatible ? ' — No compatible con esta factura' : '')) ?></option>
<?php endforeach; ?>
</select><span class="small text-secondary" id="delivery-point-hint" data-delivery-point-hint aria-live="polite"><?= !$selectedDocument ? 'Primero selecciona el tipo de factura.' : (!$validPoints ? 'No hay un punto de venta activo compatible. Revisa la configuración de puntos de venta.' : 'Puntos activos registrados en Configuración de Ventas. Los incompatibles se muestran deshabilitados.') ?></span></label>
<?php if ($note['origin'] === 'cycle'): ?>
<label class="col-md-5">Lista de precios<select name="price_list_id" class="form-select">
<option value="">Precio de venta del producto</option>
<?php foreach ($priceLists as $list): ?><option value="<?= esc($list['id']) ?>" <?= ($input['price_list_id'] ?? '') === $list['id'] ? 'selected' : '' ?>><?= esc($list['name']) ?></option><?php endforeach; ?>
</select></label>
<label class="col-md-4">Impuesto<select name="tax_id" class="form-select" required>
<?php foreach ($taxes as $tax): ?><option value="<?= esc($tax['id']) ?>" <?= (($input['tax_id'] ?? '') === $tax['id'] || (empty($input['tax_id']) && !empty($tax['is_default']))) ? 'selected' : '' ?>><?= esc($tax['name']) ?> (<?= esc($tax['rate']) ?> %)</option><?php endforeach; ?>
</select></label>
<div class="col-md-3 align-self-end"><button class="btn btn-outline-secondary icon-btn" type="submit" formmethod="get" formnovalidate title="Actualizar precios" aria-label="Actualizar precios"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button></div>
<p class="small text-secondary">Después de cambiar la lista o el impuesto, pulsa Actualizar precios para revisar los nuevos importes.</p>
<?php else: ?><p class="small text-secondary">Se conservan los importes del remito, distribuyendo sus descuentos y recargos entre los renglones.</p><?php endif; ?>
</div>
<div class="table-responsive"><table class="table align-middle">
<thead><tr><th>Producto</th><th>Remitido</th><th>Facturado</th><th>Pendiente</th><th>A facturar</th><th>Precio unitario final</th><th>IVA %</th><th>Importe</th></tr></thead>
<tbody>
<?php foreach ($note['items'] as $item):
$quantity = $mode === 'total' ? $item['pending_quantity'] : ($input['quantities'][$item['id']] ?? 0);
$originalUnits = max(1, \App\Libraries\DeliveryNoteService::units($item['quantity']));
?>
<tr data-delivery-line data-original="<?= esc($originalUnits) ?>" data-used="<?= esc(\App\Libraries\DeliveryNoteService::units($item['invoiced_quantity'])) ?>" data-cents="<?= esc((int) round(($item['billing_line_total'] ?? 0) * 100)) ?>" data-tax="<?= esc($item['tax_rate'] ?? 0) ?>">
<td><?= esc(($item['sku'] ?? '') . ' · ' . $item['product_name']) ?></td>
<td><?= esc($item['quantity']) ?></td><td><?= esc($item['invoiced_quantity']) ?></td><td><?= esc($item['pending_quantity']) ?></td>
<td><input type="number" class="form-control" style="min-width:100px" name="quantities[<?= esc($item['id']) ?>]" value="<?= esc($quantity) ?>" min="0" max="<?= esc($item['pending_quantity']) ?>" step="0.0001" <?= $mode === 'total' ? 'readonly' : '' ?> data-delivery-quantity></td>
<td><?= number_format($item['billing_price'] ?? 0, 4, ',', '.') ?></td><td><?= esc($item['tax_rate'] ?? 0) ?></td><td data-delivery-amount></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="text-end mb-3" aria-live="polite"><div>Neto: <strong data-delivery-net></strong></div><div>IVA: <strong data-delivery-tax></strong></div><div>Total <?= esc($note['currency_code'] ?? 'ARS') ?>: <strong data-delivery-total></strong></div></div>
<p class="small text-secondary">Esta operación no repite la salida de stock del remito. La factura se genera a cuenta corriente; los cobros se registran desde Cobranzas.</p>
<div class="d-flex gap-2 justify-content-end">
<button class="btn btn-outline-dark icon-btn" name="operation" value="draft" title="Guardar borrador" aria-label="Guardar borrador" <?= $note['invoice_reason'] ? 'disabled' : '' ?>><i class="bi bi-floppy" aria-hidden="true"></i></button>
<button class="btn btn-dark icon-btn" name="operation" value="confirm" title="Confirmar factura" aria-label="Confirmar factura" <?= $note['invoice_reason'] ? 'disabled' : '' ?>><i class="bi bi-check-lg" aria-hidden="true"></i></button>
</div>
</form>
<?= view('sales/delivery_invoice_history', ['note' => $note, 'companyId' => $companyId]) ?>
</div></section>
<script src="<?= base_url('assets/js/delivery-invoice.js') ?>"></script>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
