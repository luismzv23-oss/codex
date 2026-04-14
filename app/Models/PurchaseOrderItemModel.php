<?php

namespace App\Models;

class PurchaseOrderItemModel extends BaseUuidModel
{
    protected $table         = 'purchase_order_items';
    protected $allowedFields = ['id', 'purchase_order_id', 'product_id', 'description', 'quantity', 'received_quantity', 'unit_cost', 'tax_rate', 'tax_amount', 'line_total', 'created_at', 'updated_at'];
}
