<?php

namespace App\Models;

class SalesDeliveryNoteModel extends BaseUuidModel
{
    protected $table = 'sales_delivery_notes';
    protected $allowedFields = [
        'company_id', 'customer_id', 'sales_order_id', 'sale_id', 'delivery_number',
        'delivery_date', 'status', 'warehouse_id', 'shipping_address', 'carrier',
        'tracking_number', 'customer_name_snapshot', 'customer_document_snapshot',
        'notes', 'dispatched_at', 'delivered_at', 'created_by',
    ];
}
