<?php
namespace App\Libraries\Core;

/** Immutable view of the authenticated, refreshed identity for this request. */
final class ActorContext
{
    private array $user;

    private function __construct(array $user) { $this->user = $user; }

    public static function current(): self
    {
        $user = service('auth')->user();
        if (!$user || empty($user['id'])) throw new AccessException('Debes iniciar sesión para continuar.', 401);
        return new self($user);
    }

    public function id(): string { return $this->user['id']; }
    public function companyId(): ?string { return $this->user['company_id'] ?? null; }
    public function role(): string { return $this->user['role_slug'] ?? ''; }
    public function isSuperadmin(): bool { return $this->role() === 'superadmin'; }
    public function hasPermission(string $permission): bool
    {
        return \App\Libraries\AuthService::allows($this->user, $permission);
    }
}
