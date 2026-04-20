<?php

namespace App\Libraries;

/**
 * LibroIvaDigitalService — Generates IVA books in AFIP RG 4597 format.
 * Exports sales and purchases IVA books as TXT files for AFIP submission.
 */
class LibroIvaDigitalService
{
    /**
     * Generate Libro IVA Ventas for a period.
     * Returns array of records + summary.
     */
    public function ventasReport(string $companyId, string $periodFrom, string $periodTo): array
    {
        $db = db_connect();

        $sales = $db->table('sales s')
            ->select('s.*, dt.name AS doc_type_name, dt.code AS doc_type_code, dt.afip_code AS cbte_tipo')
            ->join('document_types dt', 'dt.id = s.document_type_id', 'left')
            ->where('s.company_id', $companyId)
            ->where('s.status', 'confirmed')
            ->where('s.sale_date >=', $periodFrom)
            ->where('s.sale_date <=', $periodTo)
            ->orderBy('s.sale_date', 'ASC')
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

            $records[] = [
                'fecha'            => $sale['sale_date'] ?? '',
                'tipo_cbte'        => $sale['cbte_tipo'] ?? '',
                'tipo_cbte_nombre' => $sale['doc_type_name'] ?? '',
                'punto_venta'      => str_pad((string) ($sale['point_of_sale_number'] ?? '1'), 5, '0', STR_PAD_LEFT),
                'numero_cbte'      => str_pad((string) ($sale['sale_number'] ?? '0'), 8, '0', STR_PAD_LEFT),
                'doc_tipo'         => $sale['customer_document_type'] ?? '80',
                'doc_nro'          => $sale['customer_document_snapshot'] ?? '',
                'razon_social'     => $sale['customer_name_snapshot'] ?? '',
                'neto_gravado'     => $netoGravado,
                'iva_21'           => $iva,
                'iva_105'          => 0,
                'iva_27'           => 0,
                'exento'           => $exento,
                'no_gravado'       => $noGravado,
                'percepciones'     => 0,
                'total'            => $total,
                'cae'              => $sale['cae'] ?? '',
                'cae_vto'          => $sale['cae_due_date'] ?? '',
                'moneda'           => $sale['currency_code'] ?? 'ARS',
                'cambio'           => (float) ($sale['exchange_rate'] ?? 1),
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
            ->select('pi.*, s.name AS supplier_name, s.cuit AS supplier_cuit, s.tax_id AS supplier_tax_id')
            ->join('suppliers s', 's.id = pi.supplier_id', 'left')
            ->where('pi.company_id', $companyId)
            ->where('pi.status !=', 'cancelled')
            ->where('pi.invoice_date >=', $periodFrom)
            ->where('pi.invoice_date <=', $periodTo)
            ->orderBy('pi.invoice_date', 'ASC')
            ->get()
            ->getResultArray();

        $records = [];
        $totals  = ['neto_gravado' => 0, 'iva' => 0, 'exento' => 0, 'no_gravado' => 0, 'total' => 0, 'retenciones' => 0];

        foreach ($invoices as $inv) {
            $netoGravado = (float) ($inv['subtotal'] ?? 0);
            $iva         = (float) ($inv['tax_total'] ?? 0);
            $total       = (float) ($inv['total'] ?? 0);

            $records[] = [
                'fecha'            => $inv['invoice_date'] ?? '',
                'tipo_cbte'        => $inv['invoice_type'] ?? '',
                'numero_cbte'      => $inv['invoice_number'] ?? '',
                'doc_tipo'         => '80',
                'doc_nro'          => $inv['supplier_cuit'] ?? $inv['supplier_tax_id'] ?? '',
                'razon_social'     => $inv['supplier_name'] ?? '',
                'neto_gravado'     => $netoGravado,
                'iva_21'           => $iva,
                'iva_105'          => 0,
                'iva_27'           => 0,
                'exento'           => 0,
                'no_gravado'       => 0,
                'retenciones'      => 0,
                'total'            => $total,
                'cae'              => $inv['cae'] ?? '',
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
     * Export Libro IVA Ventas to AFIP TXT format (RG 4597).
     * Returns the TXT content as a string.
     */
    public function exportVentasTxt(string $companyId, string $periodFrom, string $periodTo): string
    {
        $report = $this->ventasReport($companyId, $periodFrom, $periodTo);
        $lines = [];

        foreach ($report['records'] as $r) {
            // AFIP format: fixed-width fields
            $line = '';
            $line .= str_replace('-', '', $r['fecha']);                              // 8 - Fecha
            $line .= str_pad((string) $r['tipo_cbte'], 3, '0', STR_PAD_LEFT);       // 3 - Tipo comprobante
            $line .= $r['punto_venta'];                                              // 5 - Punto de venta
            $line .= str_pad((string) $r['numero_cbte'], 20, '0', STR_PAD_LEFT);     // 20 - Numero comprobante
            $line .= str_pad((string) $r['numero_cbte'], 20, '0', STR_PAD_LEFT);     // 20 - Numero hasta
            $line .= str_pad((string) $r['doc_tipo'], 2, '0', STR_PAD_LEFT);         // 2 - Tipo documento
            $line .= str_pad((string) $r['doc_nro'], 20, '0', STR_PAD_LEFT);         // 20 - Numero documento
            $line .= str_pad($r['razon_social'], 30);                                // 30 - Razon social
            $line .= $this->formatAmount($r['total']);                               // 15 - Importe total
            $line .= $this->formatAmount($r['no_gravado']);                           // 15 - No gravado
            $line .= $this->formatAmount($r['percepciones']);                        // 15 - Percepciones
            $line .= $this->formatAmount($r['exento']);                              // 15 - Exento
            $line .= $this->formatAmount($r['neto_gravado']);                        // 15 - Neto gravado
            $line .= $this->formatAmount($r['iva_21']);                              // 15 - IVA
            $lines[] = $line;
        }

        return implode("\r\n", $lines);
    }

    /**
     * Export Libro IVA Compras to AFIP TXT format.
     */
    public function exportComprasTxt(string $companyId, string $periodFrom, string $periodTo): string
    {
        $report = $this->comprasReport($companyId, $periodFrom, $periodTo);
        $lines = [];

        foreach ($report['records'] as $r) {
            $line = '';
            $line .= str_replace('-', '', $r['fecha']);
            $line .= str_pad((string) $r['tipo_cbte'], 3, '0', STR_PAD_LEFT);
            $line .= str_pad((string) $r['numero_cbte'], 20, '0', STR_PAD_LEFT);
            $line .= str_pad((string) $r['doc_tipo'], 2, '0', STR_PAD_LEFT);
            $line .= str_pad((string) $r['doc_nro'], 20, '0', STR_PAD_LEFT);
            $line .= str_pad($r['razon_social'], 30);
            $line .= $this->formatAmount($r['total']);
            $line .= $this->formatAmount($r['no_gravado']);
            $line .= $this->formatAmount($r['exento']);
            $line .= $this->formatAmount($r['neto_gravado']);
            $line .= $this->formatAmount($r['iva_21']);
            $lines[] = $line;
        }

        return implode("\r\n", $lines);
    }

    /**
     * Format amount for AFIP TXT: 13 integers + 2 decimals, zero-padded.
     */
    private function formatAmount(float $amount): string
    {
        $cents = (int) round(abs($amount) * 100);
        $sign = $amount < 0 ? '-' : '';
        return $sign . str_pad((string) $cents, 15, '0', STR_PAD_LEFT);
    }
}
