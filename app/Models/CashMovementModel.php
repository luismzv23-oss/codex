<?php

namespace App\Models;

class CashMovementModel extends BaseUuidModel
{
    protected $table = 'cash_movements';
    protected $allowedFields = [
        'id',
        'company_id',
        'cash_register_id',
        'cash_session_id',
        'movement_type',
        'payment_method',
        'gateway_id',
        'cash_check_id',
        'amount',
        'reference_type',
        'reference_id',
        'reference_number',
        'external_reference',
        'reconciliation_status',
        'occurred_at',
        'notes',
        'created_by',
        'created_at',
        'updated_at',
    ];
}
