<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeLogsCompanyIdNullable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $isMySQL = $db->DBDriver === 'MySQLi';

        // 1. Alter company_id in audit_logs to be nullable
        if ($isMySQL) {
            try {
                $db->query('ALTER TABLE audit_logs DROP FOREIGN KEY audit_logs_company_id_foreign');
            } catch (\Throwable $e) {
                // Ignore if it doesn't exist
            }
        }

        $this->forge->modifyColumn('audit_logs', [
            'company_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
            ]
        ]);

        if ($isMySQL) {
            try {
                $db->query('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE');
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // 2. Alter company_id in integration_logs to be nullable
        if ($isMySQL) {
            try {
                $db->query('ALTER TABLE integration_logs DROP FOREIGN KEY integration_logs_company_id_foreign');
            } catch (\Throwable $e) {
                // Ignore if it doesn't exist
            }
        }

        $this->forge->modifyColumn('integration_logs', [
            'company_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
            ]
        ]);

        
        if ($isMySQL) {
            try {
                $db->query('ALTER TABLE integration_logs ADD CONSTRAINT integration_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE');
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }

    public function down()
    {
        // Reverting to not null might fail if there are null values in the database,
        // so we leave it as nullable to prevent breaking down migration.
    }
}
