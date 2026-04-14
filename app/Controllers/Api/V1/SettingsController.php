<?php

namespace App\Controllers\Api\V1;

use App\Models\BranchModel;
use App\Models\CompanyModel;
use App\Models\CurrencyModel;
use App\Models\TaxModel;
use App\Models\VoucherSequenceModel;

class SettingsController extends BaseApiController
{
    public function index()
    {
        $companyId = $this->resolveCompanyId();

        if (! $companyId) {
            return $this->fail('Empresa no disponible.', 404);
        }

        return $this->success([
            'company' => (new CompanyModel())->find($companyId),
            'branches' => (new BranchModel())->where('company_id', $companyId)->findAll(),
            'taxes' => (new TaxModel())->where('company_id', $companyId)->findAll(),
            'currencies' => (new CurrencyModel())->where('company_id', $companyId)->findAll(),
            'voucher_sequences' => (new VoucherSequenceModel())->where('company_id', $companyId)->findAll(),
        ]);
    }

    public function updateCompany()
    {
        $companyId = $this->resolveCompanyId();
        $payload = $this->payload();
        $model = new CompanyModel();
        $row = $model->find($companyId);

        if (! $row) {
            return $this->fail('Empresa no disponible.', 404);
        }

        $model->update($companyId, [
            'name' => trim((string) ($payload['name'] ?? $row['name'])),
            'legal_name' => trim((string) ($payload['legal_name'] ?? $row['legal_name'])),
            'tax_id' => trim((string) ($payload['tax_id'] ?? $row['tax_id'])),
            'email' => trim((string) ($payload['email'] ?? $row['email'])),
            'phone' => trim((string) ($payload['phone'] ?? $row['phone'])),
            'address' => trim((string) ($payload['address'] ?? $row['address'])),
            'currency_code' => trim((string) ($payload['currency_code'] ?? $row['currency_code'])),
        ]);

        return $this->success($model->find($companyId));
    }

    public function storeBranch()
    {
        $payload = $this->payload();
        $model = new BranchModel();
        $id = $model->insert([
            'company_id' => $this->resolveCompanyId(),
            'name' => trim((string) ($payload['name'] ?? '')),
            'code' => trim((string) ($payload['code'] ?? '')),
            'address' => trim((string) ($payload['address'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success($model->find($id), 201);
    }

    public function storeTax()
    {
        $payload = $this->payload();
        $model = new TaxModel();
        $id = $model->insert([
            'company_id' => $this->resolveCompanyId(),
            'name' => trim((string) ($payload['name'] ?? '')),
            'code' => trim((string) ($payload['code'] ?? '')),
            'rate' => (float) ($payload['rate'] ?? 0),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success($model->find($id), 201);
    }

    public function storeCurrency()
    {
        $payload = $this->payload();
        $model = new CurrencyModel();
        $id = $model->insert([
            'company_id' => $this->resolveCompanyId(),
            'code' => strtoupper(trim((string) ($payload['code'] ?? ''))),
            'name' => trim((string) ($payload['name'] ?? '')),
            'symbol' => trim((string) ($payload['symbol'] ?? '')),
            'exchange_rate' => (float) ($payload['exchange_rate'] ?? 1),
            'is_default' => ! empty($payload['is_default']) ? 1 : 0,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success($model->find($id), 201);
    }

    public function storeVoucherSequence()
    {
        $payload = $this->payload();
        $model = new VoucherSequenceModel();
        $id = $model->insert([
            'company_id' => $this->resolveCompanyId(),
            'branch_id' => $payload['branch_id'] ?? null,
            'document_type' => trim((string) ($payload['document_type'] ?? '')),
            'prefix' => trim((string) ($payload['prefix'] ?? '')),
            'current_number' => (int) ($payload['current_number'] ?? 1),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success($model->find($id), 201);
    }

    private function resolveCompanyId(): ?string
    {
        if ($this->apiIsSuperadmin()) {
            $payload = $this->payload();
            $companyId = (string) ($payload['company_id'] ?? $this->request->getGet('company_id') ?? '');

            return $companyId !== '' ? $companyId : null;
        }

        return $this->apiCompanyId();
    }
}
