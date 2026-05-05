<?php

namespace App\Libraries;

/**
 * SicoreService — Generates SICORE files for AFIP (RG 2233/4110).
 * Handles withholding and perception declarations.
 */
class SicoreService
{
    /**
     * Generate SICORE TXT for withholdings applied in a period.
     * Format: fixed-width per AFIP RG 2233.
     */
    public function exportWithholdingsTxt(string $companyId, string $from, string $to): string
    {
        $rows = (new WithholdingService())->withholdingsReport($companyId, $from, $to);
        $lines = [];

        foreach ($rows as $r) {
            $supplier = $this->resolveSupplier($r['supplier_id'] ?? null);
            $line = '';
            $line .= '06';                                                              // 2 - Codigo de comprobante (06=Orden de pago)
            $line .= str_replace('-', '', $r['applied_at'] ?? date('Y-m-d'));            // 10 - Fecha emision
            $line .= str_pad('', 16, '0');                                               // 16 - Nro comprobante
            $line .= $this->formatSicoreAmount((float)($r['base_amount'] ?? 0));         // 16 - Importe comprobante
            $line .= $this->resolveTaxCode($r['tax_type'] ?? 'iva');                     // 4 - Codigo impuesto
            $line .= $this->resolveRegimenCode($r['tax_type'] ?? 'iva');                 // 3 - Codigo regimen
            $line .= '1';                                                                // 1 - Operacion (1=Retencion)
            $line .= $this->formatSicoreAmount((float)($r['base_amount'] ?? 0));         // 14 - Base calculo
            $line .= str_replace('-', '', substr($r['applied_at'] ?? '', 0, 10));         // 10 - Fecha retencion
            $line .= '01';                                                               // 2 - Condicion (01=Inscripto)
            $line .= '0';                                                                // 1 - Retencion pract. a sujetos suspendidos
            $line .= $this->formatSicoreAmount((float)($r['amount'] ?? 0));              // 14 - Importe retencion
            $line .= $this->formatSicorePercentage((float)($r['rate'] ?? 0));            // 5 - Porcentaje exclusion
            $line .= str_replace('-', '', substr($r['applied_at'] ?? '', 0, 10));         // 10 - Fecha emision boletin
            $line .= '80';                                                               // 2 - Tipo documento retenido
            $line .= str_pad(preg_replace('/\D/', '', $supplier['cuit'] ?? ''), 20, '0', STR_PAD_LEFT); // 20 - Nro documento
            $line .= str_pad($r['certificate_number'] ?? '', 14, '0', STR_PAD_LEFT);     // 14 - Nro certificado
            $lines[] = $line;
        }

        return implode("\r\n", $lines);
    }

    /**
     * Generate SICORE TXT for perceptions applied in a period.
     */
    public function exportPerceptionsTxt(string $companyId, string $from, string $to): string
    {
        $rows = (new WithholdingService())->perceptionsReport($companyId, $from, $to);
        $lines = [];

        foreach ($rows as $r) {
            $customer = $this->resolveCustomer($r['customer_id'] ?? null);
            $line = '';
            $line .= '01';                                                              // 2 - Codigo comprobante (01=Factura)
            $line .= str_replace('-', '', $r['applied_at'] ?? date('Y-m-d'));
            $line .= str_pad('', 16, '0');
            $line .= $this->formatSicoreAmount((float)($r['base_amount'] ?? 0));
            $line .= $this->resolveTaxCode($r['tax_type'] ?? 'iva');
            $line .= $this->resolveRegimenCode($r['tax_type'] ?? 'iva');
            $line .= '2';                                                                // 1 - Operacion (2=Percepcion)
            $line .= $this->formatSicoreAmount((float)($r['base_amount'] ?? 0));
            $line .= str_replace('-', '', substr($r['applied_at'] ?? '', 0, 10));
            $line .= '01';
            $line .= '0';
            $line .= $this->formatSicoreAmount((float)($r['amount'] ?? 0));
            $line .= $this->formatSicorePercentage((float)($r['rate'] ?? 0));
            $line .= str_replace('-', '', substr($r['applied_at'] ?? '', 0, 10));
            $line .= '80';
            $line .= str_pad(preg_replace('/\D/', '', $customer['document_number'] ?? ''), 20, '0', STR_PAD_LEFT);
            $line .= str_pad('', 14, '0');
            $lines[] = $line;
        }

        return implode("\r\n", $lines);
    }

    /**
     * Summary data for the tax dashboard.
     */
    public function periodSummary(string $companyId, string $from, string $to): array
    {
        $withholdings = (new WithholdingService())->withholdingsReport($companyId, $from, $to);
        $perceptions  = (new WithholdingService())->perceptionsReport($companyId, $from, $to);

        return [
            'withholdings_count' => count($withholdings),
            'withholdings_total' => array_sum(array_column($withholdings, 'amount')),
            'perceptions_count'  => count($perceptions),
            'perceptions_total'  => array_sum(array_column($perceptions, 'amount')),
            'withholdings'       => $withholdings,
            'perceptions'        => $perceptions,
        ];
    }

    private function formatSicoreAmount(float $amount): string
    {
        $cents = (int) round(abs($amount) * 100);
        return str_pad((string) $cents, 16, '0', STR_PAD_LEFT);
    }

    private function formatSicorePercentage(float $pct): string
    {
        $val = (int) round(abs($pct) * 100);
        return str_pad((string) $val, 5, '0', STR_PAD_LEFT);
    }

    private function resolveTaxCode(string $taxType): string
    {
        return match ($taxType) {
            'iva'       => '0767',
            'ganancias' => '0217',
            'iibb'      => '0000',
            'suss'      => '0351',
            default     => '0000',
        };
    }

    private function resolveRegimenCode(string $taxType): string
    {
        return match ($taxType) {
            'iva'       => '499',
            'ganancias' => '046',
            'suss'      => '904',
            default     => '000',
        };
    }

    private function resolveSupplier(?string $supplierId): array
    {
        if (!$supplierId) return [];
        return db_connect()->table('suppliers')->where('id', $supplierId)->get()->getRowArray() ?? [];
    }

    private function resolveCustomer(?string $customerId): array
    {
        if (!$customerId) return [];
        return db_connect()->table('customers')->where('id', $customerId)->get()->getRowArray() ?? [];
    }
}
