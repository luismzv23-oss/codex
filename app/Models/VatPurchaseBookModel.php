<?php

namespace App\Models;

class VatPurchaseBookModel extends BaseUuidModel
{
    protected $table = 'vat_purchase_books';

    protected $allowedFields = [
        'id',
        'company_id',
        'source_type',
        'source_id',
        'purchase_receipt_id',
        'supplier_id',
        'document_number',
        'supplier_document',
        'issue_date',
        'supplier_name',
        'supplier_tax_id',
        'supplier_vat_condition',
        'net_amount',
        'tax_amount',
        'total_amount',
        'currency_code',
        'status',
        'created_at',
        'updated_at',
    ];
}
