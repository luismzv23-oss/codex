<?php
namespace App\Libraries;

final class CashDashboard
{
    public static function period($from, $to): array
    {
        $valid = static function ($date): bool {
            if (!is_string($date)) return false;
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            return $parsed && $parsed->format('Y-m-d') === $date;
        };
        $to = $valid($to) ? $to : date('Y-m-d');
        $from = $valid($from) ? $from : date('Y-m-d', strtotime($to.' -6 days'));
        if ($from > $to) [$from, $to] = [$to, $from];
        if (strtotime($to) - strtotime($from) > 366 * 86400) $from = date('Y-m-d', strtotime($to.' -366 days'));
        return [$from, $to];
    }

    public function load(string $company, array $registerIds, string $from, string $to, ?string $ownerId = null): array
    {
        $db = db_connect();
        $scope = static function ($query, bool $sessions = false) use ($company, $registerIds, $ownerId, $db) {
            $query->where('company_id', $company);
            if ($ownerId !== null) {
                if ($sessions) $query->where('opened_by', $ownerId);
                else $query->whereIn('cash_session_id', $db->table('cash_sessions')->select('id')->where('company_id', $company)->where('opened_by', $ownerId));
            }
            return $registerIds ? $query->whereIn('cash_register_id', $registerIds) : $query->where('1 = 0', null, false);
        };
        $movements = $scope($db->table('cash_movements'))
            ->where('occurred_at >=', $from.' 00:00:00')->where('occurred_at <', date('Y-m-d', strtotime($to.' +1 day')).' 00:00:00')
            ->orderBy('occurred_at')->get()->getResultArray();
        $data = ['income'=>0,'expense'=>0,'count'=>0,'pending'=>0,'days'=>[],'methods'=>[], 'from'=>$from,'to'=>$to];
        for ($day = $from; $day <= $to; $day = date('Y-m-d', strtotime($day.' +1 day'))) $data['days'][$day] = ['income'=>0,'expense'=>0];
        foreach ($movements as $row) {
            if (($row['status'] ?? 'posted')!=='posted') continue;
            $amount=round((float)$row['amount']*(float)($row['exchange_rate'] ?? 1),2);
            $income=max(0,$amount); $expense=max(0,-$amount); $day=substr($row['occurred_at'],0,10);
            $data['income']+=$income; $data['expense']+=$expense; $data['count']++;
            if (($row['reconciliation_status'] ?? '')==='pending') $data['pending']++;
            $data['days'][$day]['income']+=$income; $data['days'][$day]['expense']+=$expense;
            $method=PaymentIntegrityService::label($row);
            if (!empty($row['payment_method_id'])) $method.=' ['.($row['payment_method_code'] ?? $row['payment_method_id']).']';
            $data['methods'][$method]=($data['methods'][$method] ?? 0)+$income;
        }
        arsort($data['methods']);
        $data['net'] = round($data['income'] - $data['expense'], 2);
        $data['open'] = $scope($db->table('cash_sessions'), true)->where('status', 'open')->countAllResults();
        return $data;
    }
}
