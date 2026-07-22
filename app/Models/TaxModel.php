<?php

namespace App\Models;

class TaxModel extends BaseUuidModel
{
    protected $table         = 'taxes';
    protected $allowedFields = [
        'id',
        'company_id',
        'name',
        'code',
        'rate',
        'afip_code',
        'is_default',
        'active',
        'created_at',
        'updated_at',
    ];

    public function getDefaultTax(string $companyId): ?array
    {
        return $this->where('company_id', $companyId)
            ->where('is_default', 1)
            ->where('active', 1)
            ->first();
    }

    public function setDefault(string $taxId, string $companyId): bool
    {
        $this->where('company_id', $companyId)->set(['is_default' => 0])->update();
        return (bool) $this->update($taxId, ['is_default' => 1]);
    }
}

