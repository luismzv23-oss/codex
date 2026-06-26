<?php

namespace App\Libraries;

/**
 * LibroIvaDigitalService — Generates IVA books in AFIP RG 4597 format.
 * Exports sales and purchases IVA books as TXT files for AFIP submission.
 */
class LibroIvaDigitalService
{
    /**
     * Map tax rate percentage to AFIP official alicuota code.
     */
    private function mapTaxRateToAfipCode(float $rate): int
    {
        $rateInt = (int) round($rate * 10);
        return match ($rateInt) {
            0 => 3,     // 0%
            25 => 9,    // 2.5%
            50 => 8,    // 5%
            105 => 4,   // 10.5%
            210 => 5,   // 21%
            270 => 6,   // 27%
            default => 5, // Default to 21% if unknown
        };
    }

    /**
     * Format amount for AFIP TXT: 13 integers + 2 decimals, zero-padded.
     * The first character is '-' if negative, else it's part of the zero-padded string.
     */
    private function formatAmount(float $amount): string
    {
        $cents = (int) round(abs($amount) * 100);
        if ($amount < 0) {
            return '-' . str_pad((string) $cents, 14, '0', STR_PAD_LEFT);
        }
        return str_pad((string) $cents, 15, '0', STR_PAD_LEFT);
    }

    /**
     * Format exchange rate for AFIP TXT: 10 integers + 6 decimals = 16 characters.
     */
    private function formatExchangeRate(float $rate): string
    {
        $cents = (int) round($rate * 1000000);
        return str_pad((string) $cents, 16, '0', STR_PAD_LEFT);
    }

    /**
     * Generate Libro IVA Ventas for a period.
     * Returns array of records + summary.
     */
    public function ventasReport(string $companyId, string $periodFrom, string $periodTo): array
    {
        $db = db_connect();

        $sales = $db->table('sales s')
            ->select('s.*, dt.name AS doc_type_name, dt.code AS doc_type_code')
            ->join('sales_document_types dt', 'dt.id = s.document_type_id', 'left')
            ->where('s.company_id', $companyId)
            ->where('s.status', 'confirmed')
            ->where('s.issue_date >=', $periodFrom)
            ->where('s.issue_date <=', $periodTo)
            ->orderBy('s.issue_date', 'ASC')
            ->orderBy('s.sale_number', 'ASC')
            ->get()
            ->getResultArray();

        $records = [];
        $totals  = ['neto_gravado' => 0, 'iva' => 0, 'exento' => 0, 'no_gravado' => 0, 'total' => 0, 'percepciones' => 0];

        foreach ($sales as $sale) {
            $netoGravado = (float) ($sale['subtotal'] ?? 0);
            $iva         = (float) ($sale['tax_total'] ?? 0);
            $total       = (float) ($sale['total'] ?? 0);
            $exento      = (float) ($sale['exempt_total'] ?? 0);
            $noGravado   = (float) ($sale['non_taxable_total'] ?? 0);

            // Load items to compute breakdown by tax rate
            $items = $db->table('sale_items')->where('sale_id', $sale['id'])->get()->getResultArray();
            $alicuotas = [];
            foreach ($items as $item) {
                $rate = (float) ($item['tax_rate'] ?? 0);
                $net  = (float) ($item['subtotal'] ?? 0);
                $tax  = (float) ($item['tax_total'] ?? 0);

                if ($rate > 0) {
                    $afipCode = $this->mapTaxRateToAfipCode($rate);
                    if (!isset($alicuotas[$afipCode])) {
                        $alicuotas[$afipCode] = [
                            'codigo_alicuota' => $afipCode,
                            'neto_gravado'    => 0.0,
                            'iva'             => 0.0,
                        ];
                    }
                    $alicuotas[$afipCode]['neto_gravado'] += $net;
                    $alicuotas[$afipCode]['iva']          += $tax;
                }
            }

            // Fallback if no items but there is tax
            if (empty($alicuotas) && $iva > 0) {
                $alicuotas[5] = [
                    'codigo_alicuota' => 5,
                    'neto_gravado'    => $netoGravado,
                    'iva'             => $iva,
                ];
            }

            $records[] = [
                'id'               => $sale['id'],
                'fecha'            => $sale['issue_date'] ?? $sale['sale_date'] ?? '',
                'tipo_cbte'        => $sale['doc_type_code'] ?? $sale['document_code'] ?? '6',
                'tipo_cbte_nombre' => $sale['doc_type_name'] ?? '',
                'punto_venta'      => str_pad((string) ($sale['point_of_sale_number'] ?? '1'), 5, '0', STR_PAD_LEFT),
                'numero_cbte'      => str_pad((string) ($sale['sale_number'] ?? '0'), 8, '0', STR_PAD_LEFT),
                'doc_tipo'         => $sale['customer_document_type'] ?? '80',
                'doc_nro'          => $sale['customer_document_snapshot'] ?? '',
                'razon_social'     => $sale['customer_name_snapshot'] ?? '',
                'neto_gravado'     => $netoGravado,
                'iva_21'           => $iva,
                'exento'           => $exento,
                'no_gravado'       => $noGravado,
                'percepciones'     => 0,
                'total'            => $total,
                'cae'              => $sale['cae'] ?? '',
                'cae_vto'          => $sale['cae_due_date'] ?? '',
                'moneda'           => $sale['currency_code'] ?? 'ARS',
                'cambio'           => (float) ($sale['exchange_rate'] ?? 1),
                'alicuotas'        => array_values($alicuotas),
            ];

            $totals['neto_gravado'] += $netoGravado;
            $totals['iva']          += $iva;
            $totals['exento']       += $exento;
            $totals['no_gravado']   += $noGravado;
            $totals['total']        += $total;
        }

        return [
            'type'    => 'ventas',
            'period'  => ['from' => $periodFrom, 'to' => $periodTo],
            'records' => $records,
            'totals'  => $totals,
            'count'   => count($records),
        ];
    }

