<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ThrottleFilter implements FilterInterface
{
    /**
     * Maximum requests allowed per window, by route prefix.
     */
    private array $limits = [
        'login'   => ['max' => 5,  'window' => 60],   // 5 attempts per minute
        'api'     => ['max' => 60, 'window' => 60],   // 60 requests per minute
        'default' => ['max' => 120, 'window' => 60],  // 120 requests per minute
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $cache = service('cache');

        $type = $this->resolveType($request, $arguments);
        $limit = $this->limits[$type] ?? $this->limits['default'];

        $ip = $request->getIPAddress();
        $key = "throttle_{$type}_" . str_replace([':', '.'], '_', $ip);

        $current = (int) $cache->get($key);

        if ($current >= $limit['max']) {
            $retryAfter = $limit['window'];

            // API requests get a JSON response
            if ($type === 'api') {
                return service('response')
                    ->setStatusCode(429)
                    ->setHeader('Retry-After', (string) $retryAfter)
                    ->setJSON([
                        'status'  => 'error',
                        'message' => 'Demasiadas solicitudes. Intentá de nuevo en ' . $retryAfter . ' segundos.',
                    ]);
            }

            // Web requests get redirected back with error
            return redirect()
                ->back()
                ->with('error', 'Demasiados intentos. Por favor esperá ' . $retryAfter . ' segundos antes de intentar nuevamente.');
        }

        // Increment the counter
        if ($current === 0) {
            $cache->save($key, 1, $limit['window']);
        } else {
            $cache->save($key, $current + 1, $limit['window']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after
    }

    /**
     * Determine the throttle type from the filter arguments or URI.
     */
    private function resolveType(RequestInterface $request, ?array $arguments): string
    {
        // If a type was passed as a filter argument, use it
        if (! empty($arguments[0])) {
            return $arguments[0];
        }

        $path = $request->getUri()->getPath();

        if (str_contains($path, 'login') || str_contains($path, 'auth')) {
            return 'login';
        }

        if (str_starts_with($path, 'api/') || str_starts_with($path, '/api/')) {
            return 'api';
        }

        return 'default';
    }
}
