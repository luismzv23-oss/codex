<?php

namespace App\Models;

class SalesReceivableModel extends BaseUuidModel
{
    protected $table         = 'sales_receivables';
    protected $allowedFields = [
        'id',
        'company_id',
        'sale_id',
        'customer_id',
        'document_type_id',
        'document_number',
        'issue_date',
        'due_date',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'created_at',
        'updated_at',
    ];
}
