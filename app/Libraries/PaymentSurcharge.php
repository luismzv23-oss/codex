<?php
namespace App\Libraries;

class PaymentSurcharge
{
    public static function calculate(float $base, float $percentage): array
    {
        if (! is_finite($base) || $base < 0 || ! is_finite($percentage) || $percentage < 0 || $percentage > 100) {
            throw new \RuntimeException('Base o porcentaje de recargo inválido.');
        }
        $baseCents = (int) round($base * 100);
        $rate = (int) round($percentage * 100);
        $surchargeCents = (int) round($baseCents * $rate / 10000);
        return ['rate' => $rate / 100, 'amount' => $surchargeCents / 100, 'total' => ($baseCents + $surchargeCents) / 100];
    }
}
