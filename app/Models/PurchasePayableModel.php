<?php

namespace App\Models;

class PurchasePayableModel extends BaseUuidModel
{
    protected $table         = 'purchase_payables';
    protected $allowedFields = ['id', 'company_id', 'supplier_id', 'purchase_receipt_id', 'payable_number', 'status', 'currency_code', 'total_amount', 'paid_amount', 'balance_amount', 'due_date', 'created_at', 'updated_at'];
}