    /**
     * Generate Libro IVA Compras for a period.
     */
    public function comprasReport(string $companyId, string $periodFrom, string $periodTo): array
    {
        $db = db_connect();

        $invoices = $db->table('purchase_invoices pi')
            ->select('pi.*, s.name AS supplier_name, s.tax_id AS supplier_tax_id, s.vat_condition')
            ->join('suppliers s', 's.id = pi.supplier_id', 'left')
            ->where('pi.company_id', $companyId)
            ->where('pi.status !=', 'cancelled')
            ->where('pi.issue_date >=', $periodFrom)
            ->where('pi.issue_date <=', $periodTo)
            ->orderBy('pi.issue_date', 'ASC')
            ->get()
            ->getResultArray();

        $records = [];
        $totals  = ['neto_gravado' => 0, 'iva' => 0, 'exento' => 0, 'no_gravado' => 0, 'total' => 0, 'retenciones' => 0];

        foreach ($invoices as $inv) {
            $netoGravado = (float) ($inv['subtotal'] ?? 0);
            $iva         = (float) ($inv['tax_total'] ?? 0);
            $total       = (float) ($inv['total'] ?? 0);
            $exento      = 0.0; // Fallback or computed if column existed
            $noGravado   = 0.0;
            $retenciones = 0.0;

            // Load items to compute breakdown by tax rate
            $items = $db->table('purchase_invoice_items')->where('purchase_invoice_id', $inv['id'])->get()->getResultArray();
            $alicuotas = [];
            foreach ($items as $item) {
                $rate = (float) ($item['tax_rate'] ?? 0);
                $tax = (float) ($item['tax_amount'] ?? 0);
                $total_item = (float) ($item['line_total'] ?? 0);
                $net = $total_item - $tax;

                if ($rate > 0) {
                    $afipCode = $this->mapTaxRateToAfipCode($rate);
                    if (!isset($alicuotas[$afipCode])) {
                        $alicuotas[$afipCode] = [
                            'codigo_alicuota' => $afipCode,
                            'neto_gravado'    => 0.0,
                            'iva'             => 0.0,
                        ];
                    }
                    $alicuotas[$afipCode]['neto_gravado'] += $net;
                    $alicuotas[$afipCode]['iva']          += $tax;
                }
            }

            // Fallback if no items but there is tax
            if (empty($alicuotas) && $iva > 0) {
                $alicuotas[5] = [
                    'codigo_alicuota' => 5,
                    'neto_gravado'    => $netoGravado,
                    'iva'             => $iva,
                ];
            }

            // Parse supplier invoice type based on invoice number letter prefix or supplier VAT condition
            $numStr = strtoupper(trim($inv['invoice_number'] ?? ''));
            $tipoCbte = 1; // Default Factura A
            if (str_contains($numStr, 'B')) {
                $tipoCbte = 6;
            } elseif (str_contains($numStr, 'C')) {
                $tipoCbte = 11;
            } else {
                $vat = strtolower(trim($inv['vat_condition'] ?? ''));
                if (str_contains($vat, 'monotribut') || str_contains($vat, 'exento')) {
                    $tipoCbte = 11;
                }
            }

            $records[] = [
                'id'               => $inv['id'],
                'fecha'            => $inv['issue_date'] ?? '',
                'tipo_cbte'        => $tipoCbte,
                'numero_cbte'      => $inv['invoice_number'] ?? '',
                'doc_tipo'         => '80', // CUIT
                'doc_nro'          => $inv['supplier_cuit'] ?? $inv['supplier_tax_id'] ?? '',
                'razon_social'     => $inv['supplier_name'] ?? '',
                'neto_gravado'     => $netoGravado,
                'iva_21'           => $iva,
                'exento'           => $exento,
                'no_gravado'       => $noGravado,
                'retenciones'      => $retenciones,
                'total'            => $total,
                'cae'              => $inv['cae'] ?? '',
                'moneda'           => $inv['currency_code'] ?? 'ARS',
                'cambio'           => (float) ($inv['exchange_rate'] ?? 1),
                'alicuotas'        => array_values($alicuotas),
            ];

            $totals['neto_gravado'] += $netoGravado;
            $totals['iva']          += $iva;
            $totals['total']        += $total;
        }

        return [
            'type'    => 'compras',
            'period'  => ['from' => $periodFrom, 'to' => $periodTo],
            'records' => $records,
            'totals'  => $totals,
            'count'   => count($records),
        ];
    }

