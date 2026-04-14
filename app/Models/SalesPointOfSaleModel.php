<?php

namespace App\Models;

class SalesPointOfSaleModel extends BaseUuidModel
{
    protected $table         = 'sales_points_of_sale';
    protected $allowedFields = [
        'id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'document_type_id',
        'name',
        'code',
        'channel',
        'active',
        'created_at',
        'updated_at',
    ];
}
