<?php
namespace App\Libraries;

use RuntimeException;

/** Shared validation; client supplied statuses are never trusted. */
class PaymentIntegrityService
{
    public const METHODS = ['cash', 'card', 'transfer', 'check', 'qr'];

    public function cents($value): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d+(?:\.\d{1,2})?$/D', (string) $value)
            || ! is_finite((float) $value) || (float) $value > 999999999999.99) {
            throw new RuntimeException('El importe debe ser un decimal positivo con hasta dos decimales.');
        }
        return (int) round((float) $value * 100);
    }

    public function parse(array $payments): array
    {
        $rows = [];
        foreach ($payments as $payment) {
            if (! is_array($payment)) { throw new RuntimeException('Detalle de pago inválido.'); }
            $cents = $this->cents($payment['amount'] ?? 0);
            if ($cents === 0) { continue; }
            $method = trim((string) ($payment['payment_method'] ?? ''));
            if (! in_array($method, self::METHODS, true)) { throw new RuntimeException('Medio de pago inválido. Desglosa los pagos combinados en líneas.'); }
            $reference = trim((string) ($payment['reference'] ?? ''));
            $external = trim((string) ($payment['external_reference'] ?? ''));
            if ($method === 'transfer' && $reference === '' && $external === '') { throw new RuntimeException('La transferencia requiere una referencia verificable.'); }
            $date = trim((string) ($payment['paid_at'] ?? ''));
            if ($date !== '' && strtotime($date) === false) { throw new RuntimeException('Fecha de pago inválida.'); }
            $rows[] = ['payment_method' => $method, 'amount' => $cents / 100,
                'gateway_id' => trim((string) ($payment['gateway_id'] ?? '')) ?: null,
                'cash_check_id' => trim((string) ($payment['cash_check_id'] ?? '')) ?: null,
                'external_reference' => $external ?: null, 'reference' => $reference,
                'status' => $method === 'transfer' ? 'pending' : 'registered',
                'paid_at' => $date !== '' ? date('Y-m-d H:i:s', strtotime($date)) : null,
                'notes' => trim((string) ($payment['notes'] ?? ''))];
        }
        return $rows;
    }

    public function validateReferences(string $companyId, array $rows): void
    {
        foreach ($rows as $row) {
            if (! empty($row['gateway_id']) && ! db_connect()->table('cash_payment_gateways')->where('id', $row['gateway_id'])->where('company_id', $companyId)->where('active', 1)->countAllResults()) {
                throw new RuntimeException('La pasarela no está activa en esta empresa.');
            }
            if (! empty($row['cash_check_id'])) {
                if ($row['payment_method'] !== 'check' || ! db_connect()->table('cash_checks')->where('id', $row['cash_check_id'])->where('company_id', $companyId)->whereIn('status', ['portfolio', 'received', 'cartera'])->countAllResults()) {
                    throw new RuntimeException('Cheque no disponible para este cobro.');
                }
            }
        }
    }

    public static function settled(array $payment): bool
    {
        return in_array($payment['status'] ?? 'registered', ['registered', 'confirmed'], true);
    }

    public static function paid(array $payments): float
    {
        return round(array_sum(array_map(static fn(array $p): float => self::settled($p) ? (float) $p['amount'] : 0, $payments)), 2);
    }
}
