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
