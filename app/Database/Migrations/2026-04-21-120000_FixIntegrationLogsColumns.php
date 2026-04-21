<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds missing columns to integration_logs table.
 *
 * The table was created by 2026-04-08-160000 with columns:
 *   provider, service, reference_type, reference_id, status, request_payload,
 *   response_payload, message, user_id
 *
 * ArcaService::logIntegration expects additional columns:
 *   service_slug, operation, environment, source_type, source_id, cae, error_message, duration_ms
 */
class FixIntegrationLogsColumns extends Migration
{
    public function up()
    {
        $this->forge->addColumn('integration_logs', [
            'service_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
                'after'      => 'company_id',
            ],
            'operation' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'after'      => 'service_slug',
            ],
            'environment' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'homologacion',
                'after'      => 'operation',
            ],
            'source_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'status',
            ],
            'source_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'source_type',
            ],
            'cae' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'source_id',
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'response_payload',
            ],
            'duration_ms' => [
                'type'    => 'INT',
                'null'    => true,
                'after'   => 'error_message',
                'comment' => 'Request duration in milliseconds',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('integration_logs', [
            'service_slug',
            'operation',
            'environment',
            'source_type',
            'source_id',
            'cae',
            'error_message',
            'duration_ms',
        ]);
    }
}
