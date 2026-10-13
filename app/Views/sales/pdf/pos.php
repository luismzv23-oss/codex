<?php
$cfg = $ticketSettings ?? [];
$isDeliveryNote = ($documentType['category'] ?? '') === 'delivery_note' || in_array(strtoupper($documentType['code'] ?? $sale['document_code'] ?? ''), ['REMITO','RTO','RM'], true);
if (empty($preview) && !empty($fiscal['cae'])) {
    $cfg['ticket_show_qr'] = 1;
    $cfg['ticket_show_authorization'] = 1;
}
$flag = static fn ($key) => 'data-pos-block="'.$key.'"'.((int)($cfg['ticket_'.$key] ?? 1) === 1 ? '' : ' style="display:none"');
$money = static fn ($amount) => number_format((float)$amount, 2, ',', '.');
$value = static fn ($key, $fallback = '') => (string)($cfg['ticket_'.$key] ?? $fallback);
$date = static fn ($input) => $input && strtotime($input) !== false ? date('d/m/Y', strtotime($input)) : '-';
$fiscal = $fiscal ?? [];
$payments = array_values(array_filter($payments ?? [], static fn ($p) => ($p['status'] ?? '') !== 'reversed'));
$visiblePayments = array_values(array_filter($payments, static fn ($payment) => (int)($payment['show_on_receipt'] ?? 1) === 1));
$taxes = [];
foreach ($items as $item) { $rate = $money($item['tax_rate'] ?? 0); $taxes[$rate] = ($taxes[$rate] ?? 0) + (float)($item['tax_total'] ?? 0); }
$fonts = ['Courier'=>'Courier','DejaVu Sans'=>'DejaVu Sans','DejaVu Serif'=>'DejaVu Serif','Helvetica'=>'Helvetica','Helvetica 75 Bold'=>'Helvetica','Times-Roman'=>'Times-Roman'];
$font = $fonts[$value('font_family','Courier')] ?? 'Courier';
$fontSize = ['small'=>9,'medium'=>11,'large'=>13][$value('font_size','medium')] ?? 11;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Comprobante POS</title>
<style>
@page{margin:12mm}*{box-sizing:border-box}body{font-family:'<?= $font ?>',monospace;font-size:<?= $fontSize ?>px;color:#111;margin:0}table{width:100%;border-collapse:collapse}td{vertical-align:top;padding:6px}h1{font-size:17px;margin:12px 0}p{margin:5px 0}.frame{border:1px solid #aaa}.header{border-bottom:1px solid #aaa}.header td{padding:14px 10px}.brand{width:40%;text-align:center}.badge{width:12%;text-align:center}.badge strong{display:block;background:#e1e1e1;border-radius:5px;font-size:30px;margin:5px auto;padding:5px}.document{width:48%;font-size:.95em}.client{padding:12px 8px;border-bottom:1px solid #aaa}.client td{width:50%;padding:3px 5px}.items th{border-bottom:1px solid #999;border-top:1px solid #999;font-size:.8em;text-align:left;padding:7px 5px}.items td{padding:8px 5px;overflow-wrap:anywhere}.items thead{display:table-header-group}.items tr{page-break-inside:avoid}.right{text-align:right!important}.items-region{min-height:240px;padding:0 7px}.muted{font-size:.85em;color:#555}.bottom{border-top:1px solid #aaa;padding:8px;page-break-inside:avoid}.bottom>table>tbody>tr>td{width:50%;padding:0 5px;vertical-align:top}.totals{background:#e1e1e1}.totals td{padding:5px 6px}.grand td{border-top:1px solid #777;font-weight:bold;padding:9px 6px}.qr{width:34mm;height:34mm}.foot{padding:8px;border-top:1px solid #aaa;white-space:pre-line;overflow-wrap:anywhere}.fiscal{font-size:.9em}.fiscal p{margin:4px 0}.preview-label{font-size:9px;text-align:center;color:#555}.payments td{padding:4px}.notes{min-height:50px}.preserve{white-space:pre-line}.bold{font-weight:bold}
</style></head><body>
<?php if (!empty($preview)): ?><div class="preview-label">VISTA PREVIA · DATOS DE EJEMPLO · SIN VALIDEZ FISCAL</div><?php endif; ?>
<div class="frame">
<table class="header"><tr>
<td class="brand">
    <div <?= $flag('show_header') ?>><h1 data-pos-text="header_title"><?= esc($value('header_title') ?: ($company['legal_name'] ?? $company['name'])) ?></h1></div>
    <div <?= $flag('show_subtitle') ?> data-pos-text="company_subtitle"><?= esc($value('company_subtitle')) ?></div>
    <div <?= $flag('show_address') ?>><p data-pos-text="company_address"><?= esc($value('company_address') ?: ($company['address'] ?? '')) ?></p><p data-pos-text="company_phone"><?= esc($value('company_phone') ?: ($company['phone'] ?? '')) ?></p></div>
    <div <?= $flag('show_custom_header') ?>><p class="preserve" data-pos-text="custom_text_top_left" data-pos-bold="bold_top_left" style="font-weight:<?= (int)$value('bold_top_left',1) ? 'bold' : 'normal' ?>"><?= esc($value('custom_text_top_left')) ?></p></div>
</td>
<td class="badge"><div <?= $flag('show_document') ?>>Original<strong data-pos-letter><?= esc($documentType['letter'] ?? 'X') ?></strong><span data-pos-code><?= esc($fiscal['documentTypeCode'] ?? '') ?></span></div></td>
<td class="document">
    <div <?= $flag('show_document') ?>><b data-pos-document><?= esc($documentType['name'] ?? 'COMPROBANTE') ?>:</b> <?= esc($fiscal['documentNumber'] ?? $sale['sale_number'] ?? '') ?></div>
    <div <?= $flag('show_dates') ?>><p><b>Fecha de emisión:</b> <?= $date($sale['issue_date'] ?? null) ?></p><p><b>Fecha de vencimiento:</b> <?= $date($sale['due_date'] ?? null) ?></p></div>
    <div <?= $flag('show_tax_id') ?>><p><b>CUIT:</b> <?= esc($fiscal['taxId'] ?? $company['tax_id'] ?? '') ?></p></div>
    <div <?= $flag('show_custom_header') ?>><p class="preserve" data-pos-text="custom_text_top_right" data-pos-bold="bold_top_right" style="font-weight:<?= (int)$value('bold_top_right',0) ? 'bold' : 'normal' ?>"><?= esc($value('custom_text_top_right')) ?></p></div>
</td></tr></table>
<div class="client">
    <div <?= $flag('show_customer') ?>><table><tr><td><b>Nombre:</b> <?= esc($sale['customer_name_snapshot'] ?? $customer['billing_name'] ?? $customer['name'] ?? 'Consumidor Final') ?></td><td><b>IVA:</b> <?= esc($sale['customer_tax_profile'] ?? $customer['tax_profile'] ?? '-') ?></td></tr>
    <tr><td><b>CUIT/DNI:</b> <?= esc($sale['customer_document_snapshot'] ?? $customer['document_number'] ?? '-') ?></td><td><b>Tel.:</b> <?= esc($customer['phone'] ?? '-') ?></td></tr>
    <tr><td colspan="2"><b>Dirección:</b> <?= esc($customer['address'] ?? '-') ?></td></tr></table></div>
    <p <?= $flag('show_condition') ?>><b>Condición de venta:</b> <?= esc($conditionName ?? '-') ?></p>
    <p <?= $flag('show_user') ?>><b>Vendedor:</b> <?= esc($creatorName ?? '-') ?></p>
</div>
<div class="items-region"><table class="items"><thead><tr>
<th <?= $flag('show_sku') ?>>SKU</th><th>Descripción</th><th <?= $flag('show_quantity') ?>>Cantidad</th><?php if (!$isDeliveryNote): ?><th <?= $flag('show_unit_price') ?>>Precio unitario</th><th>Importe</th><th <?= $flag('show_item_tax') ?>>IVA %</th><th <?= $flag('show_item_discount') ?>>Desc. %</th><?php endif; ?>
</tr></thead><tbody>
<?php foreach ($items as $item): ?><tr>
<td <?= $flag('show_sku') ?>><?= esc($item['sku'] ?? '') ?></td>
<td><?= esc($item['product_name']) ?><div <?= $flag('show_brand') ?> class="muted"><?= esc($item['brand'] ?? '') ?></div><?php if (!$isDeliveryNote): ?><div <?= $flag('show_item_breakdown') ?> class="muted"><?= $money($item['quantity']) ?> x <?= $money($item['unit_price']) ?></div><?php endif; ?></td>
<td <?= $flag('show_quantity') ?>><?= $money($item['quantity']) ?></td><?php if (!$isDeliveryNote): ?><td <?= $flag('show_unit_price') ?>><?= $money($item['unit_price']) ?></td><td><?= $money($item['line_total']) ?></td><td <?= $flag('show_item_tax') ?>><?= $money($item['tax_rate'] ?? 0) ?></td><td <?= $flag('show_item_discount') ?>><?= $money($item['discount_rate'] ?? 0) ?></td><?php endif; ?>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php if (!$isDeliveryNote): ?><div class="bottom"><table><tr><td class="fiscal">
    <div <?= $flag('show_qr') ?>><?php if (!empty($qrDataUri)): ?><img class="qr" src="<?= esc($qrDataUri,'attr') ?>" alt="QR fiscal"><?php else: ?><p class="muted">QR no disponible: requiere autorización y datos fiscales completos.</p><?php endif; ?></div>
    <div <?= $flag('show_authorization') ?>><?php if (!empty($fiscal['cae'])): ?><p><b>CAE:</b> <?= esc($fiscal['cae']) ?></p><p><b>Vencimiento CAE:</b> <?= $date($fiscal['caeDueDate'] ?? null) ?></p><?php if (!empty($fiscal['testEnvironment'])): ?><p>HOMOLOGACIÓN · SIN VALIDEZ FISCAL</p><?php endif; ?><?php else: ?><p>SIN AUTORIZACIÓN FISCAL DISPONIBLE</p><?php endif; ?></div>
</td><td><table class="totals">
    <tr <?= $flag('show_subtotal') ?>><td>Subtotal neto</td><td class="right"><?= $money($sale['subtotal'] ?? 0) ?></td></tr>
    <?php foreach ($taxes as $rate=>$amount): ?><tr <?= $flag('show_taxes') ?>><td>IVA <?= esc($rate) ?> %</td><td class="right"><?= $money($amount) ?></td></tr><?php endforeach; ?>
    <tr <?= $flag('show_discounts') ?>><td>Descuentos de productos</td><td class="right"><?= $money($sale['item_discount_total'] ?? 0) ?></td></tr>
    <tr <?= $flag('show_discounts') ?>><td>Descuento general</td><td class="right"><?= $money($sale['global_discount_total'] ?? 0) ?></td></tr>
    <tr class="grand"><td>Importe total</td><td class="right"><?= $money($sale['total']) ?></td></tr>
</table><p <?= $flag('show_currency') ?> class="right">Moneda: <?= esc($sale['currency_code']) ?></p></td></tr></table></div>
<?php if ($visiblePayments): ?><div <?= $flag('show_payments') ?>><table class="payments"><tr><td><b>Medio de pago</b></td><td class="right"><b>Importe</b></td></tr><?php foreach ($visiblePayments as $payment): ?><tr><td><?= esc($payment['payment_method_code'] ?? $payment['payment_method'] ?? '') ?><?= ($payment['status'] ?? '') === 'pending' ? ' (pendiente)' : '' ?></td><td class="right"><?= $money($payment['amount']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php endif; ?>
<div <?= $flag('show_notes') ?>><div class="foot notes"><b>Observaciones:</b> <?= esc($sale['notes'] ?? '') ?></div></div>
<div <?= $flag('show_footer') ?>><div class="foot" data-pos-text="footer_notes"><?= esc($value('footer_notes')) ?></div></div>
<div <?= $flag('show_custom_footer') ?>><div class="foot"><table><tr><td data-pos-text="custom_text_bottom_left"><?= esc($value('custom_text_bottom_left')) ?></td><td class="right" data-pos-text="custom_text_bottom_right"><?= esc($value('custom_text_bottom_right')) ?></td></tr></table></div></div>
</div></body></html>
