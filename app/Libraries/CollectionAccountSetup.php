<?php
declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/** Provision missing mappings without changing existing accounts or their balances. */
final class CollectionAccountSetup
{
    public function configure(string $companyId, bool $apply = false): array
    {
        $db = db_connect();
        if (!$db->transBegin()) throw new RuntimeException('No se pudo iniciar la configuracion contable.');
        try {
            $sql = 'SELECT * FROM ' . $db->protectIdentifiers($db->prefixTable('companies')) . ' WHERE id = ?';
            if ($db->DBDriver === 'MySQLi') $sql .= ' FOR UPDATE';
            $company = $db->query($sql, [$companyId])->getRowArray();
            if (!$company) throw new RuntimeException('Empresa no disponible.');
            $mapping = [];
            $report = [];
            $names = ['cash' => 'Caja', 'bank' => 'Banco Cuenta Corriente', 'receivable' => 'Deudores por Ventas'];
            foreach (CollectionAccountingService::ACCOUNTS as $key => $label) {
                $type = $key === 'customer_advances' ? 'liability' : 'asset';
                $setting = $db->table('company_settings')->where('company_id', $companyId)->where('key', 'account_' . $key)->get()->getRowArray();
                $account = null;
                if (!empty($setting['value'])) {
                    $account = $db->table('accounts')->where('company_id', $companyId)->where('id', $setting['value'])->get()->getRowArray();
                    if (!$account || !$account['active'] || $account['is_group'] || !$account['accepts_entries'] || $account['account_type'] !== $type || $account['currency_code'] !== $company['currency_code']) {
                        throw new RuntimeException('Revisa la asignacion existente de ' . $label . ' en ' . $company['name'] . '. No se modifico.');
                    }
                } else {
                    $matches = $db->table('accounts')->where('company_id', $companyId)->where('name', $names[$key] ?? $label)
                        ->where('account_type', $type)->where('active', 1)->where('is_group', 0)->where('accepts_entries', 1)->where('currency_code', $company['currency_code'])->get()->getResultArray();
                    if (count($matches) > 1) throw new RuntimeException('Hay varias cuentas para ' . $label . '; asigna una explicitamente.');
                    $account = $matches[0] ?? null;
                }
                $action = $account ? (!empty($setting['value']) ? 'preserved' : 'mapped') : 'created';
                if (!$account) {
                    $prefix = $type === 'liability' ? '2.1' : '1.1';
                    $parent = $db->table('accounts')->where('company_id', $companyId)->where('code', $prefix)->where('is_group', 1)->get()->getRowArray();
                    $suffix = 90;
                    do { $code = $prefix . '.' . $suffix++; } while ($db->table('accounts')->where('company_id', $companyId)->where('code', $code)->countAllResults());
                    $account = ['id' => app_uuid(), 'company_id' => $companyId, 'parent_id' => $parent['id'] ?? null,
                        'code' => $code, 'name' => $names[$key] ?? $label, 'account_type' => $type, 'is_group' => 0,
                        'level' => $parent ? (int) $parent['level'] + 1 : 3, 'accepts_entries' => 1, 'active' => 1,
                        'currency_code' => $company['currency_code'], 'opening_balance' => 0, 'created_at' => date('Y-m-d H:i:s')];
                    // Insert during planning too, then roll back, so allocated codes stay distinct.
                    if (!$db->table('accounts')->insert($account)) throw new RuntimeException('No se pudo preparar la cuenta.');
                }
                $mapping[$key] = $account['id'];
                $report[] = ['concept' => $key, 'code' => $account['code'], 'name' => $account['name'], 'action' => $action];
            }
            (new SalesCollectionService())->configureAccounts($companyId, $mapping);
            if (!$db->transStatus()) throw new RuntimeException('No se pudo configurar la contabilidad de cobranzas.');
            $completed = $apply ? $db->transCommit() : $db->transRollback();
            if (!$completed) throw new RuntimeException('No se pudo finalizar la configuracion contable.');
            return ['company' => $company['name'], 'company_id' => $companyId, 'applied' => $apply, 'accounts' => $report];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
