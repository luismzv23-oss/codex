<?php

namespace App\Models;

class AccountingEntryItemModel extends BaseUuidModel
{
    protected $table = 'accounting_entry_items';

    protected $allowedFields = [
        'id',
        'accounting_entry_id',
        'account_id',
        'line_number',
        'description',
        'debit',
        'credit',
        'reference_type',
        'reference_id',
        'created_at',
        'updated_at',
    ];
}
