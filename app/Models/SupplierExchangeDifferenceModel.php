<?php

namespace App\Models;

class SupplierExchangeDifferenceModel extends BaseUuidModel
{
    protected $table = 'supplier_exchange_differences';
    protected $allowedFields = [
        'id', 'company_id', 'purchase_invoice_id', 'purchase_payment_id', 'currency_code',
        'base_rate', 'settlement_rate', 'difference_amount', 'notes', 'created_at', 'updated_at',
    ];
}
