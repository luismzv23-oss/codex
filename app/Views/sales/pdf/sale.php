<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1, h2, h3 { margin: 0 0 8px; }
        .header, .section { margin-bottom: 18px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px 6px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; }
        .totals td { border: 0; }
        .right { text-align: right; }
        .pill { display: inline-block; padding: 2px 8px; border: 1px solid #d1d5db; border-radius: 999px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Comprobante de venta</h1>
        <div class="muted"><?= esc($company['name'] ?? 'Empresa') ?></div>
        <div class="muted">Generado el <?= esc($generatedAt ?? '') ?></div>
    </div>

    <div class="section">
        <table>
            <tr>
                <td><strong>Comprobante</strong><br><?= esc(trim((string) (($sale['document_code'] ?? 'VENTA') . ' ' . ($sale['sale_number'] ?? '-')))) ?></td>
                <td><strong>Punto de venta</strong><br><?= esc($sale['point_of_sale_name'] ?? '-') ?></td>
                <td><strong>Fecha</strong><br><?= esc(! empty($sale['issue_date']) ? date('d/m/Y H:i', strtotime($sale['issue_date'])) : '-') ?></td>
                <td><strong>Estado</strong><br><span class="pill"><?= esc($sale['status'] ?? '-') ?></span></td>
                <td><strong>Pago</strong><br><span class="pill"><?= esc($sale['payment_status'] ?? '-') ?></span></td>
            </tr>
            <tr>
                <td colspan="2"><strong>Cliente</strong><br><?= esc($sale['customer_name_snapshot'] ?? $customer['billing_name'] ?? $customer['name'] ?? 'Consumidor Final') ?></td>
                <td><strong>Documento</strong><br><?= esc($sale['customer_document_snapshot'] ?? $customer['document_number'] ?? '-') ?></td>
                <td><strong>Moneda</strong><br><?= esc($sale['currency_code'] ?? 'ARS') ?></td>
                <td><strong>Vencimiento</strong><br><?= esc(! empty($sale['due_date']) ? date('d/m/Y', strtotime($sale['due_date'])) : '-') ?></td>
            </tr>
            <tr>
                <td colspan="2"><strong>Perfil fiscal</strong><br><?= esc($sale['customer_tax_profile'] ?? $customer['vat_condition'] ?? $customer['tax_profile'] ?? 'Consumidor Final') ?></td>
                <td><strong>Documento tipo</strong><br><?= esc($sale['document_type_name'] ?? $sale['document_code'] ?? '-') ?></td>
                <td colspan="2"><strong>Observaciones</strong><br><?= esc($sale['notes'] ?: '-') ?></td>
            </tr>
            <tr>
                <td><strong>Estado ARCA</strong><br><?= esc($sale['arca_status'] ?? '-') ?></td>
                <td><strong>Servicio</strong><br><?= esc(strtoupper((string) ($sale['arca_service'] ?? '-'))) ?></td>
                <td><strong>CAE</strong><br><?= esc($sale['cae'] ?? '-') ?></td>
                <td><strong>Vto. CAE</strong><br><?= esc(! empty($sale['cae_due_date']) ? date('d/m/Y', strtotime($sale['cae_due_date'])) : '-') ?></td>
                <td><strong>Resultado fiscal</strong><br><?= esc($sale['arca_result_code'] ?? '-') ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Items</h3>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Producto</th>
                    <th>Cant.</th>
                    <th>P. Unit.</th>
                    <th>Desc.</th>
                    <th>Imp.</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= esc($item['sku']) ?></td>
                        <td><?= esc($item['product_name']) ?></td>
                        <td><?= number_format((float) $item['quantity'], 2, ',', '.') ?></td>
                        <td><?= number_format((float) $item['unit_price'], 2, ',', '.') ?></td>
                        <td><?= number_format((float) $item['discount_amount'], 2, ',', '.') ?></td>
                        <td><?= number_format((float) $item['tax_total'], 2, ',', '.') ?></td>
                        <td class="right"><?= number_format((float) $item['line_total'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <table class="totals">
            <tr><td class="right"><strong>Subtotal:</strong> <?= number_format((float) ($sale['subtotal'] ?? 0), 2, ',', '.') ?></td></tr>
            <tr><td class="right"><strong>Descuento global:</strong> <?= number_format((float) ($sale['global_discount_total'] ?? 0), 2, ',', '.') ?></td></tr>
            <tr><td class="right"><strong>Impuestos:</strong> <?= number_format((float) ($sale['tax_total'] ?? 0), 2, ',', '.') ?></td></tr>
            <tr><td class="right"><strong>Total:</strong> <?= number_format((float) ($sale['total'] ?? 0), 2, ',', '.') ?></td></tr>
            <tr><td class="right"><strong>Pagado:</strong> <?= number_format((float) ($sale['paid_total'] ?? 0), 2, ',', '.') ?></td></tr>
        </table>
    </div>

    <?php if (! empty($payments)): ?>
        <div class="section">
            <h3>Pagos</h3>
            <table>
                <thead>
                    <tr>
                        <th>Metodo</th>
                        <th>Monto</th>
                        <th>Referencia</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= esc($payment['payment_method']) ?></td>
                            <td><?= number_format((float) $payment['amount'], 2, ',', '.') ?></td>
                            <td><?= esc($payment['reference'] ?: '-') ?></td>
                            <td><?= esc(! empty($payment['paid_at']) ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (! empty($returns)): ?>
        <div class="section">
            <h3>Devoluciones</h3>
            <table>
                <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returns as $return): ?>
                        <tr>
                            <td><?= esc($return['return_number']) ?></td>
                            <td><?= esc($return['status']) ?></td>
                            <td><?= number_format((float) $return['total'], 2, ',', '.') ?></td>
                            <td><?= esc($return['reason'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>
</html>
