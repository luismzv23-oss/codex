<?php

namespace App\Models;

class AccountingEntryModel extends BaseUuidModel
{
    protected $table = 'accounting_entries';

    protected $allowedFields = [
        'id',
        'company_id',
        'entry_number',
        'entry_date',
        'source_type',
        'source_id',
        'source_number',
        'description',
        'status',
        'total_debit',
        'total_credit',
        'posted_by',
        'posted_at',
        'created_at',
        'updated_at',
    ];
}
