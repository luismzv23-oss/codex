<?php
namespace App\Libraries;

final class PurchasesDashboard
{
    public static function filters(array $input): array
    {
        $date = static function ($value, string $fallback): string {
            $value = is_string($value) ? $value : '';
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $parsed && $parsed->format('Y-m-d') === $value ? $value : $fallback;
        };
        $from = $date($input['from'] ?? '', date('Y-m-01'));
        $to = $date($input['to'] ?? '', date('Y-m-d'));
        return ['from'=>min($from,$to), 'to'=>max($from,$to), 'supplier_id'=>is_string($input['supplier_id'] ?? null) ? $input['supplier_id'] : ''];
    }

    public static function prepare(array $data, array $filters, ?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $data['supplierOptions'] = $data['suppliers'];
        foreach (['suppliers'=>null,'orders'=>'issued_at','receipts'=>'received_at','payables'=>null,'invoices'=>'issue_date','creditNotes'=>'issue_date','costHistory'=>'observed_at'] as $key=>$dateField) {
            $data[$key] = array_values(array_filter($data[$key], static function ($row) use ($key,$dateField,$filters) {
                if ($filters['supplier_id'] !== '' && ($row[$key === 'suppliers' ? 'id' : 'supplier_id'] ?? '') !== $filters['supplier_id']) return false;
                if (!$dateField) return true;
                $day = substr((string)($row[$dateField] ?? ''),0,10);
                return $day >= $filters['from'] && $day <= $filters['to'];
            }));
        }
        $count = static fn($rows,$status) => count(array_filter($rows,static fn($row)=>in_array($row['status'] ?? '',$status,true)));
        $pending = array_filter($data['payables'],static fn($row)=>in_array($row['status'] ?? '',['pending','partial'],true) && (float)$row['balance_amount'] > 0);
        $balances = [];
        foreach ($pending as $row) {
            $currency = ($row['currency_code'] ?? '') ?: 'Sin moneda';
            $balances[$currency] = ($balances[$currency] ?? 0) + (float)$row['balance_amount'];
        }
        ksort($balances);
        $data['summary'] = [
            'suppliers'=>count(array_filter($data['suppliers'],static fn($row)=>(int)($row['active'] ?? 0) === 1)),
            'orders_draft'=>$count($data['orders'],['draft']),
            'orders_approved'=>$count($data['orders'],['approved','received_partial']),
            'receipts'=>count($data['receipts']),
            'payables_pending'=>count($pending),
            'overdue'=>count(array_filter($pending,static fn($row)=>!empty($row['due_date']) && substr($row['due_date'],0,10)<$today)),
            'balances'=>$balances,
        ];
        $data['orderStates'] = [];
        foreach ($data['orders'] as $row) {
            $status = $row['status'] ?? 'draft';
            $data['orderStates'][$status] = ($data['orderStates'][$status] ?? 0) + 1;
        }
        $data['filters'] = $filters;
        return $data;
    }
}
