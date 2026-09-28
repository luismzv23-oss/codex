<?php
namespace App\Libraries;
use App\Models\CompanyPaymentMethodModel;
use RuntimeException;

class KioskPayments
{
    public function prepare(string $companyId, array $input): array
    {
        if (! $input || count($input) > 20) { throw new RuntimeException('Agrega entre 1 y 20 medios de pago.'); }
        $rows = [];
        foreach ($input as $line) {
            if (! is_array($line) || ! is_string($line['payment_method_id'] ?? null)) { throw new RuntimeException('Medio de pago inválido.'); }
            $method = (new CompanyPaymentMethodModel())->where('company_id', $companyId)->where('active', 1)->find($line['payment_method_id']);
            if (! $method) { throw new RuntimeException('Medio de pago no disponible en la empresa.'); }
            $rows[] = self::line($method, $line);
        }
        return $rows;
    }

    public static function line(array $method, array $input): array
    {
        $integrity = new PaymentIntegrityService();
        $base = $integrity->cents($input['base_amount'] ?? '');
        if ($base <= 0) { throw new RuntimeException('Cada importe asignado debe ser mayor que cero.'); }
        $surcharge = PaymentSurcharge::calculate($base / 100, (float) $method['percentage']);
        $type = $method['type'] === 'wallet' ? 'qr' : $method['type'];
        $received = $type === 'cash' ? $integrity->cents($input['received_amount'] ?? '') : (int) round($surcharge['total'] * 100);
        $due = (int) round($surcharge['total'] * 100);
        if ($received < $due) { throw new RuntimeException('El efectivo entregado no cubre el importe de esa línea.'); }
        $reference = $input['reference'] ?? '';
        if (! is_string($reference) || mb_strlen($reference) > 120) { throw new RuntimeException('Referencia inválida.'); }
        // Kiosk records transfers as pending; evidence is required when confirming them.
        $row = $integrity->parse([['payment_method'=>$type,'amount'=>number_format($surcharge['total'],2,'.',''),'reference'=>$reference]], false)[0];
        return array_merge($row, [
            'payment_method_id'=>$method['id'], 'payment_method_code'=>$method['code'],
            'base_amount'=>$base / 100, 'surcharge_rate'=>$surcharge['rate'], 'surcharge_amount'=>$surcharge['amount'],
            'received_amount'=>$received / 100, 'change_amount'=>($received - $due) / 100,
        ]);
    }

    public static function assertAllocated(array $rows, float $base): void
    {
        $allocated = array_sum(array_map(static fn($row) => (int) round($row['base_amount'] * 100), $rows));
        if ($allocated !== (int) round($base * 100)) { throw new RuntimeException('Los importes asignados deben cubrir exactamente el total con impuestos y descuentos, antes de recargos.'); }
    }
}
