<?php

namespace App\Models;

class InventorySettingModel extends BaseUuidModel
{
    protected $table         = 'inventory_settings';
    protected $allowedFields = [
        'id',
        'company_id',
        'alert_email',
        'unusual_movement_threshold',
        'no_rotation_days',
        'allow_negative_stock',
        'low_stock_alerts',
        'internal_notifications',
        'email_notifications',
        'valuation_method',
        'negative_stock_scope',
        'allow_negative_on_sales',
        'allow_negative_on_transfers',
        'allow_negative_on_adjustments',
        'created_at',
        'updated_at',
    ];
}
