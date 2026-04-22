<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! \Config\Services::auth()->check()) {
            // AJAX / fetch requests get a 401 JSON so the login page
            // is never injected inside modals or partial views.
            if ($request->isAJAX()
                || $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest'
                || str_contains((string) $request->getHeaderLine('Accept'), 'application/json')
            ) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON([
                        'status'  => 'error',
                        'message' => 'Sesion expirada. Por favor inicia sesion nuevamente.',
                        'code'    => 'session_expired',
                    ]);
            }

            return redirect()->to('/login')->with('error', 'Debes iniciar sesion para continuar.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
