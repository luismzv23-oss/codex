<?php

namespace App\Models;

class SalesZoneModel extends BaseUuidModel
{
    protected $table = 'sales_zones';

    protected $allowedFields = [
        'id', 'company_id', 'name', 'code', 'region', 'description', 'active', 'created_at', 'updated_at',
    ];
}
