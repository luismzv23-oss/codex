<?php
namespace App\Libraries;

final class KardexDashboard
{
    public static function summarize(array $groups, int $products): array
    {
        $out = ['total'=>0,'products'=>$products,'ingreso'=>0,'egreso'=>0,'transferencia'=>0,'ajuste'=>0,'days'=>[],'updated'=>date('H:i:s')];
        foreach ($groups as $row) {
            $n = (int)$row['total'];
            $type = $row['movement_type'];
            $out['total'] += $n;
            if (in_array($type,['ingreso','egreso','transferencia','ajuste'],true)) { $out[$type] += $n; }
            $out['days'][$row['day']] = ($out['days'][$row['day']] ?? 0) + $n;
        }
        ksort($out['days']);
        if ($out['days'] && (strtotime(array_key_last($out['days'])) - strtotime(array_key_first($out['days']))) > 366 * 86400) {
            $monthly = [];
            foreach ($out['days'] as $date=>$n) { $month=substr($date,0,7); $monthly[$month]=($monthly[$month]??0)+$n; }
            $end = array_key_last($monthly);
            for ($month=array_key_first($monthly); $month <= $end; $month=date('Y-m',strtotime($month.'-01 +1 month'))) { $monthly[$month]=$monthly[$month]??0; }
            ksort($monthly);
            $out['days']=$monthly; $out['monthly']=true;
            return $out;
        }
        // Preserve calendar gaps instead of connecting consecutive activity days.
        if ($out['days']) {
            $end = array_key_last($out['days']);
            for ($day = array_key_first($out['days']); $day <= $end; $day = date('Y-m-d',strtotime($day.' +1 day'))) {
                $out['days'][$day] = $out['days'][$day] ?? 0;
            }
            ksort($out['days']);
        }
        return $out;
    }
}
