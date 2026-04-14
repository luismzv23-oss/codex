<?php

namespace App\Models;

class DocumentEventModel extends BaseUuidModel
{
    protected $table = 'document_events';

    protected $allowedFields = [
        'id', 'company_id', 'module', 'document_type', 'document_id', 'event_type', 'payload', 'user_id', 'created_at', 'updated_at',
    ];
}
