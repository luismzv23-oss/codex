<?php

namespace App\Validation;

class PasswordRules
{
    /**
     * Validate that a password meets strong complexity requirements:
     *  - At least 8 characters
     *  - At least one uppercase letter
     *  - At least one lowercase letter
     *  - At least one digit
     *  - At least one special character (@$!%*?&#+\-_.)
     */
    public function strong_password(string $str, ?string &$error = null): bool
    {
        if (mb_strlen($str) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
            return false;
        }

        if (! preg_match('/[a-z]/', $str)) {
            $error = 'La contraseña debe contener al menos una letra minúscula.';
            return false;
        }

        if (! preg_match('/[A-Z]/', $str)) {
            $error = 'La contraseña debe contener al menos una letra mayúscula.';
            return false;
        }

        if (! preg_match('/\d/', $str)) {
            $error = 'La contraseña debe contener al menos un número.';
            return false;
        }

        if (! preg_match('/[@$!%*?&#+\-_.]/', $str)) {
            $error = 'La contraseña debe contener al menos un carácter especial (@$!%*?&#+-_.)';
            return false;
        }

        return true;
    }

    /**
     * Validate that a password is not in a list of commonly breached passwords.
     */
    public function not_common_password(string $str, ?string &$error = null): bool
    {
        $common = [
            'password', '12345678', '123456789', '1234567890', 'qwerty123',
            'admin123', 'letmein12', 'welcome1', 'monkey123', 'dragon12',
            'master12', 'password1', 'Password1', 'abc12345', 'trustno1',
        ];

        if (in_array(strtolower($str), array_map('strtolower', $common), true)) {
            $error = 'La contraseña elegida es demasiado común. Elegí una más segura.';
            return false;
        }

        return true;
    }
}
