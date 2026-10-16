<?php

namespace App\Models;

class CashReconciliationModel extends BaseUuidModel
{
    protected $table = 'cash_reconciliations';
    protected $allowedFields = [
        'payment_method_id', 'payment_method_code', 'payment_method_name', 'payment_method_snapshot', 'payment_data', 'currency_code',
        'id', 'company_id', 'cash_session_id', 'payment_method', 'expected_amount', 'actual_amount',
        'difference_amount', 'status', 'notes', 'created_by', 'created_at', 'updated_at',
    ];
}
