<?php

namespace App\Controllers\Api\V1;

use App\Models\BranchModel;
use App\Models\CompanyModel;
use App\Models\CurrencyModel;
use App\Models\UserModel;

class CompaniesController extends BaseApiController
{
    public function index()
    {
        $builder = (new CompanyModel())->orderBy('name', 'ASC');

        if (! $this->apiIsSuperadmin()) {
            $builder->where('id', $this->apiCompanyId());
        }

        return $this->success($builder->findAll());
    }

    public function store()
    {
        if (! $this->apiIsSuperadmin()) {
            return $this->fail('Solo superadmin puede crear empresas.', 403);
        }

        $payload = $this->payload();
        $model = new CompanyModel();
        $currencyCode = trim((string) ($payload['currency_code'] ?? 'ARS'));
        $id = $model->insert([
            'name' => trim((string) ($payload['name'] ?? '')),
            'legal_name' => trim((string) ($payload['legal_name'] ?? '')),
            'tax_id' => trim((string) ($payload['tax_id'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'address' => trim((string) ($payload['address'] ?? '')),
            'currency_code' => $currencyCode,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);
        $this->ensureBaseCurrency((string) $id, $currencyCode);
        (new BranchModel())->ensureMainBranch((string) $id);

        return $this->success($model->find($id), 201);
    }

    public function update(string $id)
    {
        $model = new CompanyModel();
        $row = $model->find($id);

        if (! $row || (! $this->apiIsSuperadmin() && $row['id'] !== $this->apiCompanyId())) {
            return $this->fail('Empresa no disponible.', 404);
        }

        $payload = $this->payload();
        $currencyCode = trim((string) ($payload['currency_code'] ?? $row['currency_code']));
        $model->update($id, [
            'name' => trim((string) ($payload['name'] ?? $row['name'])),
            'legal_name' => trim((string) ($payload['legal_name'] ?? $row['legal_name'])),
            'tax_id' => trim((string) ($payload['tax_id'] ?? $row['tax_id'])),
            'email' => trim((string) ($payload['email'] ?? $row['email'])),
            'phone' => trim((string) ($payload['phone'] ?? $row['phone'])),
            'address' => trim((string) ($payload['address'] ?? $row['address'])),
            'currency_code' => $currencyCode,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : $row['active'],
        ]);
        $this->ensureBaseCurrency($id, $currencyCode);
        (new BranchModel())->ensureMainBranch($id);

        return $this->success($model->find($id));
    }

    public function delete(string $id)
    {
        if (! $this->apiIsSuperadmin()) {
            return $this->fail('Solo superadmin puede eliminar empresas.', 403);
        }

        $model = new CompanyModel();
        $row = $model->find($id);

        if (! $row) {
            return $this->fail('Empresa no disponible.', 404);
        }

        $db = db_connect();
        $db->transStart();

        (new UserModel())->where('company_id', $id)->delete();
        $model->delete($id);

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->fail('No se pudo eliminar la empresa seleccionada.', 500);
        }

        return $this->success([
            'id' => $id,
            'deleted' => true,
        ]);
    }

    private function ensureBaseCurrency(string $companyId, string $currencyCode): void
    {
        $currencyModel = new CurrencyModel();
        $existing = $currencyModel
            ->where('company_id', $companyId)
            ->where('code', $currencyCode)
            ->first();

        $currencyModel
            ->where('company_id', $companyId)
            ->set(['is_default' => 0])
            ->update();

        if ($existing) {
            $currencyModel->update($existing['id'], [
                'is_default' => 1,
                'active' => 1,
            ]);

            return;
        }

        $template = $currencyModel
            ->select('code, name, symbol, exchange_rate')
            ->where('code', $currencyCode)
            ->orderBy('active', 'DESC')
            ->first();

        $currencyModel->insert([
            'company_id' => $companyId,
            'code' => $currencyCode,
            'name' => $template['name'] ?? $currencyCode,
            'symbol' => $template['symbol'] ?? '',
            'exchange_rate' => $template['exchange_rate'] ?? '1.0000',
            'is_default' => 1,
            'active' => 1,
        ]);
    }
}
