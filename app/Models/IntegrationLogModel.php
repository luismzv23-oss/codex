<?php

namespace App\Models;

class IntegrationLogModel extends BaseUuidModel
{
    protected $table = 'integration_logs';

    protected $allowedFields = [
        'id', 'company_id', 'provider', 'service', 'reference_type', 'reference_id', 'status', 'request_payload', 'response_payload', 'message', 'user_id', 'created_at', 'updated_at',
    ];
}
