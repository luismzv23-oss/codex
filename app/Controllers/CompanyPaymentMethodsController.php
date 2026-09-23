<?php
namespace App\Controllers;

use App\Libraries\CompanyPaymentMethodService;
use App\Models\CompanyPaymentMethodModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class CompanyPaymentMethodsController extends BaseController
{
    private function resolveCompanyId(): string
    {
        $id = $this->companyId();
        if ($this->isSuperadmin()) {
            $id = $this->request->getPost('company_id') ?: $this->request->getGet('company_id') ?: $id;
        }
        if (! is_string($id) || ! (new \App\Models\CompanyModel())->find($id)) {
            throw PageNotFoundException::forPageNotFound('Empresa no disponible.');
        }
        return $id;
    }

    public function form(?string $id = null)
    {
        $companyId = (string) $this->resolveCompanyId();
        $method = $id ? (new CompanyPaymentMethodModel())->where('company_id', $companyId)->find($id) : [];
        if ($id && ! $method) { throw PageNotFoundException::forPageNotFound(); }
        $options = [];
        foreach (['currencies' => 'currencies', 'branches' => 'branches', 'points' => 'sales_points_of_sale'] as $key => $table) {
            $options[$key] = db_connect()->table($table)->where('company_id', $companyId)->orderBy('name', 'ASC')->get()->getResultArray();
        }
        return view('settings/forms/payment_method', $options + [
            'method' => $method, 'companyId' => $companyId,
            'types' => CompanyPaymentMethodService::TYPES, 'destinations' => CompanyPaymentMethodService::DESTINATIONS,
            'pageTitle' => $id ? 'Editar medio de pago' : 'Nuevo medio de pago',
            'formAction' => site_url('configuracion/medios-pago' . ($id ? '/' . $id . '/actualizar' : '')),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function listing()
    {
        $companyId = $this->resolveCompanyId();
        return $this->response->setHeader('Cache-Control', 'no-store')->setBody(view('settings/payment_methods_card', [
            'company' => ['id' => $companyId],
            'paymentMethods' => (new CompanyPaymentMethodModel())->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll(),
        ]));
    }

    public function save(?string $id = null)
    {
        $companyId = (string) $this->resolveCompanyId();
        try {
            $input = (array) $this->request->getPost();
            $extra = $input['extra_required_fields'] ?? '';
            if (! is_string($extra)) { throw new \RuntimeException('Datos obligatorios inválidos.'); }
            if (! is_array($input['required_fields'] ?? [])) { throw new \RuntimeException('Datos obligatorios inválidos.'); }
            $input['required_fields'] = array_merge($input['required_fields'] ?? [], array_values(array_filter(array_map('trim', explode(',', $extra)), static fn($v) => $v !== '')));
            $method = (new CompanyPaymentMethodService())->save($companyId, $input, $id);
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Medio de pago guardado correctamente.', [
                'entity' => 'company-payment-method', 'action' => $id ? 'updated' : 'created',
                'item' => ['id' => $method['id'], 'company_id' => $companyId],
            ]);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function delete(string $id)
    {
        $companyId = (string) $this->resolveCompanyId();
        try {
            (new CompanyPaymentMethodService())->remove($companyId, $id);
            return redirect()->to('/configuracion?company_id=' . $companyId)->with('message', 'Medio de pago eliminado del catálogo.');
        } catch (\RuntimeException $e) {
            return redirect()->to('/configuracion?company_id=' . $companyId)->with('error', $e->getMessage());
        }
    }
}
