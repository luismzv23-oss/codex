<?php

namespace App\Models;

class QaRunModel extends BaseUuidModel
{
    protected $table = 'qa_runs';
    protected $allowedFields = [
        'id', 'company_id', 'module_name', 'scenario_code', 'status', 'notes', 'executed_by',
        'executed_at', 'created_at', 'updated_at',
    ];
}
