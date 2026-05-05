<?php

namespace App\Models;

class SalesDeliveryNoteItemModel extends BaseUuidModel
{
    protected $table = 'sales_delivery_note_items';
    protected $allowedFields = [
        'sales_delivery_note_id', 'sales_order_item_id', 'product_id', 'sku',
        'product_name', 'quantity', 'warehouse_id', 'lot_number', 'serial_number',
        'sort_order',
    ];
}
