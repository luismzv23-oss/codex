<?php

namespace App\Models;

class SalesReceiptModel extends BaseUuidModel
{
    protected $table = 'sales_receipts';
    protected $allowedFields = [
        'id',
        'company_id',
        'customer_id',
        'cash_register_id',
        'cash_session_id',
        'receipt_number',
        'issue_date',
        'currency_code',
        'payment_method',
        'gateway_id',
        'cash_check_id',
        'total_amount',
        'reference',
        'external_reference',
        'notes',
        'created_by',
        'created_at',
        'updated_at',
    ];
}
