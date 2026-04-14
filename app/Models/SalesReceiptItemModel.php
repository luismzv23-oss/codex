<?php

namespace App\Models;

class SalesReceiptItemModel extends BaseUuidModel
{
    protected $table = 'sales_receipt_items';
    protected $allowedFields = [
        'id',
        'sales_receipt_id',
        'sales_receivable_id',
        'sale_id',
        'document_number',
        'applied_amount',
        'created_at',
        'updated_at',
    ];
}
