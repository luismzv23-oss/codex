<?php

namespace App\Libraries;

use App\Models\PasswordResetTokenModel;
use App\Models\RolePermissionModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class AuthService
{
    private UserModel $users;
    private PasswordResetTokenModel $passwordResets;
    private RolePermissionModel $rolePermissions;

    public function __construct()
    {
        $this->users = new UserModel();
        $this->passwordResets = new PasswordResetTokenModel();
        $this->rolePermissions = new RolePermissionModel();
    }

    public function attempt(string $login, string $password): bool
    {
        $user = $this->users->findForAuth(trim($login));

        if (! $user || (int) $user['active'] !== 1) {
            return false;
        }

        if (! password_verify($password, $user['password_hash'])) {
            return false;
        }

        session()->regenerate(true);
        $this->storeUser($user['id']);
        $this->users->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    public function storeUser(string $userId): void
    {
        $user = $this->users->findForAuthById($userId);

        if (! $user) {
            return;
        }

        session()->set('auth_user', [
            'id' => $user['id'],
            'company_id' => $user['company_id'],
            'branch_id' => $user['branch_id'],
            'branch_name' => $user['branch_name'] ?? null,
            'branch_code' => $user['branch_code'] ?? null,
            'role_id' => $user['role_id'],
            'role_slug' => $user['role_slug'],
            'role_name' => $user['role_name'],
            'name' => $user['name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'company_name' => $user['company_name'],
            'must_change_password' => (int) $user['must_change_password'],
            'permissions' => $this->permissionsForRole($user['role_id']),
        ]);
    }

    public function user(): ?array
    {
        $user = session()->get('auth_user');

        return is_array($user) ? $user : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if (($user['role_slug'] ?? null) === 'superadmin') {
            return true;
        }

        $restrictedForNonMainAdmin = [
            'users.manage',
            'settings.manage',
            'branches.manage',
            'taxes.manage',
            'currencies.manage',
            'voucher_sequences.manage',
            'systems.manage',
        ];

        if (
            ($user['role_slug'] ?? null) === 'admin'
            && ($user['branch_code'] ?? null) !== 'MAIN'
            && in_array($permission, $restrictedForNonMainAdmin, true)
        ) {
            return false;
        }

        return in_array($permission, $user['permissions'] ?? [], true);
    }

    public function logout(): void
    {
        session()->remove('auth_user');
        session()->regenerate(true);
    }

    public function createPasswordReset(string $email): ?array
    {
        $user = $this->users->where('email', trim($email))->where('active', 1)->first();

        if (! $user) {
            return null;
        }

        $selector = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(16));

        $this->passwordResets
            ->where('user_id', $user['id'])
            ->delete();

        $this->passwordResets->insert([
            'user_id' => $user['id'],
            'selector' => $selector,
            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
            'expires_at' => Time::now()->addHours(1)->toDateTimeString(),
            'used_at' => null,
        ]);

        return [
            'selector' => $selector,
            'token' => $token,
            'user' => $user,
        ];
    }

    public function validatePasswordReset(string $selector, string $token): ?array
    {
        $reset = $this->passwordResets->where('selector', $selector)->first();

        if (! $reset || $reset['used_at'] !== null || strtotime((string) $reset['expires_at']) < time()) {
            return null;
        }

        if (! password_verify($token, $reset['token_hash'])) {
            return null;
        }

        return $reset;
    }

    public function resetPassword(string $selector, string $token, string $newPassword): bool
    {
        $reset = $this->validatePasswordReset($selector, $token);

        if (! $reset) {
            return false;
        }

        $this->users->update($reset['user_id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);

        $this->passwordResets->update($reset['id'], [
            'used_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function permissionsForRole(string $roleId): array
    {
        $rows = $this->rolePermissions
            ->select('permissions.slug')
            ->join('permissions', 'permissions.id = role_permission.permission_id')
            ->where('role_permission.role_id', $roleId)
            ->findAll();

        return array_values(array_map(static fn(array $row): string => $row['slug'], $rows));
    }
}
