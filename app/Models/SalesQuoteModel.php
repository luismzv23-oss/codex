<?php

namespace App\Models;

class SalesQuoteModel extends BaseUuidModel
{
    protected $table = 'sales_quotes';
    protected $allowedFields = [
        'company_id', 'customer_id', 'quote_number', 'quote_date', 'valid_until',
        'status', 'currency_code', 'exchange_rate', 'subtotal', 'tax_total',
        'discount_total', 'total', 'customer_name_snapshot', 'customer_document_snapshot',
        'customer_tax_profile', 'sales_agent_id', 'sales_zone_id', 'sales_condition_id',
        'price_list_id', 'notes', 'internal_notes', 'converted_to_order_id',
        'created_by', 'approved_by', 'approved_at',
    ];
}
