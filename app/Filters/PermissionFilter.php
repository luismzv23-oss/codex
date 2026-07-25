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

        $user = $auth->user();
        if (($user['role_slug'] ?? '') === 'vendedor') {
            $uri = trim((string)$request->getUri()->getPath(), '/');
            if (strpos($uri, 'caja') === 0) {
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
            } else if (strpos($uri, 'ventas') !== 0 && strpos($uri, 'logout') !== 0) {
                 return redirect()->to('/ventas');
            }
        }

    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
