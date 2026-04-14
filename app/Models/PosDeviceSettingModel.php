<?php

namespace App\Models;

class PosDeviceSettingModel extends BaseUuidModel
{
    protected $table = 'pos_device_settings';
    protected $allowedFields = [
        'id', 'company_id', 'channel', 'device_type', 'device_name', 'device_code', 'settings_json',
        'active', 'created_at', 'updated_at',
    ];
}
