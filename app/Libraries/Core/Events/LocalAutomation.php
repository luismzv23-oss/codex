<?php
namespace App\Libraries\Core\Events;

use App\Libraries\WithholdingService;
use RuntimeException;

final class LocalAutomation
{
    public static function perceptions(array $event,array $payload): void
    {
        self::source('sales',$event['company_id'],$payload['sale_id']);
        if ($payload['category']==='delivery_note') return;
        (new WithholdingService())->calculatePerceptions($event['company_id'],(float)$payload['total'],'sale',$payload['sale_id'],$payload['customer_id'],$payload['tax_configs']);
    }

    public static function withholdings(array $event,array $payload): void
    {
        self::source('purchase_payments',$event['company_id'],$payload['payment_id']);
        (new WithholdingService())->calculateWithholdings($event['company_id'],(float)$payload['amount'],'purchase_payment',$payload['payment_id'],$payload['supplier_id'],$payload['tax_configs']);
    }

    private static function source(string $table,string $companyId,string $id): void
    {
        if (!db_connect()->table($table)->where('company_id',$companyId)->where('id',$id)->countAllResults()) throw new RuntimeException('Origen no disponible.');
    }

    public static function evidence(array $event,array $payload): void
    {
        // Describes only a real completed action; never claims stock, CAE or funds were applied here.
        $actions=[['action'=>'integration_event_recorded','event_id'=>$event['id'],'source_type'=>$event['source_type'],'source_id'=>$event['source_id']]];
        if (!db_connect()->table('automation_log')->insert([
            'id'=>app_uuid(),'company_id'=>$event['company_id'],'event'=>$event['event'],
            'actions'=>json_encode($actions,JSON_THROW_ON_ERROR),'actions_count'=>1,'created_at'=>date('Y-m-d H:i:s'),
        ])) throw new RuntimeException('No se pudo registrar la evidencia.');
    }
}
