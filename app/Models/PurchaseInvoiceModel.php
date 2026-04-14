<?php

namespace App\Models;

class PurchaseInvoiceModel extends BaseUuidModel
{
    protected $table = 'purchase_invoices';
    protected $allowedFields = [
        'id', 'company_id', 'supplier_id', 'purchase_receipt_id', 'invoice_number', 'currency_code',
        'exchange_rate', 'subtotal', 'tax_total', 'total', 'issue_date', 'due_date', 'status', 'notes',
        'created_by', 'created_at', 'updated_at',
    ];
}
