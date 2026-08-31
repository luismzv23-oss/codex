<?php

if (! function_exists('auth')) {
    function auth()
    {
        return service('auth');
    }
}

if (! function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return auth()->user();
    }
}

if (! function_exists('auth_check')) {
    function auth_check(): bool
    {
        return auth()->check();
    }
}

if (! function_exists('auth_can')) {
    function auth_can(string $permission): bool
    {
        return auth()->can($permission);
    }
}

if (! function_exists('auth_company_id')) {
    function auth_company_id(): ?string
    {
        $user = auth_user();
        return $user['company_id'] ?? session('active_company_id') ?? null;
    }
}

