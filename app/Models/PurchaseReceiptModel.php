<?php

namespace App\Models;

class PurchaseReceiptModel extends BaseUuidModel
{
    protected $table         = 'purchase_receipts';
    protected $allowedFields = ['id', 'company_id', 'branch_id', 'supplier_id', 'purchase_order_id', 'warehouse_id', 'receipt_number', 'supplier_document', 'status', 'currency_code', 'subtotal', 'tax_total', 'total', 'issued_at', 'received_at', 'notes', 'created_by', 'created_at', 'updated_at'];
}
