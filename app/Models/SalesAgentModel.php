<?php

namespace App\Models;

class SalesAgentModel extends BaseUuidModel
{
    protected $table = 'sales_agents';

    protected $allowedFields = [
        'id', 'company_id', 'name', 'code', 'email', 'phone', 'commission_rate', 'notes', 'active', 'created_at', 'updated_at',
    ];
}
