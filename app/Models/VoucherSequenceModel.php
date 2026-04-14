<?php

namespace App\Models;

class VoucherSequenceModel extends BaseUuidModel
{
    protected $table         = 'voucher_sequences';
    protected $allowedFields = [
        'id',
        'company_id',
        'branch_id',
        'document_type',
        'prefix',
        'current_number',
        'active',
        'created_at',
        'updated_at',
    ];
}
