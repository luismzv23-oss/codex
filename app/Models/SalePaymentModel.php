<?php

namespace App\Models;

class SalePaymentModel extends BaseUuidModel
{
    protected $table         = 'sale_payments';
    protected $allowedFields = [
        'id',
        'sale_id',
        'payment_method',
        'amount',
        'reference',
        'status',
        'paid_at',
        'notes',
        'created_at',
        'updated_at',
    ];
}
