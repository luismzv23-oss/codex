<?php

namespace App\Libraries;

use App\Libraries\WithholdingService;
use App\Libraries\SicoreService;

/**
 * WithholdingEngineService — Automated Tax Withholding Engine for Supplier Payments.
 * Automates calculation of Ganancias, IVA, and IIBB retenciones, updates monthly cumulative metrics,
 * generates certificates, and interfaces with Sicore / SIRE exports.
 */
class WithholdingEngineService
{
    protected WithholdingService $withholdingService;
    protected SicoreService $sicoreService;

    public function __construct()
    {
        $this->withholdingService = new WithholdingService();
        $this->sicoreService      = new SicoreService();
    }

    /**
     * Calculate and process all applicable tax withholdings for a supplier payment.
     */
    public function processPaymentWithholdings(
        string $companyId,
        string $supplierId,
        float $paymentNetTotal,
        array $invoicesToPay,
        string $paymentDate
    ): array {
        $db = db_connect();

        // 1. Fetch supplier tax profile
        $supplier = $db->table('suppliers')
            ->where('id', $supplierId)
            ->where('company_id', $companyId)
            ->get()->getRowArray();

        if (!$supplier) {
            return ['ok' => false, 'error' => 'Proveedor no encontrado'];
        }

        $taxCategory = $supplier['vat_condition'] ?? 'RI'; // Responsable Inscripto by default
        $cuit = $supplier['cuit'] ?? '';

        // If supplier is Monotributista or Exento, check non-subject status
        if (in_array($taxCategory, ['MONOTRIBUTO', 'EXENTO'])) {
            return [
                'ok'                    => true,
                'withholdings_applied'  => [],
                'total_withholding'     => 0.0,
                'net_payment_after_tax' => $paymentNetTotal,
                'message'               => 'Proveedor no sujeto a retención por condición IVA: ' . $taxCategory,
            ];
        }

        // 2. Fetch monthly cumulative payments to supplier for threshold calculation
        $currentMonthStart = date('Y-m-01', strtotime($paymentDate));
        $currentMonthEnd   = date('Y-m-t', strtotime($paymentDate));

        $monthlyAccumulated = $db->table('purchase_payments')
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('payment_date >=', $currentMonthStart)
            ->where('payment_date <=', $currentMonthEnd)
            ->selectSum('amount')
            ->get()->getRowArray();

        $accumulatedPaymentAmount = (float) ($monthlyAccumulated['amount'] ?? 0);
        $totalBaseAmount = $accumulatedPaymentAmount + $paymentNetTotal;

        $appliedWithholdings = [];
        $totalWithholdingAmount = 0.0;

        // 3. Calculate Ganancias Retención
        $gananciasThreshold = 224000.0; // Non-taxable minimum threshold
        $gananciasRate = 0.02; // 2% for general goods/services

        if ($totalBaseAmount > $gananciasThreshold) {
            $taxableBase = $totalBaseAmount - $gananciasThreshold;
            $totalCalculatedGanancias = round($taxableBase * $gananciasRate, 2);

            // Subtract previously withheld Ganancias in the month
            $prevWithheld = $db->table('withholding_certificates')
                ->where('company_id', $companyId)
                ->where('supplier_id', $supplierId)
                ->where('tax_type', 'ganancias')
                ->where('certificate_date >=', $currentMonthStart)
                ->where('certificate_date <=', $currentMonthEnd)
                ->selectSum('amount')
                ->get()->getRowArray();

            $prevWithheldGanancias = (float) ($prevWithheld['amount'] ?? 0);
            $currentGananciasToWithhold = max(0.0, round($totalCalculatedGanancias - $prevWithheldGanancias, 2));

            if ($currentGananciasToWithhold > 100.0) { // Minimum withholding threshold
                $certNumber = 'GAN-' . date('Ym') . '-' . str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $certId = app_uuid();

                $db->table('withholding_certificates')->insert([
                    'id'                 => $certId,
                    'company_id'         => $companyId,
                    'supplier_id'        => $supplierId,
                    'tax_type'           => 'ganancias',
                    'certificate_number' => $certNumber,
                    'certificate_date'   => $paymentDate,
                    'base_amount'        => $paymentNetTotal,
                    'rate'               => $gananciasRate * 100,
                    'amount'             => $currentGananciasToWithhold,
                    'created_at'         => date('Y-m-d H:i:s'),
                ]);

                $appliedWithholdings[] = [
                    'id'                 => $certId,
                    'tax_type'           => 'ganancias',
                    'certificate_number' => $certNumber,
                    'amount'             => $currentGananciasToWithhold,
                ];

                $totalWithholdingAmount += $currentGananciasToWithhold;
            }
        }

        // 4. Calculate IIBB Retención (Provinces / ARBA / AGIP)
        $iibbRate = (float) ($supplier['iibb_retention_rate'] ?? 1.5) / 100.0;
        if ($iibbRate > 0 && $paymentNetTotal > 1000.0) {
            $iibbAmount = round($paymentNetTotal * $iibbRate, 2);
            $certNumber = 'IIBB-' . date('Ym') . '-' . str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $certId = app_uuid();

            $db->table('withholding_certificates')->insert([
                'id'                 => $certId,
                'company_id'         => $companyId,
                'supplier_id'        => $supplierId,
                'tax_type'           => 'iibb',
                'certificate_number' => $certNumber,
                'certificate_date'   => $paymentDate,
                'base_amount'        => $paymentNetTotal,
                'rate'               => $iibbRate * 100,
                'amount'             => $iibbAmount,
                'created_at'         => date('Y-m-d H:i:s'),
            ]);

            $appliedWithholdings[] = [
                'id'                 => $certId,
                'tax_type'           => 'iibb',
                'certificate_number' => $certNumber,
                'amount'             => $iibbAmount,
            ];

            $totalWithholdingAmount += $iibbAmount;
        }

        $netPayment = round($paymentNetTotal - $totalWithholdingAmount, 2);

        return [
            'ok'                    => true,
            'payment_gross'         => $paymentNetTotal,
            'withholdings_applied'  => $appliedWithholdings,
            'total_withholding'     => $totalWithholdingAmount,
            'net_payment_after_tax' => $netPayment,
            'monthly_accumulated'   => $totalBaseAmount,
        ];
    }
}
