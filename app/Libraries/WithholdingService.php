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
        ?string $supplierId = null,
        ?array $snapshot = null
    ): array {
        $db = db_connect();
        $configs = $snapshot ?? $db->table('tax_withholdings')
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->whereIn('applies_to', [$sourceType === 'sale' ? 'sales' : 'purchases', 'both'])
            ->get()
            ->getResultArray();

        $applied = [];
        $totalWithheld = 0;

        foreach ($configs as $config) {
            if (($config['company_id'] ?? null) !== $companyId) throw new \RuntimeException('Configuración tributaria de otra empresa.');
            $minAmount = (float) ($config['min_amount'] ?? 0);

            if ($paymentAmount < $minAmount) {
                continue;
            }

            $rate   = (float) ($config['rate'] ?? 0);
            $amount = round($paymentAmount * ($rate / 100), 2);

            if ($amount <= 0) {
                continue;
            }

            $existing = $db->table('tax_withholdings_applied')->where('company_id',$companyId)->where('source_type',$sourceType)->where('source_id',$sourceId)->where('withholding_id',$config['id'])->get()->getRowArray();
            if ($existing) {
                self::assertSameTax($existing,$paymentAmount,$rate,$amount);
                $applied[]=$existing; $totalWithheld += $amount; continue;
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

            if (!$db->table('tax_withholdings_applied')->insert($record)) throw new \RuntimeException('No se pudo guardar la retención.');

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
        ?string $customerId = null,
        ?array $snapshot = null
    ): array {
        $db = db_connect();
        $configs = $snapshot ?? $db->table('tax_perceptions')
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->get()
            ->getResultArray();

        $applied = [];
        $totalPerceived = 0;

        foreach ($configs as $config) {
            if (($config['company_id'] ?? null) !== $companyId) throw new \RuntimeException('Configuración tributaria de otra empresa.');
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

            $existing = $db->table('tax_perceptions_applied')->where('company_id',$companyId)->where('source_type',$sourceType)->where('source_id',$sourceId)->where('perception_id',$config['id'])->get()->getRowArray();
            if ($existing) {
                self::assertSameTax($existing,$saleAmount,$rate,$amount);
                $applied[]=$existing; $totalPerceived += $amount; continue;
            }
            if (!$db->table('tax_perceptions_applied')->insert($record)) throw new \RuntimeException('No se pudo guardar la percepción.');

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

    private static function assertSameTax(array $existing,float $base,float $rate,float $amount): void
    {
        if (round((float)$existing['base_amount'],2)!==round($base,2) || (float)$existing['rate']!==$rate || round((float)$existing['amount'],2)!==round($amount,2)) {
            throw new \RuntimeException('El origen ya tiene un impuesto aplicado con otros valores.');
        }
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
            ->orderBy('certificate_number', 'DESC')
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
