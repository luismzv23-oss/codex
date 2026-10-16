<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Caja - <?= esc($register['name']) ?></title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #000000;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 16px;
            margin: 0 0 5px;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 12px;
            margin: 0 0 5px;
            color: #555;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f2f2f2;
            padding: 4px 6px;
            margin-top: 15px;
            margin-bottom: 8px;
            border: 1px solid #ddd;
        }
        .info-table, .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-table td {
            padding: 4px;
            vertical-align: top;
            border: 0;
        }
        .info-table td.label {
            font-weight: bold;
            width: 30%;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 10px;
        }
        .data-table th {
            background-color: #f9f9f9;
            font-weight: bold;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .badge-open {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .badge-closed {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        .totals-box {
            margin-top: 20px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        .signature-section {
            margin-top: 50px;
            width: 100%;
        }
        .signature-box {
            width: 45%;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 5px;
            display: inline-block;
        }
        .signature-spacer {
            width: 8%;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Cierre de Caja</h1>
        <h2><?= esc($company['legal_name'] ?: $company['name']) ?></h2>
        <div style="font-size: 9px; color: #666;">
            CUIT: <?= esc($company['tax_id'] ?: '-') ?> | Dirección: <?= esc($company['address'] ?: '-') ?>
        </div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Caja:</td>
            <td><?= esc($register['name']) ?> (<?= esc(strtoupper($register['code'])) ?>)</td>
            <td class="label">Estado:</td>
            <td>
                <?php if ($session['status'] === 'open'): ?>
                    <span class="badge badge-open">Abierta</span>
                <?php else: ?>
                    <span class="badge badge-closed">Cerrada</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="label">Apertura:</td>
            <td><?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?> por <?= esc($openedBy['name'] ?? 'Usuario') ?></td>
            <td class="label">Cierre:</td>
            <td><?= $session['closed_at'] ? esc(date('d/m/Y H:i', strtotime($session['closed_at']))) . ' por ' . esc($closedBy['name'] ?? 'Usuario') : '-' ?></td>
        </tr>
    </table>

    <div class="section-title">Resumen de Saldos</div>
    <table class="info-table" style="background-color: #fcfcfc; border: 1px solid #eee; padding: 5px;">
        <tr>
            <td class="label">Saldo Inicial:</td>
            <td class="text-right">$<?= number_format((float)$session['opening_amount'], 2, ',', '.') ?></td>
            <td class="label">Saldo Esperado (Teórico):</td>
            <td class="text-right" style="font-weight: bold;">$<?= number_format((float)$session['expected_closing_amount'], 2, ',', '.') ?></td>
        </tr>
        <?php if ($session['status'] === 'closed'): ?>
            <tr>
                <td class="label">Saldo Real (Arqueo):</td>
                <td class="text-right" style="font-weight: bold;">$<?= number_format((float)$session['actual_closing_amount'], 2, ',', '.') ?></td>
                <td class="label">Diferencia:</td>
                <td class="text-right" style="font-weight: bold; color: <?= (float)$session['difference_amount'] >= 0 ? '#155724' : '#721c24' ?>;">
                    $<?= number_format((float)$session['difference_amount'], 2, ',', '.') ?>
                    <?php if ((float)$session['difference_amount'] > 0): ?>
                        (Sobrante)
                    <?php elseif ((float)$session['difference_amount'] < 0): ?>
                        (Faltante)
                    <?php else: ?>
                        (Cuadrado)
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="section-title">Resumen por Medio de Pago (Jornada)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Medio de Pago</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php $totalMovements=(float)$session['expected_closing_amount']-(float)$session['opening_amount']; ?>
            <?php foreach ($paymentMethods as $pm): ?>
                <?php
                $methodName = \App\Libraries\PaymentIntegrityService::label($pm).' · '.($pm['currency_code'] ?? '');
                if (strtolower($methodName) === 'cash') {
                    $displayName = 'Efectivo';
                } elseif (strtolower($methodName) === 'card') {
                    $displayName = 'Tarjeta';
                } else {
                    $displayName = ucfirst($methodName);
                }
                ?>
                <tr>
                    <td><?= esc($displayName) ?></td>
                    <td class="text-right">$<?= number_format((float)$pm['total'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="font-weight: bold; background-color: #f2f2f2;">
                <td>Movimiento neto en moneda base</td>
                <td class="text-right">$<?= number_format($totalMovements, 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <?php
    $invoiceCount = count($sales);
    $invoiceTotal = array_sum(array_column($sales, 'total'));
    ?>
    <div style="page-break-inside: avoid;">
        <div class="section-title">RESUMEN DE FACTURACI&Oacute;N DEL TURNO</div>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
            <tr>
                <td style="width: 35%; background: #eef1f8; padding: 18px; border: 1px solid #dce1ed; text-align: center;">
                    <div style="font-size: 9px; color: #52617b;">COMPROBANTES EMITIDOS</div>
                    <div style="font-size: 28px; font-weight: bold; color: #222e50;"><?= $invoiceCount ?></div>
                </td>
                <td style="background: #222e50; color: #ffffff; padding: 18px; text-align: center;">
                    <div style="font-size: 9px;">TOTAL FACTURADO</div>
                    <div style="font-size: 24px; font-weight: bold;">$<?= number_format($invoiceTotal, 2, ',', '.') ?></div>
                </td>
            </tr>
        </table>
        <p style="font-size: 9px; color: #666;">Cada comprobante se cuenta una sola vez, incluso cuando tiene varios medios de pago.</p>
        <?php if ($invoiceCount === 0): ?><p>No se emitieron comprobantes durante este turno.</p><?php endif; ?>
    </div>

    <?php if (!empty($session['notes'])): ?>
        <div style="margin-top: 15px;">
            <strong>Observaciones / Notas:</strong>
            <p style="margin: 5px 0; font-style: italic; background-color: #fdfdfd; padding: 6px; border: 1px solid #eee; border-radius: 3px;">
                <?= esc($session['notes']) ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="signature-box">
            Firma Responsable Caja
        </div>
        <div class="signature-spacer"></div>
        <div class="signature-box">
            Firma Auditor / Tesorería
        </div>
    </div>
</body>
</html>
