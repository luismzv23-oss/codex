<?php

namespace App\Libraries;

class TwoFactorService
{
    private int $codeLength = 6;
    private int $period     = 30;   // seconds
    private int $window     = 1;    // accept 1 period before/after
    private int $backupCount = 10;

    // ── Secret Generation ────────────────────────────────

    public function generateSecret(int $length = 20): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }

        return $secret;
    }

    // ── TOTP Code Generation (RFC 6238) ──────────────────

    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $counter   = intdiv($timestamp, $this->period);

        return $this->hotp($secret, $counter);
    }

    // ── Verification ─────────────────────────────────────

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $timestamp = $timestamp ?? time();
        $counter   = intdiv($timestamp, $this->period);

        // Check current period and ±window to account for clock drift
        for ($i = -$this->window; $i <= $this->window; $i++) {
            $expected = $this->hotp($secret, $counter + $i);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    // ── Backup Codes ─────────────────────────────────────

    public function generateBackupCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < $this->backupCount; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));  // 8-char hex codes
        }

        return $codes;
    }

    public function verifyBackupCode(string $code, array $storedCodes): ?array
    {
        foreach ($storedCodes as $index => $stored) {
            if (hash_equals(strtoupper($stored), strtoupper($code))) {
                // Remove used code
                $remaining = $storedCodes;
                unset($remaining[$index]);

                return array_values($remaining);
            }
        }

        return null;
    }

    // ── QR Code URI (for Google Authenticator / Authy) ───

    public function getQrUri(string $secret, string $label, string $issuer = 'Codex ERP'): string
    {
        $label  = rawurlencode($label);
        $issuer = rawurlencode($issuer);
        $secret = rawurlencode($secret);

        return "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits={$this->codeLength}&period={$this->period}";
    }

    /**
     * Generate a simple SVG QR code. Uses a minimal QR implementation
     * so no external library is required.
     */
    public function getQrSvgDataUri(string $data): string
    {
        // For production, integrate a QR library. This returns the URI for client-side rendering.
        // The frontend can use a JS QR library (e.g., qrcode.js) with the otpauth:// URI.
        return $data;
    }

    // ── HOTP (RFC 4226) ──────────────────────────────────

    private function hotp(string $secret, int $counter): string
    {
        $key  = $this->base32Decode($secret);
        $data = pack('N*', 0, $counter);

        $hash   = hash_hmac('sha1', $data, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** $this->codeLength);

        return str_pad((string) $code, $this->codeLength, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos($map, $input[$i]);

            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
