<?php

namespace App\Controllers;

use App\Libraries\Core\ModuleInstallationService;

final class ModuleIntegrationController extends BaseController
{
    public function index()
    {
        if (!$this->isSuperadmin()) return $this->response->setStatusCode(403)->setBody('Acceso restringido.');
        $companyId=$this->resolveActiveCompanyId();
        return view('systems/integration',[
            'pageTitle'=>'Integración de sistemas',
            'report'=>(new ModuleInstallationService())->report(),
            'integrationStatus'=>$companyId ? \App\Libraries\Core\Events\DeliveryStatus::summary($companyId) : null,
        ]);
    }
}
