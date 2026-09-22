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
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->company($companyId, $this->payload());
            return $this->success($result, 200);
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function storeBranch()
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->branch($companyId, $this->payload());
            return $this->success($result, 201);
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function storeTax()
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->tax($companyId, $this->payload());
            return $this->success($result, 201);
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function storeCurrency()
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->currency($companyId, $this->payload());
            return $this->success($result, 201);
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function storeVoucherSequence()
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->sequence($companyId, $this->payload());
            return $this->success($result, 201);
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
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
