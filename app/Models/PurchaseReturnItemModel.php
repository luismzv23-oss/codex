<?php

namespace App\Models;

class PurchaseReturnItemModel extends BaseUuidModel
{
    protected $table         = 'purchase_return_items';
    protected $allowedFields = ['id', 'purchase_return_id', 'purchase_receipt_item_id', 'product_id', 'quantity', 'unit_cost', 'tax_rate', 'tax_amount', 'line_total', 'created_at', 'updated_at'];
}
