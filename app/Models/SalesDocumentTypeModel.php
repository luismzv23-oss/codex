<?php

namespace App\Models;

class SalesDocumentTypeModel extends BaseUuidModel
{
    protected $table         = 'sales_document_types';
    protected $allowedFields = [
        'id',
        'company_id',
        'code',
        'name',
        'category',
        'letter',
        'sequence_key',
        'default_prefix',
        'channel',
        'impacts_stock',
        'impacts_receivable',
        'requires_customer',
        'sort_order',
        'is_default',
        'active',
        'created_at',
        'updated_at',
    ];

    public function getDefaultDocumentType(string $companyId): ?array
    {
        if (! $this->db->fieldExists('is_default', 'sales_document_types')) {
            return null;
        }

        return $this->where('company_id', $companyId)
            ->where('is_default', 1)
            ->where('active', 1)
            ->first();
    }


    public function setDefault(string $documentTypeId, string $companyId): bool
    {
        $this->where('company_id', $companyId)->set(['is_default' => 0])->update();
        return (bool) $this->update($documentTypeId, ['is_default' => 1]);
    }
}