    /**
     * Legacy exporter support (exports CBTE).
     */
    public function exportVentasTxt(string $companyId, string $periodFrom, string $periodTo): string
    {
        return $this->exportVentasCbteTxt($companyId, $periodFrom, $periodTo);
    }

    /**
     * Legacy exporter support (exports CBTE).
     */
    public function exportComprasTxt(string $companyId, string $periodFrom, string $periodTo): string
    {
        return $this->exportComprasCbteTxt($companyId, $periodFrom, $periodTo);
    }

    /**
     * Export Ventas Comprobantes (266 positions).
     */
    public function exportVentasCbteTxt(string $companyId, string $periodFrom, string $periodTo): string
    {
        $report = $this->ventasReport($companyId, $periodFrom, $periodTo);
        $lines = [];

        foreach ($report['records'] as $r) {
            $line = '';
            $line .= str_replace('-', '', substr($r['fecha'], 0, 10));                // 8 - Fecha
            $line .= str_pad((string) $r['tipo_cbte'], 3, '0', STR_PAD_LEFT);       // 3 - Tipo comprobante
            $line .= str_pad((string) $r['punto_venta'], 5, '0', STR_PAD_LEFT);     // 5 - Punto de venta
            $line .= str_pad((string) $r['numero_cbte'], 20, '0', STR_PAD_LEFT);     // 20 - Numero comprobante
            $line .= str_pad((string) $r['numero_cbte'], 20, '0', STR_PAD_LEFT);     // 20 - Numero hasta
            $line .= str_pad((string) $r['doc_tipo'], 2, '0', STR_PAD_LEFT);         // 2 - Tipo documento
            $line .= str_pad(preg_replace('/\D/', '', (string) $r['doc_nro']), 20, '0', STR_PAD_LEFT); // 20 - Numero documento
            $line .= substr(str_pad((string) $r['razon_social'], 30, ' '), 0, 30);   // 30 - Razon social
            $line .= $this->formatAmount($r['total']);                               // 15 - Importe total
            $line .= $this->formatAmount($r['no_gravado']);                           // 15 - No gravado
            $line .= $this->formatAmount($r['exento']);                              // 15 - Exento
            $line .= $this->formatAmount(0);                                         // 15 - Percepciones IVA / pagos a cuenta Nac
            $line .= $this->formatAmount($r['percepciones']);                        // 15 - Percepciones IIBB
            $line .= $this->formatAmount(0);                                         // 15 - Percepciones Mun
            $line .= $this->formatAmount(0);                                         // 15 - Impuestos Internos
            $line .= str_pad($r['moneda'] === 'ARS' ? 'PES' : $r['moneda'], 3, ' '); // 3 - Código de Moneda
            $line .= $this->formatExchangeRate($r['cambio']);                        // 16 - Tipo de cambio
            $line .= (string) count($r['alicuotas']);                                // 1 - Cantidad de alícuotas
            $line .= ($r['exento'] > 0 && $r['neto_gravado'] == 0) ? 'E' : ' ';      // 1 - Código de operación
            $line .= $this->formatAmount(0);                                         // 15 - Otros tributos
            $line .= str_pad(str_replace('-', '', $r['cae_vto'] ? substr($r['cae_vto'], 0, 10) : ''), 8, '0', STR_PAD_LEFT); // 8 - Vencimiento cbte
            $line .= str_repeat(' ', 8);                                             // 8 - Vencimiento pago (spaces)
            $line .= ' ';                                                            // 1 - Relleno

            $lines[] = substr(str_pad($line, 266, ' '), 0, 266);
        }

        return implode("\r\n", $lines);
    }

