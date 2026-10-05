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

        $user = $auth->user();
        $isVendedor = ($user['role_slug'] ?? '') === 'vendedor';
        $uri = trim((string)$request->getUri()->getPath(), '/');

        $permission = $arguments[0] ?? null;

        if ($permission === null || ! $auth->can($permission)) {
            if ($isVendedor) {
                if ($uri === 'ventas' || str_starts_with($uri, 'ventas/')) {
                    return redirect()->to('/login')->with('error', 'No tienes permisos asignados para acceder a Ventas.');
                }
                return redirect()->to('/ventas')->with('error', 'No tienes permisos para acceder a este modulo.');
            }

            if ($uri === 'dashboard') {
                return redirect()->to('/login')->with('error', 'No tienes permisos para acceder al panel principal.');
            }

            return redirect()->to('/dashboard')->with('error', 'No tienes permisos para acceder a este modulo.');
        }

        if ($isVendedor) {
            if (str_starts_with($uri, 'caja')) {
                $db = \Config\Database::connect();
                $hasCaja = $db->table('user_systems')
                    ->join('systems', 'systems.id = user_systems.system_id')
                    ->where('user_systems.user_id', $user['id'] ?? '')
                    ->where('systems.slug', 'caja')
                    ->where('user_systems.active', 1)
                    ->where('systems.active', 1)
                    ->countAllResults() > 0;

                if (!$hasCaja) {
                    return redirect()->to('/ventas')->with('error', 'No tienes acceso al sistema de Caja.');
                }
            } else if (!str_starts_with($uri, 'ventas') && !str_starts_with($uri, 'logout') && !str_starts_with($uri, 'login')) {
                if ($uri !== 'ventas') {
                    return redirect()->to('/ventas');
                }
            }
        }

    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
