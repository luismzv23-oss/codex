<?php

namespace App\Controllers\Api\V1;

use App\Libraries\AuditService;
use App\Libraries\JwtService;
use App\Libraries\TwoFactorService;
use App\Models\UserModel;

class AuthController extends BaseApiController
{
    /**
     * POST /api/v1/auth/login
     * Authenticate and return JWT access + refresh tokens.
     */
    public function login()
    {
        $payload  = $this->payload();
        $login    = trim((string) ($payload['login'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($login === '' || $password === '') {
            return $this->fail('Login y password son obligatorios.', 422);
        }

        $userModel = new UserModel();
        $user = $userModel->findForAuth($login);

        if (! $user || (int) $user['active'] !== 1) {
            (new AuditService())->logLogin($user['id'] ?? '', false, 'User not found or inactive');
            return $this->fail('Credenciales invalidas.', 401);
        }

        if (! password_verify($password, $user['password_hash'])) {
            (new AuditService())->logLogin($user['id'], false, 'Invalid password');
            return $this->fail('Credenciales invalidas.', 401);
        }

        // ── 2FA Challenge ──
        if ((int) ($user['two_factor_enabled'] ?? 0) === 1) {
            $totpCode = trim((string) ($payload['totp_code'] ?? ''));

            if ($totpCode === '') {
                return $this->response->setStatusCode(200)->setJSON([
                    'status'           => 'two_factor_required',
                    'message'          => 'Se requiere el codigo de autenticacion de dos factores.',
                    'two_factor_required' => true,
                ]);
            }

            $twoFactor = new TwoFactorService();
            $isValid   = $twoFactor->verify($user['two_factor_secret'], $totpCode);

            // Try backup code if TOTP fails
            if (! $isValid) {
                $backups = json_decode($user['two_factor_backup_codes'] ?? '[]', true);

                if (is_array($backups)) {
                    $remaining = $twoFactor->verifyBackupCode($totpCode, $backups);

                    if ($remaining !== null) {
                        $isValid = true;
                        $userModel->update($user['id'], [
                            'two_factor_backup_codes' => json_encode($remaining),
                        ]);
                    }
                }
            }

            if (! $isValid) {
                (new AuditService())->logLogin($user['id'], false, '2FA code invalid');
                return $this->fail('Codigo de verificacion invalido.', 401);
            }
        }

        // ── Issue tokens ──
        $jwt          = new JwtService();
        $accessToken  = $jwt->generateAccessToken($user);
        $refreshToken = $jwt->generateRefreshToken($user['id']);

        // Update last login
        $userModel->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        (new AuditService())->logLogin($user['id'], true);

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken['token'],
            'token_type'    => 'Bearer',
            'expires_in'    => $jwt->getAccessTtl(),
            'user'          => $jwt->sanitizeUser($user),
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     * Exchange a valid refresh token for a new access + refresh token pair.
     */
    public function refresh()
    {
        $payload      = $this->payload();
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));

        if ($refreshToken === '') {
            return $this->fail('refresh_token es obligatorio.', 422);
        }

        $jwt    = new JwtService();
        $result = $jwt->refreshAccessToken($refreshToken);

        if (! $result) {
            return $this->fail('Refresh token invalido o expirado.', 401);
        }

        return $this->success([
            'access_token'  => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type'    => 'Bearer',
            'expires_in'    => $jwt->getAccessTtl(),
            'user'          => $result['user'],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke the refresh token.
     */
    public function logout()
    {
        $payload      = $this->payload();
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));

        if ($refreshToken !== '') {
            (new JwtService())->revokeRefreshToken($refreshToken);
        }

        // Also close session if present
        if ($this->authService()->check()) {
            $userId = $this->authService()->user()['id'] ?? null;
            $this->authService()->logout();

            if ($userId) {
                (new AuditService())->log('auth', 'logout', 'user', $userId);
            }
        }

        return $this->success(['message' => 'Sesion cerrada correctamente.']);
    }

    /**
     * GET /api/v1/auth/me
     * Get current authenticated user profile.
     */
    public function me()
    {
        $user = $this->resolveApiUser();

        if (! $user) {
            return $this->fail('No autenticado.', 401);
        }

        return $this->success((new JwtService())->sanitizeUser($user));
    }

    /**
     * POST /api/v1/auth/2fa/setup
     * Generate a 2FA secret and QR code URI for the authenticated user.
     */
    public function twoFactorSetup()
    {
        $user = $this->resolveApiUser();

        if (! $user) {
            return $this->fail('No autenticado.', 401);
        }

        $twoFactor = new TwoFactorService();
        $secret    = $twoFactor->generateSecret();
        $qrUri     = $twoFactor->getQrUri($secret, $user['email'] ?? $user['username']);

        // Store secret temporarily (not enabled yet until confirmed)
        (new UserModel())->update($user['id'], ['two_factor_secret' => $secret]);

        return $this->success([
            'secret' => $secret,
            'qr_uri' => $qrUri,
            'message' => 'Escaneá el codigo QR con Google Authenticator o Authy, luego confirmá con POST /api/v1/auth/2fa/confirm',
        ]);
    }

    /**
     * POST /api/v1/auth/2fa/confirm
     * Confirm 2FA setup by verifying a TOTP code. Enables 2FA and generates backup codes.
     */
    public function twoFactorConfirm()
    {
        $user = $this->resolveApiUser();

        if (! $user) {
            return $this->fail('No autenticado.', 401);
        }

        $payload  = $this->payload();
        $totpCode = trim((string) ($payload['totp_code'] ?? ''));

        if ($totpCode === '') {
            return $this->fail('totp_code es obligatorio.', 422);
        }

        $secret = $user['two_factor_secret'] ?? '';

        if ($secret === '') {
            return $this->fail('Primero debes ejecutar POST /api/v1/auth/2fa/setup', 400);
        }

        $twoFactor = new TwoFactorService();

        if (! $twoFactor->verify($secret, $totpCode)) {
            return $this->fail('Codigo de verificacion invalido. Revisá el reloj de tu dispositivo.', 400);
        }

        // Enable 2FA and generate backup codes
        $backupCodes = $twoFactor->generateBackupCodes();

        (new UserModel())->update($user['id'], [
            'two_factor_enabled'      => 1,
            'two_factor_backup_codes' => json_encode($backupCodes),
        ]);

        (new AuditService())->log('auth', 'two_factor_enabled', 'user', $user['id']);

        return $this->success([
            'enabled'      => true,
            'backup_codes' => $backupCodes,
            'message'      => '2FA activado. Guardá estos codigos de respaldo en un lugar seguro.',
        ]);
    }

    /**
     * POST /api/v1/auth/2fa/disable
     * Disable 2FA for the authenticated user (requires current TOTP code).
     */
    public function twoFactorDisable()
    {
        $user = $this->resolveApiUser();

        if (! $user) {
            return $this->fail('No autenticado.', 401);
        }

        $payload  = $this->payload();
        $totpCode = trim((string) ($payload['totp_code'] ?? ''));

        if ($totpCode === '') {
            return $this->fail('totp_code es obligatorio para desactivar 2FA.', 422);
        }

        $twoFactor = new TwoFactorService();

        if (! $twoFactor->verify($user['two_factor_secret'] ?? '', $totpCode)) {
            return $this->fail('Codigo de verificacion invalido.', 400);
        }

        (new UserModel())->update($user['id'], [
            'two_factor_secret'       => null,
            'two_factor_enabled'      => 0,
            'two_factor_backup_codes' => null,
        ]);

        (new AuditService())->log('auth', 'two_factor_disabled', 'user', $user['id']);

        return $this->success(['enabled' => false, 'message' => '2FA desactivado.']);
    }

    // ── Helpers ──────────────────────────────────────────

    /**
     * Resolve the current user from JWT or session.
     */
    private function resolveApiUser(): ?array
    {
        // JWT user (set by ApiAuthFilter)
        $jwtData = $this->request->jwt_user ?? null;

        if ($jwtData && isset($jwtData['user_id'])) {
            return (new UserModel())->findForAuthById($jwtData['user_id']);
        }

        // Session user
        return $this->authService()->user();
    }
}