    /**
     * Export Ventas Alícuotas (84 positions).
     */
    public function exportVentasAlicuotasTxt(string $companyId, string $periodFrom, string $periodTo): string
    {
        $report = $this->ventasReport($companyId, $periodFrom, $periodTo);
        $lines = [];

        foreach ($report['records'] as $r) {
            foreach ($r['alicuotas'] as $aliq) {
                $line = '';
                $line .= str_pad((string) $r['tipo_cbte'], 3, '0', STR_PAD_LEFT);       // 3 - Tipo comprobante
                $line .= str_pad((string) $r['punto_venta'], 5, '0', STR_PAD_LEFT);     // 5 - Punto de venta
                $line .= str_pad((string) $r['numero_cbte'], 20, '0', STR_PAD_LEFT);     // 20 - Numero comprobante
                $line .= $this->formatAmount($aliq['neto_gravado']);                     // 15 - Neto gravado
                $line .= str_pad((string) $aliq['codigo_alicuota'], 4, '0', STR_PAD_LEFT); // 4 - Alícuota
                $line .= $this->formatAmount($aliq['iva']);                              // 15 - Impuesto liquidado
                $line .= str_repeat(' ', 22);                                            // 22 - Relleno

                $lines[] = substr(str_pad($line, 84, ' '), 0, 84);
            }
        }

        return implode("\r\n", $lines);
    }

