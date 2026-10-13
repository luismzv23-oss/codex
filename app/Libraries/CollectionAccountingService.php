<?php

namespace App\Libraries;

use RuntimeException;

/** Called inside the collection transaction, with the company row locked. */
class CollectionAccountingService
{
    public const ACCOUNTS = [
        'cash' => 'Caja', 'bank' => 'Banco', 'receivable' => 'Clientes',
        'customer_advances' => 'Anticipos de clientes', 'card_clearing' => 'Tarjetas por acreditar',
        'check_clearing' => 'Cheques en cartera', 'qr_clearing' => 'Billeteras por acreditar',
    ];

    public static function paymentAccount(string $method): string
    {
        $map = ['cash' => 'cash', 'transfer' => 'bank', 'card' => 'card_clearing', 'check' => 'check_clearing', 'qr' => 'qr_clearing'];
        if (!isset($map[$method])) throw new RuntimeException('Medio no admitido para mover fondos.');
        return $map[$method];
    }

    public function account(string $companyId, string $key): string
    {
        $setting = db_connect()->table('company_settings')->where('company_id', $companyId)->where('key', 'account_' . $key)->get()->getRowArray();
        $id = (string) ($setting['value'] ?? '');
        $account = db_connect()->table('accounts')->where('company_id', $companyId)->where('id', $id)->where('active', 1)->where('is_group', 0)->where('accepts_entries', 1)->get()->getRowArray();
        if (!$account) throw new RuntimeException('Configura una cuenta activa de movimiento para ' . (self::ACCOUNTS[$key] ?? $key) . '.');
        return $id;
    }

    public function post(string $companyId, string $referenceType, string $referenceId, string $userId, string $debitKey, string $creditKey, float $amount, string $description): void
    {
        if ($amount <= 0) throw new RuntimeException('El asiento requiere un importe positivo.');
        $debit = $this->account($companyId, $debitKey);
        $credit = $this->account($companyId, $creditKey);
        if ($debit === $credit) throw new RuntimeException('Las cuentas de origen y destino deben ser diferentes.');
        $result = (new AccountingService())->createJournalEntry($companyId, [
            'reference_type' => $referenceType, 'reference_id' => $referenceId,
            'status' => 'posted', 'user_id' => $userId, 'description' => mb_substr($description, 0, 255),
        ], [
            ['account_id' => $debit, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $credit, 'debit' => 0, 'credit' => $amount],
        ]);
        if (empty($result['ok'])) throw new RuntimeException($result['error'] ?? 'No se pudo contabilizar.');
    }
}
