<?php

namespace App\Models;

class SalesCommissionModel extends BaseUuidModel
{
    protected $table = 'sales_commissions';
    protected $allowedFields = [
        'id',
        'company_id',
        'sale_id',
        'sales_agent_id',
        'base_amount',
        'rate',
        'commission_amount',
        'status',
        'notes',
        'liquidated_at',
        'created_at',
        'updated_at',
    ];
}
