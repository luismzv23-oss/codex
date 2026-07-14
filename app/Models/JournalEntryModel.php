<?php

namespace App\Models;

class JournalEntryModel extends BaseUuidModel
{
    protected $table         = 'journal_entries';
    protected $allowedFields = [
        'id',
        'company_id',
        'entry_number',
        'entry_date',
        'description',
        'reference_type',
        'reference_id',
        'status',
        'total_debit',
        'total_credit',
        'user_id',
        'posted_at',
        'created_at',
        'updated_at',
    ];
}
