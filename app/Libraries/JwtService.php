<?php

namespace App\Libraries;

use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class JwtService
{
    private string $secret;
    private string $algorithm = 'HS256';
    private int $accessTtl  = 900;     // 15 minutes
    private int $refreshTtl = 604800;  // 7 days

    public function __construct()
    {
        $this->secret = env('jwt.secret', 'codex-dev-secret-change-in-production-min-32-chars!');
    }

    // ── Access Token ───────────────────────────────────────

    public function generateAccessToken(array $user): string
    {
        $now = time();

        $payload = [
            'iss' => 'codex-erp',
            'sub' => $user['id'],
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'data' => [
                'user_id'    => $user['id'],
                'company_id' => $user['company_id'] ?? null,
                'branch_id'  => $user['branch_id'] ?? null,
                'role_slug'  => $user['role_slug'] ?? null,
            ],
        ];

        return $this->encode($payload);
    }

    // ── Refresh Token ──────────────────────────────────────

    public function generateRefreshToken(string $userId): array
    {
        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->refreshTtl);

        $db = db_connect();
        $db->table('refresh_tokens')->insert([
            'id'          => app_uuid(),
            'user_id'     => $userId,
            'token_hash'  => $tokenHash,
            'expires_at'  => $expiresAt,
            'ip_address'  => service('request')->getIPAddress(),
            'user_agent'  => substr((string) service('request')->getUserAgent(), 0, 255),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function refreshAccessToken(string $refreshToken): ?array
    {
        $tokenHash = hash('sha256', $refreshToken);
        $db = db_connect();

        $row = $db->table('refresh_tokens')
            ->where('token_hash', $tokenHash)
            ->where('revoked_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        if (! $row) {
            return null;
        }

        $userModel = new UserModel();
        $user = $userModel->findForAuthById($row['user_id']);

        if (! $user || (int) ($user['active'] ?? 0) !== 1) {
            return null;
        }

        // Rotate: revoke old refresh token and issue new one
        $db->table('refresh_tokens')
            ->where('id', $row['id'])
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);

        $newRefresh = $this->generateRefreshToken($user['id']);

        return [
            'access_token'  => $this->generateAccessToken($user),
            'refresh_token' => $newRefresh['token'],
            'expires_in'    => $this->accessTtl,
            'user'          => $this->sanitizeUser($user),
        ];
    }

    public function revokeRefreshToken(string $refreshToken): void
    {
        $tokenHash = hash('sha256', $refreshToken);

        db_connect()->table('refresh_tokens')
            ->where('token_hash', $tokenHash)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeAllUserTokens(string $userId): void
    {
        db_connect()->table('refresh_tokens')
            ->where('user_id', $userId)
            ->where('revoked_at IS NULL')
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    // ── Validate ───────────────────────────────────────────

    public function validateToken(string $token): ?array
    {
        $payload = $this->decode($token);

        if (! $payload) {
            return null;
        }

        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload['data'] ?? null;
    }

    // ── JWT Encoding / Decoding (native PHP, no library) ──

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]));
        $body   = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", $this->secret, true)
        );

        return "{$header}.{$body}.{$signature}";
    }

    private function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", $this->secret, true)
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($body), true);

        return is_array($payload) ? $payload : null;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // ── Helpers ────────────────────────────────────────────

    public function sanitizeUser(array $user): array
    {
        unset($user['password_hash'], $user['two_factor_secret'], $user['two_factor_backup_codes']);
        return $user;
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }
}
