<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeAuditLogsCompanyIdNullable extends Migration
{
    public function up(): void
    {
        // Drop FK first (required by MySQL before modifying the column)
        $this->forge->dropForeignKey('audit_logs', 'audit_logs_company_id_foreign');

        $this->forge->modifyColumn('audit_logs', [
            'company_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        // Re-add FK allowing NULL (MySQL ignores FK check when value is NULL)
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'SET NULL');
        $this->forge->processIndexes('audit_logs');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('audit_logs', 'audit_logs_company_id_foreign');

        $this->forge->modifyColumn('audit_logs', [
            'company_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => false,
            ],
        ]);

        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->processIndexes('audit_logs');
    }
}
