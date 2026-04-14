<?php

namespace App\Models;

class BranchModel extends BaseUuidModel
{
    protected $table         = 'branches';
    protected $allowedFields = [
        'id',
        'company_id',
        'name',
        'code',
        'address',
        'phone',
        'active',
        'created_at',
        'updated_at',
    ];

    public function ensureMainBranch(string $companyId): void
    {
        $existing = $this->where('company_id', $companyId)->countAllResults();

        if ($existing > 0) {
            return;
        }

        $this->insert([
            'company_id' => $companyId,
            'name' => 'Casa Matriz',
            'code' => 'MAIN',
            'address' => null,
            'phone' => null,
            'active' => 1,
        ]);
    }
}
