<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Nothing to do before
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Prevent MIME-type sniffing
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        // Clickjacking protection — allow same origin for popup iframes
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');

        // XSS protection (legacy browsers)
        $response->setHeader('X-XSS-Protection', '1; mode=block');

        // Referrer policy — only send origin on cross-origin requests
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy — disable dangerous browser features
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS — only in production with HTTPS
        if (ENVIRONMENT === 'production') {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com",
            "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->setHeader('Content-Security-Policy', $csp);
    }
}
