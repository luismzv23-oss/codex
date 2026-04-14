<?php

namespace App\Models;

class PasswordResetTokenModel extends BaseUuidModel
{
    protected $table         = 'password_reset_tokens';
    protected $allowedFields = [
        'id',
        'user_id',
        'selector',
        'token_hash',
        'expires_at',
        'used_at',
        'created_at',
        'updated_at',
    ];
}
