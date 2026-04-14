<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = \Config\Services::auth();

        if (! $auth->check()) {
            return redirect()->to('/login');
        }

        $permission = $arguments[0] ?? null;

        if ($permission === null || ! $auth->can($permission)) {
            return redirect()->to('/dashboard')->with('error', 'No tienes permisos para acceder a este modulo.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
