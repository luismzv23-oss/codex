<?php

namespace App\Models;

class VatSalesBookModel extends BaseUuidModel
{
    protected $table = 'vat_sales_books';

    protected $allowedFields = [
        'id',
        'company_id',
        'source_type',
        'source_id',
        'sale_id',
        'document_type_id',
        'document_number',
        'issue_date',
        'customer_name',
        'customer_document',
        'customer_tax_profile',
        'net_amount',
        'tax_amount',
        'total_amount',
        'currency_code',
        'status',
        'created_at',
        'updated_at',
    ];
}
