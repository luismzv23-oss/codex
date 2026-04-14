<?php

namespace App\Models;

class PurchaseOrderModel extends BaseUuidModel
{
    protected $table         = 'purchase_orders';
    protected $allowedFields = ['id', 'company_id', 'branch_id', 'supplier_id', 'warehouse_id', 'order_number', 'status', 'currency_code', 'subtotal', 'tax_total', 'total', 'issued_at', 'expected_at', 'approved_at', 'approved_by', 'notes', 'created_by', 'created_at', 'updated_at'];
}
