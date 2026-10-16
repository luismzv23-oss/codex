<?php

namespace App\Models;

class PurchaseInvoiceModel extends BaseUuidModel
{
    protected $table = 'purchase_invoices';
    protected $allowedFields = [
        'document_type','point_of_sale','invoice_sequence','supplier_tax_id','identity_hash','import_id','request_key','request_hash','purchase_kind','adjustments_json','adjustment_total','reviewed_total',
        'id', 'company_id', 'supplier_id', 'purchase_receipt_id', 'invoice_number', 'currency_code',
        'exchange_rate', 'subtotal', 'tax_total', 'total', 'issue_date', 'due_date', 'status', 'notes',
        'created_by', 'created_at', 'updated_at',
    ];
}
