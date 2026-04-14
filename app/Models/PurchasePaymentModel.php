<?php

namespace App\Models;

class PurchasePaymentModel extends BaseUuidModel
{
    protected $table = 'purchase_payments';
    protected $allowedFields = [
        'id', 'company_id', 'supplier_id', 'purchase_payable_id', 'payment_number', 'payment_method',
        'gateway_id', 'cash_check_id', 'currency_code', 'exchange_rate', 'amount', 'reference',
        'external_reference', 'paid_at', 'notes', 'created_by', 'created_at', 'updated_at',
    ];
}
