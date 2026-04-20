<?php

namespace App\Libraries;

use App\Models\BaseUuidModel;

/**
 * WithholdingService — Calculates and applies tax withholdings (retenciones)
 * and perceptions (percepciones) per Argentine tax law.
 */
class WithholdingService
{
    /**
     * Calculate withholdings for a payment to a supplier.
     *
     * @param string $companyId
     * @param float  $paymentAmount  Gross payment amount
     * @param string $sourceType     'purchase_payment'
     * @param string $sourceId       UUID of the payment
     * @param string|null $supplierId
     * @return array [applied[], total_withheld]
     */
    public function calculateWithholdings(
        string  $companyId,
        float   $paymentAmount,
        string  $sourceType,
        string  $sourceId,
        ?string $supplierId = null
    ): array {
        $db = db_connect();
        $configs = $db->table('tax_withholdings')
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->whereIn('applies_to', [$sourceType === 'sale' ? 'sales' : 'purchases', 'both'])
            ->get()
            ->getResultArray();

        $applied = [];
        $totalWithheld = 0;

        foreach ($configs as $config) {
            $minAmount = (float) ($config['min_amount'] ?? 0);

            if ($paymentAmount < $minAmount) {
                continue;
            }

            $rate   = (float) ($config['rate'] ?? 0);
            $amount = round($paymentAmount * ($rate / 100), 2);

            if ($amount <= 0) {
                continue;
            }

            $certNumber = $this->generateCertificateNumber($companyId, $config['tax_type']);

            $record = [
                'id'                 => app_uuid(),
                'company_id'         => $companyId,
                'withholding_id'     => $config['id'],
                'source_type'        => $sourceType,
                'source_id'          => $sourceId,
                'supplier_id'        => $supplierId,
                'base_amount'        => $paymentAmount,
                'rate'               => $rate,
                'amount'             => $amount,
                'certificate_number' => $certNumber,
                'applied_at'         => date('Y-m-d H:i:s'),
            ];

            $db->table('tax_withholdings_applied')->insert($record);

            $applied[] = array_merge($record, [
                'tax_type' => $config['tax_type'],
                'name'     => $config['name'],
            ]);
            $totalWithheld += $amount;
        }

        return [
            'applied'        => $applied,
            'total_withheld' => $totalWithheld,
            'net_payment'    => $paymentAmount - $totalWithheld,
        ];
    }

    /**
     * Calculate perceptions for a sale.
     */
    public function calculatePerceptions(
        string  $companyId,
        float   $saleAmount,
        string  $sourceType,
        string  $sourceId,
        ?string $customerId = null
    ): array {
        $db = db_connect();
        $configs = $db->table('tax_perceptions')
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->get()
            ->getResultArray();

        $applied = [];
        $totalPerceived = 0;

        foreach ($configs as $config) {
            $rate   = (float) ($config['rate'] ?? 0);
            $amount = round($saleAmount * ($rate / 100), 2);

            if ($amount <= 0) {
                continue;
            }

            $record = [
                'id'             => app_uuid(),
                'company_id'     => $companyId,
                'perception_id'  => $config['id'],
                'source_type'    => $sourceType,
                'source_id'      => $sourceId,
                'customer_id'    => $customerId,
                'base_amount'    => $saleAmount,
                'rate'           => $rate,
                'amount'         => $amount,
                'applied_at'     => date('Y-m-d H:i:s'),
            ];

            $db->table('tax_perceptions_applied')->insert($record);

            $applied[] = array_merge($record, [
                'tax_type' => $config['tax_type'],
                'name'     => $config['name'],
            ]);
            $totalPerceived += $amount;
        }

        return [
            'applied'         => $applied,
            'total_perceived' => $totalPerceived,
            'total_with_perception' => $saleAmount + $totalPerceived,
        ];
    }

    /**
     * Generate a sequential certificate number for a withholding type.
     */
    private function generateCertificateNumber(string $companyId, string $taxType): string
    {
        $prefix = match ($taxType) {
            'iva'       => 'RET-IVA',
            'ganancias' => 'RET-GAN',
            'iibb'      => 'RET-IIBB',
            'suss'      => 'RET-SUSS',
            default     => 'RET',
        };

        $db = db_connect();
        $lastCert = $db->table('tax_withholdings_applied')
            ->select('certificate_number')
            ->where('company_id', $companyId)
            ->like('certificate_number', $prefix, 'after')
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $lastNum = 0;
        if ($lastCert) {
            preg_match('/(\d+)$/', $lastCert['certificate_number'] ?? '', $m);
            $lastNum = (int) ($m[1] ?? 0);
        }

        return $prefix . '-' . str_pad((string) ($lastNum + 1), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Get withholdings report for a period.
     */
    public function withholdingsReport(string $companyId, string $from, string $to): array
    {
        return db_connect()->table('tax_withholdings_applied twa')
            ->select('twa.*, tw.name AS withholding_name, tw.tax_type')
            ->join('tax_withholdings tw', 'tw.id = twa.withholding_id', 'left')
            ->where('twa.company_id', $companyId)
            ->where('twa.applied_at >=', $from)
            ->where('twa.applied_at <=', $to . ' 23:59:59')
            ->orderBy('twa.applied_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get perceptions report for a period.
     */
    public function perceptionsReport(string $companyId, string $from, string $to): array
    {
        return db_connect()->table('tax_perceptions_applied tpa')
            ->select('tpa.*, tp.name AS perception_name, tp.tax_type')
            ->join('tax_perceptions tp', 'tp.id = tpa.perception_id', 'left')
            ->where('tpa.company_id', $companyId)
            ->where('tpa.applied_at >=', $from)
            ->where('tpa.applied_at <=', $to . ' 23:59:59')
            ->orderBy('tpa.applied_at', 'ASC')
            ->get()
            ->getResultArray();
    }
}
