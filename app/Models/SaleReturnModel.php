<?php

namespace App\Models;

class SaleReturnModel extends BaseUuidModel
{
    protected $table         = 'sale_returns';
    protected $allowedFields = [
        'id',
        'sale_id',
        'warehouse_id',
        'return_number',
        'status',
        'credit_note_number',
        'total',
        'reason',
        'created_by',
        'created_at',
        'updated_at',
    ];
}
