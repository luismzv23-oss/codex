<?php if (!empty($note['invoices']) || !empty($note['legacy'])): ?>
<hr><h3 class="h6">Comprobantes vinculados</h3>
<ul class="mb-0">
<?php foreach ($note['invoices'] as $invoice): ?>
<li><?= esc($invoice['sale_number']) ?> · <?= esc(['draft' => 'Borrador', 'confirmed' => 'Confirmada', 'cancelled' => 'Anulada', 'returned_partial' => 'Devuelta parcialmente', 'returned_total' => 'Devuelta totalmente'][$invoice['status']] ?? $invoice['status']) ?>
<?php if ($invoice['status'] === 'draft'): ?>
<a href="<?= esc(site_url('ventas/remitos/' . $note['origin'] . '/' . $note['id'] . '/facturar') . '?' . http_build_query(['company_id' => $companyId, 'draft' => $invoice['invoice_id'], 'popup' => service('request')->getGet('popup') === '1' ? '1' : '0'])) ?>">Continuar borrador</a>
<?php else: ?><a href="<?= esc(site_url('ventas/' . $invoice['invoice_id']) . '?company_id=' . rawurlencode($companyId)) ?>">Ver comprobante</a><?php endif; ?>
</li>
<?php endforeach; ?>
<?php foreach ($note['legacy'] as $invoice): ?><li><?= esc($invoice['sale_number'] ?? $invoice['id']) ?> · Requiere revisión de trazabilidad anterior.</li><?php endforeach; ?>
</ul>
<?php endif; ?>
