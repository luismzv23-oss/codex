<?php

namespace App\Models;

class PurchaseInvoiceItemModel extends BaseUuidModel
{
    protected $table = 'purchase_invoice_items';
    protected $allowedFields = [
        'id', 'purchase_invoice_id', 'product_id', 'description', 'quantity', 'unit_cost', 'tax_rate',
        'tax_amount', 'line_total', 'created_at', 'updated_at',
    ];
}
