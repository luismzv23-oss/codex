<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <?php
    $fontSizeVal = $ticketSettings['ticket_font_size'] ?? 'medium';
    $fontSizePx = '12px';
    if ($fontSizeVal === 'small') {
        $fontSizePx = '10px';
    } elseif ($fontSizeVal === 'large') {
        $fontSizePx = '14px';
    }

    $topLeftText = $ticketSettings['ticket_custom_text_top_left'] ?? 'IVA: Responsable Inscripto';

    // Resolve document type name, letter, and AFIP code
    $invoiceLetter = 'X';
    $invoiceCode = '--';
    $docName = 'COMPROBANTE';
    $cleanDocName = $docName;

    if (!empty($sale['document_type_id'])) {
        $docType = db_connect()->table('sales_document_types')->where('id', $sale['document_type_id'])->get()->getRowArray();
        if ($docType) {
            $name = $docType['name'];
            $docName = strtoupper($name);
            
            // Resolve Letter
            if (!empty($docType['letter'])) {
                $invoiceLetter = strtoupper($docType['letter']);
            } elseif (preg_match('/\b(Factura|Nota de Credito|Nota de Debito)\s+([A-BCM])\b/i', $name, $matches)) {
                $invoiceLetter = strtoupper($matches[2]);
            } elseif (stripos($name, 'Remito') !== false) {
                $invoiceLetter = 'R';
            } elseif (stripos($name, 'Pedido') !== false) {
                $invoiceLetter = 'P';
            } elseif (stripos($name, 'Presupuesto') !== false) {
                $invoiceLetter = 'X';
            } elseif (stripos($name, 'Ticket') !== false || ($docType['category'] ?? '') === 'ticket') {
                // Determine ticket letter based on company VAT profile (Responsable Inscripto -> B, Monotributo -> C)
                $isRI = stripos($topLeftText, 'Responsable Inscripto') !== false;
                $invoiceLetter = $isRI ? 'B' : 'C';
            }
            
            // Resolve AFIP code
            if (stripos($name, 'Factura A') !== false) $invoiceCode = '01';
            elseif (stripos($name, 'Nota de Debito A') !== false) $invoiceCode = '02';
            elseif (stripos($name, 'Nota de Credito A') !== false) $invoiceCode = '03';
            elseif (stripos($name, 'Factura B') !== false) $invoiceCode = '06';
            elseif (stripos($name, 'Nota de Debito B') !== false) $invoiceCode = '07';
            elseif (stripos($name, 'Nota de Credito B') !== false) $invoiceCode = '08';
            elseif (stripos($name, 'Factura C') !== false) $invoiceCode = '11';
            elseif (stripos($name, 'Nota de Debito C') !== false) $invoiceCode = '12';
            elseif (stripos($name, 'Nota de Credito C') !== false) $invoiceCode = '13';
            elseif (stripos($name, 'Factura M') !== false) $invoiceCode = '51';
            elseif (stripos($name, 'Ticket') !== false || ($docType['category'] ?? '') === 'ticket') {
                $invoiceCode = ($invoiceLetter === 'B') ? '83' : '11';
            }

            // Clean document name for display in right box
            $cleanDocName = $docName;
            if (preg_match('/^(Factura|Nota de Credito|Nota de Debito|Nota de Débito)\s+([A-BCM])$/i', $name, $matches)) {
                $cleanDocName = strtoupper($matches[1]);
            }
        }
    }
    ?>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #000000; font-size: <?= $fontSizePx ?>; margin: 0; padding: 0; }
        h1, h2, h3 { margin: 0 0 4px; }
        .section { margin-bottom: 12px; }
        
        /* Layout Tables */
        .table-bordered {
            width: 100%;
            border-collapse: collapse;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #000000;
            padding: 5px 6px;
            vertical-align: top;
        }
        .table-bordered th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: left;
            font-size: 10px;
        }
        
        /* Header Box */
        .header-box {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000000;
        }
        .header-cell {
            padding: 8px;
            vertical-align: top;
        }
        .header-left {
            width: 45%;
        }
        .header-center {
            width: 10%;
            text-align: center;
            border-left: 1px solid #000000;
            border-right: 1px solid #000000;
            position: relative;
        }
        .header-right {
            width: 45%;
        }
        
        /* Square letter badge */
        .letter-badge {
            border: 2px solid #000000;
            font-size: 26px;
            font-weight: bold;
            width: 42px;
            height: 42px;
            line-height: 42px;
            margin: 0 auto;
            text-align: center;
            background: #ffffff;
        }
        .letter-code {
            font-size: 9px;
            margin-top: 4px;
            font-weight: bold;
        }

        /* Customer Box */
        .info-box {
            width: 100%;
            border: 1px solid #000000;
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 2px;
        }
        .info-box td {
            border: 0;
            padding: 3px 2px;
            vertical-align: middle;
        }

        /* Totals Grid Table */
        .totals-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .totals-grid td {
            border: 1px solid #000000;
            padding: 6px;
            text-align: center;
            width: 16.66%;
            font-size: 11px;
        }
        .totals-grid .label {
            font-weight: bold;
            background: #f2f2f2;
            font-size: 9px;
            text-transform: uppercase;
        }
        
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #555555; font-size: 10px; }
        .barcode {
            background: repeating-linear-gradient(90deg, #000000, #000000 2px, #ffffff 2px, #ffffff 4px);
            width: 250px;
            height: 35px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <?php
    $showSku = (int) ($ticketSettings['ticket_show_sku'] ?? 1) === 1;
    $showBrand = (int) ($ticketSettings['ticket_show_brand'] ?? 1) === 1;
    $showBreakdown = (int) ($ticketSettings['ticket_show_item_breakdown'] ?? 1) === 1;
    $showCustomer = (int) ($ticketSettings['ticket_show_customer'] ?? 1) === 1;
    $showUser = (int) ($ticketSettings['ticket_show_user'] ?? 1) === 1;

    // Resolve Custom Position values or defaults
    $topLeftText = $ticketSettings['ticket_custom_text_top_left'] ?? 'IVA: Responsable Inscripto';
    
    // Top right parsing or default
    $topRightText = $ticketSettings['ticket_custom_text_top_right'] ?? '';
    if (empty($topRightText)) {
        $topRightText = "Ing. Brutos: CM. 901-111111-0\nInicio de Actividades: 01/04/1994";
    }
    
    $companyCuit = $company['tax_id'] ?: '30-68914568-0';
    $companyNameText = !empty($ticketSettings['ticket_header_title']) ? $ticketSettings['ticket_header_title'] : ($company['legal_name'] ?: $company['name']);
    ?>

    <!-- 1. Header Box (3 columns) -->
    <div class="section">
        <table class="header-box">
            <tr>
                <!-- Left: Company Details -->
                <td class="header-cell header-left">
                    <h2 style="font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px;"><?= esc($companyNameText) ?></h2>
                    <div style="font-size: 9px; font-weight: bold; margin-bottom: 6px;">IMPRENTA Y LIBRERIA</div>
                    <div style="font-size: 10px; line-height: 1.3;">
                        <?= esc($company['address'] ?: 'El Salvador 689 - (1406) Capital Federal') ?><br>
                        <?= esc($company['phone'] ?: 'Tel. 4616-1112 / 4639-0048') ?><br>
                        <span style="font-weight: bold;"><?= esc($topLeftText) ?></span>
                    </div>
                </td>
                
                <!-- Center: Letter Badge & Code -->
                <td class="header-cell header-center">
                    <div class="letter-badge"><?= esc($invoiceLetter) ?></div>
                    <div class="letter-code">Código Nº <?= esc($invoiceCode) ?></div>
                </td>
                
                <!-- Right: Document Info -->
                <td class="header-cell header-right">
                    <h2 style="font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px;"><?= esc($cleanDocName) ?></h2>
                    <?php
                    $posCode = '0001';
                    if (!empty($sale['point_of_sale_id'])) {
                        $pos = db_connect()->table('sales_points_of_sale')->where('id', $sale['point_of_sale_id'])->get()->getRowArray();
                        if ($pos) {
                            $posCode = $pos['code'] ?? $pos['name'] ?? '0001';
                            $posCode = preg_replace('/[^0-9]/', '', $posCode);
                            if (empty($posCode)) {
                                $posCode = '0001';
                            }
                            $posCode = str_pad($posCode, 4, '0', STR_PAD_LEFT);
                        }
                    }
                    ?>
                    <div style="font-size: 11px; font-weight: bold; margin-bottom: 8px;">Nº <?= esc($posCode) ?>-<?= esc(str_pad($sale['sale_number'] ?? '0', 8, '0', STR_PAD_LEFT)) ?></div>
                    <div style="font-size: 10px; line-height: 1.4;">
                        <strong>Fecha:</strong> <?= esc(!empty($sale['issue_date']) ? date('d/m/Y', strtotime($sale['issue_date'])) : date('d/m/Y')) ?><br>
                        <strong>C.U.I.T.:</strong> <?= esc($companyCuit) ?><br>
                        <span style="white-space: pre-line;"><?= esc($topRightText) ?></span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 2. Customer Info Box -->
    <div class="section">
        <table class="info-box">
            <tr>
                <td style="width: 12%;"><strong>Señores:</strong></td>
                <td style="width: 88%; border-bottom: 1px dotted #ccc; height: 16px;">
                    <?= esc($showCustomer ? ($sale['customer_name_snapshot'] ?? $customer['billing_name'] ?? $customer['name'] ?? '-') : 'Consumidor Final') ?>
                </td>
            </tr>
            <tr>
                <td style="width: 12%;"><strong>Dirección:</strong></td>
                <td style="width: 88%; border-bottom: 1px dotted #ccc; height: 16px;">
                    <?= esc($showCustomer ? ($customer['address'] ?? '-') : '-') ?>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <table style="width: 100%; border: 0;">
                        <tr>
                            <td style="width: 12%; padding: 0;"><strong>I.V.A.:</strong></td>
                            <td style="width: 48%; border-bottom: 1px dotted #ccc; padding: 0; height: 16px;">
                                <?= esc($showCustomer ? ($sale['customer_tax_profile'] ?? $customer['vat_condition'] ?? $customer['tax_profile'] ?? '-') : '-') ?>
                            </td>
                            <td style="width: 10%; padding: 0; text-align: right; padding-right: 8px;"><strong>C.U.I.T.:</strong></td>
                            <td style="width: 30%; border-bottom: 1px dotted #ccc; padding: 0; height: 16px;">
                                <?= esc($showCustomer ? ($sale['customer_document_snapshot'] ?? $customer['document_number'] ?? '-') : '-') ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- 3. Conditions & Seller Box -->
    <div class="section">
        <table class="info-box" style="margin-bottom: 12px;">
            <tr>
                <td style="width: 18%;"><strong>Condiciones de Venta:</strong></td>
                <td style="width: 42%; border-bottom: 1px dotted #ccc; height: 16px;">
                    <?= esc($sale['sales_condition_id'] ? (db_connect()->table('sales_conditions')->where('id', $sale['sales_condition_id'])->get()->getRowArray()['name'] ?? 'Contado') : 'Contado') ?>
                </td>
                <td style="width: 12%; text-align: right; padding-right: 8px;"><strong>Remito Nº:</strong></td>
                <td style="width: 28%; border-bottom: 1px dotted #ccc; height: 16px;">
                    <?php
                    $sourceDoc = '-';
                    if (!empty($sale['source_sale_id'])) {
                        $srcSale = (new \App\Models\SaleModel())->find($sale['source_sale_id']);
                        if ($srcSale) {
                            $sourceDoc = ($srcSale['document_code'] ?? 'VTA') . ' ' . ($srcSale['sale_number'] ?? '');
                        }
                    }
                    echo esc($sourceDoc);
                    ?>
                </td>
            </tr>
            <?php if ($showUser): ?>
                <tr>
                    <td style="width: 18%;"><strong>Vendedor:</strong></td>
                    <td colspan="3" style="border-bottom: 1px dotted #ccc; height: 16px;">
                        <?= esc($creatorName ?? '-') ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- 4. Items Table -->
    <div class="section" style="min-height: 250px;">
        <table class="table-bordered">
            <thead>
                <tr>
                    <th style="width: 15%;">CODIGO</th>
                    <th style="width: 10%; text-align: center;">CANTIDAD</th>
                    <th style="width: 50%;">DETALLE</th>
                    <th style="width: 12%; text-align: right;">P. UNITARIO</th>
                    <th style="width: 13%; text-align: right;">TOTAL $</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td style="font-family: monospace; font-size: 10px;">
                            <?= esc($showSku ? $item['sku'] : '-') ?>
                        </td>
                        <td class="center">
                            <?= number_format((float) $item['quantity'], 2, ',', '.') ?>
                        </td>
                        <td>
                            <?= esc($item['product_name']) ?>
                            <?php if ($showBrand && !empty($item['brand'])): ?>
                                <span class="muted">(<?= esc($item['brand']) ?>)</span>
                            <?php endif; ?>
                            <?php if ($showBreakdown && (float)$item['quantity'] !== 1.0): ?>
                                <div class="muted" style="font-size: 9px;"><?= number_format((float)$item['quantity'], 2, ',', '.') ?> u x $ <?= number_format((float)$item['unit_price'], 2, ',', '.') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="right">
                            $ <?= number_format((float) $item['unit_price'], 2, ',', '.') ?>
                        </td>
                        <td class="right" style="font-weight: bold;">
                            $ <?= number_format((float) $item['line_total'], 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5" class="center muted py-3">No hay productos en esta venta.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 5. Totals Grid (Image 2 style) -->
    <div class="section">
        <table class="totals-grid">
            <tr>
                <td class="label">Subtotal</td>
                <td class="label">Descuentos</td>
                <td class="label">Impuestos</td>
                <td class="label">IVA Insc. 21%</td>
                <td class="label">IVA No Insc.</td>
                <td class="label" style="background: #212529; color: #ffffff;">TOTAL $</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">$ <?= number_format((float) ($sale['subtotal'] ?? 0), 2, ',', '.') ?></td>
                <td>$ <?= number_format((float) ($sale['global_discount_total'] ?? 0), 2, ',', '.') ?></td>
                <td>$ <?= number_format((float) ($sale['tax_total'] ?? 0), 2, ',', '.') ?></td>
                <td>$ <?= number_format((float) (($sale['tax_total'] ?? 0) * 1.0), 2, ',', '.') ?></td>
                <td>$ 0,00</td>
                <td style="font-weight: bold; font-size: 13px; background: #f8fafc;">$ <?= number_format((float) ($sale['total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
        </table>
    </div>

    <!-- 6. Footer, CAE & Barcode block -->
    <div style="margin-top: 25px; border-top: 1px solid #000000; padding-top: 10px;">
        <table style="width: 100%; border: 0;">
            <tr>
                <!-- Left: Barcode -->
                <td style="width: 50%; border: 0; padding: 0; vertical-align: top;">
                    <div class="barcode"></div>
                    <div style="font-family: monospace; font-size: 9px; margin-top: 2px;">
                        <?= esc($sale['cae'] ?: '25064106537080') ?>
                    </div>
                </td>
                
                <!-- Right: CAE / Vto -->
                <td style="width: 50%; border: 0; padding: 0; text-align: right; vertical-align: top; font-size: 10px; line-height: 1.4;">
                    <?php if (!empty($sale['cae'])): ?>
                        <strong>C.A.E. Nº:</strong> <?= esc($sale['cae']) ?><br>
                        <strong>Fecha de Vto. CAE:</strong> <?= esc(!empty($sale['cae_due_date']) ? date('d/m/Y', strtotime($sale['cae_due_date'])) : '-') ?><br>
                    <?php else: ?>
                        <strong>DOCUMENTO NO FISCAL</strong><br>
                        <strong>PRESUPUESTO PREVIO</strong><br>
                    <?php endif; ?>
                    
                    <?php if (!empty($ticketSettings['ticket_footer_notes'])): ?>
                        <div style="margin-top: 8px; font-style: italic; font-size: 9px; color: #555555; text-align: right;">
                            <?= esc($ticketSettings['ticket_footer_notes']) ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- 7. Custom Position Margins Footer (Bottom Left / Bottom Right) -->
    <?php
    $bottomLeftText = $ticketSettings['ticket_custom_text_bottom_left'] ?? '';
    $bottomRightText = $ticketSettings['ticket_custom_text_bottom_right'] ?? '';
    if (empty($bottomLeftText) && empty($bottomRightText)) {
        $bottomLeftText = "Imprenta Su Imprenta CUIT: 30-12345678-9 Habil. 22222";
        $bottomRightText = "Fecha Impresión: " . date('d/m/Y') . " Numeración: 0001-00001601 al 0001-00001700";
    }
    ?>
    <div style="margin-top: 15px; font-size: 8px; color: #555555; border-top: 1px dotted #ccc; padding-top: 4px;">
        <table style="width: 100%; border: 0;">
            <tr>
                <td style="width: 50%; border: 0; padding: 0; font-size: 8px;">
                    <?= esc($bottomLeftText) ?>
                </td>
                <td style="width: 50%; border: 0; padding: 0; text-align: right; font-size: 8px;">
                    <?= esc($bottomRightText) ?>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
