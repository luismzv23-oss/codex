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
    private ?array $requestUser = null;
    private bool $requestResolved = false;

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
        if ($this->requestUser === null) return false;
        $this->users->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    public function storeUser(string $userId): void
    {
        $this->requestUser = $this->loadCurrentUser($userId);
        $this->requestResolved = true;
        if (!$this->requestUser) {
            session()->remove(['auth_user', 'active_company_id']);
            return;
        }
        session()->set('auth_user', $this->requestUser);
        if (!empty($this->requestUser['company_id'])) session()->set('active_company_id', $this->requestUser['company_id']);
        else session()->remove('active_company_id');
    }

    public function user(): ?array
    {
        if ($this->requestResolved) return $this->requestUser;
        $stored = session()->get('auth_user');
        $this->requestResolved = true;
        $this->requestUser = is_array($stored) && !empty($stored['id']) ? $this->loadCurrentUser((string) $stored['id']) : null;
        if ($this->requestUser) session()->set('auth_user', $this->requestUser);
        elseif (is_array($stored)) session()->remove(['auth_user', 'active_company_id']);
        return $this->requestUser;
    }

    /** Bearer authentication refreshes identity without creating or altering a web login. */
    public function authenticateRequest(string $userId): bool
    {
        $this->requestResolved = true;
        $this->requestUser = $this->loadCurrentUser($userId);
        return $this->requestUser !== null;
    }

    private function loadCurrentUser(string $userId): ?array
    {
        $user = $this->users->findForAuthById($userId);
        if (!$user || (int) $user['active'] !== 1 || empty($user['role_id']) || empty($user['role_slug'])) return null;
        $current = array_intersect_key($user, array_flip([
            'id', 'company_id', 'branch_id', 'branch_name', 'branch_code',
            'role_id', 'role_slug', 'role_name', 'name', 'username', 'email',
            'company_name', 'must_change_password',
        ]));
        $current['must_change_password'] = (int) ($user['must_change_password'] ?? 0);
        $current['permissions'] = $this->permissionsForRole($user['role_id']);
        return $current;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function can(string $permission): bool
    {
        return self::allows($this->user(), $permission);
    }

    public static function allows(?array $user, string $permission): bool
    {

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
        $this->requestUser = null;
        $this->requestResolved = true;
        session()->remove(['auth_user', 'active_company_id']);
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

        $db = db_connect();
        if (!$db->transBegin()) return false;
        try {
            // Claim once inside the same transaction as the password update.
            $ok = $db->table('password_reset_tokens')->where('id', $reset['id'])->where('used_at', null)
                ->where('expires_at >', date('Y-m-d H:i:s'))->update(['used_at'=>date('Y-m-d H:i:s')]);
            if (!$ok || $db->affectedRows() !== 1) {
                $db->transRollback();
                return false;
            }
            $user = $this->users->where('id', $reset['user_id'])->where('active', 1)->first();
            if (!$user || !$this->users->update($reset['user_id'], [
                'password_hash'=>password_hash($newPassword, PASSWORD_DEFAULT), 'must_change_password'=>0,
            ])) {
                $db->transRollback();
                return false;
            }
            if (!$db->transStatus()) throw new \RuntimeException('Password reset failed.');
            if (!$db->transCommit()) throw new \RuntimeException('Password reset failed.');
            return true;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Password reset failed.');
            return false;
        }
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
