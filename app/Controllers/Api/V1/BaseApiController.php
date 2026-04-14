<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use Config\Services;

abstract class BaseApiController extends BaseController
{
    protected function success($data = null, int $status = 200, array $meta = [])
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status' => 'ok',
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    protected function fail(string $message, int $status = 400, array $errors = [])
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ]);
    }

    protected function payload(): array
    {
        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : (array) $this->request->getPost();
    }

    protected function authService()
    {
        return Services::auth();
    }

    protected function apiUser(): ?array
    {
        return $this->authService()->user();
    }

    protected function apiCompanyId(): ?string
    {
        return $this->apiUser()['company_id'] ?? null;
    }

    protected function apiIsSuperadmin(): bool
    {
        return ($this->apiUser()['role_slug'] ?? null) === 'superadmin';
    }
}
