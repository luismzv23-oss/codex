<?php

namespace App\Models;

class CashClosureModel extends BaseUuidModel
{
    protected $table = 'cash_closures';
    protected $allowedFields = [
        'id',
        'company_id',
        'cash_session_id',
        'closed_by',
        'closed_at',
        'opening_amount',
        'expected_amount',
        'actual_amount',
        'difference_amount',
        'notes',
        'created_at',
        'updated_at',
    ];
}