    /**
     * Export Compras Comprobantes (325 positions).
     */
    public function exportComprasCbteTxt(string $companyId, string $periodFrom, string $periodTo): string
    {
        $report = $this->comprasReport($companyId, $periodFrom, $periodTo);
        $lines = [];

        foreach ($report['records'] as $r) {
            $parts = explode('-', $r['numero_cbte'] ?? '');
            $ptoVta = 1;
            $numCbte = 0;
            if (count($parts) === 2) {
                $ptoVta = (int) $parts[0];
                $numCbte = (int) $parts[1];
            } else {
                $numCbte = (int) preg_replace('/\D/', '', $r['numero_cbte'] ?? '');
            }

            $line = '';
            $line .= str_replace('-', '', substr($r['fecha'], 0, 10));                // 8 - Fecha
            $line .= str_pad((string) $r['tipo_cbte'], 3, '0', STR_PAD_LEFT);       // 3 - Tipo comprobante
            $line .= str_pad((string) $ptoVta, 5, '0', STR_PAD_LEFT);               // 5 - Punto de venta
            $line .= str_pad((string) $numCbte, 20, '0', STR_PAD_LEFT);              // 20 - Numero comprobante
            $line .= str_repeat(' ', 16);                                            // 16 - Despacho de importacion
            $line .= str_pad((string) $r['doc_tipo'], 2, '0', STR_PAD_LEFT);         // 2 - Tipo documento
            $line .= str_pad(preg_replace('/\D/', '', (string) $r['doc_nro']), 20, '0', STR_PAD_LEFT); // 20 - Numero documento
            $line .= substr(str_pad((string) $r['razon_social'], 30, ' '), 0, 30);   // 30 - Razon social
            $line .= $this->formatAmount($r['total']);                               // 15 - Importe total
            $line .= $this->formatAmount($r['no_gravado']);                           // 15 - No gravado
            $line .= $this->formatAmount($r['exento']);                              // 15 - Exento
            $line .= $this->formatAmount(0);                                         // 15 - Percepciones IVA
            $line .= $this->formatAmount(0);                                         // 15 - Percepciones Nac
            $line .= $this->formatAmount($r['retenciones']);                         // 15 - Percepciones IIBB
            $line .= $this->formatAmount(0);                                         // 15 - Percepciones Mun
            $line .= $this->formatAmount(0);                                         // 15 - Impuestos internos
            $line .= str_pad($r['moneda'] ?? 'PES', 3, ' ');                         // 3 - Codigo de moneda
            $line .= $this->formatExchangeRate($r['cambio'] ?? 1.0);                 // 16 - Tipo de cambio
            $line .= (string) count($r['alicuotas']);                                // 1 - Cantidad de alícuotas
            $line .= ' ';                                                            // 1 - Código de operación
            $line .= $this->formatAmount($r['iva_21']);                              // 15 - Credito fiscal computable
            $line .= $this->formatAmount(0);                                         // 15 - Otros tributos
            $line .= str_repeat('0', 11);                                            // 11 - CUIT Emisor
            $line .= str_repeat(' ', 30);                                            // 30 - Denominacion Emisor
            $line .= str_repeat('0', 9);                                             // 9 - IVA Comisión / Relleno

            $lines[] = substr(str_pad($line, 325, ' '), 0, 325);
        }

        return implode("\r\n", $lines);
    }

    /**
     * Export Compras Alícuotas (92 positions).
     */
    public function exportComprasAlicuotasTxt(string $companyId, string $periodFrom, string $periodTo): string
    {
        $report = $this->comprasReport($companyId, $periodFrom, $periodTo);
        $lines = [];

        foreach ($report['records'] as $r) {
            foreach ($r['alicuotas'] as $aliq) {
                $parts = explode('-', $r['numero_cbte'] ?? '');
                $ptoVta = 1;
                $numCbte = 0;
                if (count($parts) === 2) {
                    $ptoVta = (int) $parts[0];
                    $numCbte = (int) $parts[1];
                } else {
                    $numCbte = (int) preg_replace('/\D/', '', $r['numero_cbte'] ?? '');
                }

                $line = '';
                $line .= str_pad((string) $r['tipo_cbte'], 3, '0', STR_PAD_LEFT);       // 3 - Tipo comprobante
                $line .= str_pad((string) $ptoVta, 5, '0', STR_PAD_LEFT);               // 5 - Punto de venta
                $line .= str_pad((string) $numCbte, 20, '0', STR_PAD_LEFT);              // 20 - Numero comprobante
                $line .= str_pad((string) $r['doc_tipo'], 2, '0', STR_PAD_LEFT);         // 2 - Tipo documento
                $line .= str_pad(preg_replace('/\D/', '', (string) $r['doc_nro']), 20, '0', STR_PAD_LEFT); // 20 - Numero documento
                $line .= $this->formatAmount($aliq['neto_gravado']);                     // 15 - Neto gravado
                $line .= str_pad((string) $aliq['codigo_alicuota'], 4, '0', STR_PAD_LEFT); // 4 - Alícuota
                $line .= $this->formatAmount($aliq['iva']);                              // 15 - Impuesto liquidado
                $line .= str_repeat(' ', 8);                                             // 8 - Relleno

                $lines[] = substr(str_pad($line, 92, ' '), 0, 92);
            }
        }

        return implode("\r\n", $lines);
    }
}
