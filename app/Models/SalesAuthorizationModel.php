<?php

namespace App\Models;

class SalesAuthorizationModel extends BaseUuidModel
{
    protected $table = 'sales_authorizations';
    protected $allowedFields = [
        'id', 'company_id', 'sale_id', 'authorization_type', 'reason', 'status', 'requested_by',
        'resolved_by', 'resolved_at', 'resolution_notes', 'created_at', 'updated_at',
    ];
}
