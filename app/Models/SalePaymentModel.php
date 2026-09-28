<?php

namespace App\Models;

class SalePaymentModel extends BaseUuidModel
{
    protected $table         = 'sale_payments';
    protected $allowedFields = [
        'id',
        'sale_id',
        'payment_method',
        'payment_method_id', 'payment_method_code', 'base_amount', 'surcharge_rate',
        'surcharge_amount', 'received_amount', 'change_amount',
        'gateway_id',
        'cash_check_id',
        'external_reference',
        'sales_receipt_id',
        'amount',
        'reference',
        'status',
        'paid_at',
        'notes',
        'created_at',
        'updated_at',
    ];
}
