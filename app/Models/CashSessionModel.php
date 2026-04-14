<?php

namespace App\Models;

class CashSessionModel extends BaseUuidModel
{
    protected $table = 'cash_sessions';
    protected $allowedFields = [
        'id',
        'company_id',
        'cash_register_id',
        'status',
        'opened_by',
        'opened_at',
        'opening_amount',
        'expected_closing_amount',
        'actual_closing_amount',
        'difference_amount',
        'closed_by',
        'closed_at',
        'notes',
        'created_at',
        'updated_at',
    ];
}
