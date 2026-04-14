<?php

namespace App\Models;

class HardwareLogModel extends BaseUuidModel
{
    protected $table = 'hardware_logs';
    protected $allowedFields = [
        'id', 'company_id', 'channel', 'device_type', 'event_type', 'status', 'reference_type',
        'reference_id', 'payload_json', 'message', 'created_at', 'updated_at',
    ];
}
