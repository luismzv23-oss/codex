<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class HealthController extends BaseController
{
    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'status' => 'ok',
            'app' => 'codex',
            'environment' => ENVIRONMENT,
            'timestamp' => date(DATE_ATOM),
        ]);
    }
}
