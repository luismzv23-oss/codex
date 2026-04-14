<?php

namespace App\Models;

class SalesArcaEventModel extends BaseUuidModel
{
    protected $table = 'sales_arca_events';

    protected $allowedFields = [
        'id',
        'company_id',
        'sale_id',
        'service_slug',
        'event_type',
        'environment',
        'request_payload',
        'response_payload',
        'status',
        'result_code',
        'message',
        'performed_by',
        'performed_at',
        'created_at',
        'updated_at',
    ];
}
