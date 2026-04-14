<?php

namespace App\Models;

class PurchaseReceiptItemModel extends BaseUuidModel
{
    protected $table         = 'purchase_receipt_items';
    protected $allowedFields = ['id', 'purchase_receipt_id', 'purchase_order_item_id', 'product_id', 'quantity', 'unit_cost', 'tax_rate', 'tax_amount', 'line_total', 'lot_number', 'serial_number', 'expiration_date', 'created_at', 'updated_at'];
}
