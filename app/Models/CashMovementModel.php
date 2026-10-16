<?php

namespace App\Models;

class CashMovementModel extends BaseUuidModel
{
    protected $table = 'cash_movements';
    protected $allowedFields = [
        'payment_method_id', 'payment_method_code', 'payment_method_name', 'payment_method_snapshot', 'payment_data', 'currency_code', 'status', 'confirmed_by', 'confirmed_at', 'confirmation_evidence', 'request_key', 'request_hash', 'branch_id', 'point_of_sale_id', 'exchange_rate',
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
