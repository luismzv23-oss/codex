<?php
namespace App\Libraries\Core\Events;

final class DeliveryStatus
{
    public static function summary(string $companyId): array
    {
        $db=db_connect();
        $available=$db->tableExists('integration_deliveries') && $db->tableExists('integration_outbox') && $db->tableExists('integration_delivery_attempts');
        $counts=array_fill_keys(['pending','processing','retry','succeeded','dead','blocked'],0);
        $attention=[];
        if ($available) {
            foreach ($db->table('integration_deliveries')->select('status, COUNT(*) AS total',false)->where('company_id',$companyId)->groupBy('status')->get()->getResultArray() as $row) {
                if (array_key_exists($row['status'],$counts)) $counts[$row['status']]=(int)$row['total'];
            }
            $attention=$db->table('integration_deliveries d')
                ->select('d.id, d.consumer, d.status, d.attempts, d.available_at, d.lease_until, d.last_error, e.event, e.source_type, e.source_id')
                ->join('integration_outbox e','e.id = d.event_id AND e.company_id = d.company_id')
                ->where('d.company_id',$companyId)->whereIn('d.status',['retry','dead','blocked'])
                ->orderBy('d.available_at')->limit(20)->get()->getResultArray();
        }
        return ['available'=>$available,'company_id'=>$companyId,'deliveries'=>$counts,'attention'=>$attention];
    }
}
