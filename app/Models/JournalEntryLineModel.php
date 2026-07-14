<?php

namespace App\Models;

class JournalEntryLineModel extends BaseUuidModel
{
    protected $table         = 'journal_entry_lines';
    protected $allowedFields = [
        'id',
        'journal_entry_id',
        'account_id',
        'description',
        'debit',
        'credit',
        'created_at',
    ];
}
