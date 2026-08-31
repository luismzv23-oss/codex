<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ReportsAuthFilter — Role-Based Access Control (RBAC) Guard
 * Restricts Reporting Center access strictly to 'admin' and 'superadmin' roles.
 */
class ReportsAuthFilter implements FilterInterface
{
    /**
     * @param IncomingRequest|RequestInterface $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        /** @var IncomingRequest $request */
        $session = session();
        $user = $session->get('user') ?? auth_user();

        $role = strtolower($user['role'] ?? 'guest');

        if (!in_array($role, ['admin', 'superadmin', 'administrator'], true)) {
            if ($request->isAJAX() || str_contains($request->getPath(), 'api/')) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'status'  => 'error',
                        'message' => 'Acceso Denegado: Requiere permisos de administrador o superadministrador para ver reportes.',
                    ]);
            }

            return redirect()->to(base_url('dashboard'))
                ->with('error', 'Acceso restringido: Se requieren permisos de Admin o Superadmin para el Centro de Reportes.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing required
    }
}
