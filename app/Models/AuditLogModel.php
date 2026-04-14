<?php

namespace App\Models;

class AuditLogModel extends BaseUuidModel
{
    protected $table = 'audit_logs';

    protected $allowedFields = [
        'id', 'company_id', 'module', 'entity_type', 'entity_id', 'action', 'before_payload', 'after_payload', 'user_id', 'created_at', 'updated_at',
    ];
}
