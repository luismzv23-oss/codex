<?php

namespace App\Models;

class PurchaseReturnModel extends BaseUuidModel
{
    protected $table         = 'purchase_returns';
    protected $allowedFields = ['id', 'company_id', 'branch_id', 'supplier_id', 'purchase_receipt_id', 'warehouse_id', 'return_number', 'status', 'subtotal', 'tax_total', 'total', 'issued_at', 'reason', 'notes', 'created_by', 'created_at', 'updated_at'];
}
