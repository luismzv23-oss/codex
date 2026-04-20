<?php

namespace App\Filters;

use App\Libraries\JwtService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // ── 1. Try JWT Bearer token first ──
        $authHeader = $request->getHeaderLine('Authorization');

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $jwt   = new JwtService();
            $data  = $jwt->validateToken($token);

            if (! $data) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON([
                        'status'  => 'error',
                        'message' => 'Token invalido o expirado.',
                        'code'    => 'token_expired',
                    ]);
            }

            // Store JWT user data in request for controllers to access
            $request->jwt_user = $data;
            return;
        }

        // ── 2. Fall back to session-based auth (for web AJAX) ──
        if (\Config\Services::auth()->check()) {
            return;
        }

        return service('response')
            ->setStatusCode(401)
            ->setJSON([
                'status'  => 'error',
                'message' => 'No autenticado. Enviar token en header Authorization: Bearer <token>',
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}

