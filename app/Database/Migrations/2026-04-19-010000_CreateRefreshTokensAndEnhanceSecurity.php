<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRefreshTokensAndEnhanceSecurity extends Migration
{
    public function up()
    {
        // ── 1. Refresh tokens for JWT ─────────────────────
        $this->forge->addField([
            'id'         => ['type' => 'CHAR', 'constraint' => 36],
            'user_id'    => ['type' => 'CHAR', 'constraint' => 36],
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 128],
            'expires_at' => ['type' => 'DATETIME'],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('token_hash');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('refresh_tokens', true);

        // ── 2. Add 2FA fields to users ────────────────────
        if (! $this->db->fieldExists('two_factor_secret', 'users')) {
            $this->forge->addColumn('users', [
                'two_factor_secret' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'after'      => 'password_hash',
                ],
                'two_factor_enabled' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'two_factor_secret',
                ],
                'two_factor_backup_codes' => [
                    'type'  => 'TEXT',
                    'null'  => true,
                    'after' => 'two_factor_enabled',
                ],
            ]);
        }

        // ── 3. Enhance audit_logs with IP and user agent ──
        if (! $this->db->fieldExists('ip_address', 'audit_logs')) {
            $this->forge->addColumn('audit_logs', [
                'ip_address' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 45,
                    'null'       => true,
                    'after'      => 'user_id',
                ],
                'user_agent' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'ip_address',
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'user_agent',
                ],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('refresh_tokens', true);

        if ($this->db->fieldExists('two_factor_secret', 'users')) {
            $this->forge->dropColumn('users', ['two_factor_secret', 'two_factor_enabled', 'two_factor_backup_codes']);
        }

        if ($this->db->fieldExists('ip_address', 'audit_logs')) {
            $this->forge->dropColumn('audit_logs', ['ip_address', 'user_agent', 'notes']);
        }
    }
}
