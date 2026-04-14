<?php

namespace App\Models;

class SalesDocumentTypeModel extends BaseUuidModel
{
    protected $table         = 'sales_document_types';
    protected $allowedFields = [
        'id',
        'company_id',
        'code',
        'name',
        'category',
        'letter',
        'sequence_key',
        'default_prefix',
        'channel',
        'impacts_stock',
        'impacts_receivable',
        'requires_customer',
        'sort_order',
        'active',
        'created_at',
        'updated_at',
    ];
}
