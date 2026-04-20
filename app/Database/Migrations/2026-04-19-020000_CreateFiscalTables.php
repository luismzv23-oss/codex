<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFiscalTables extends Migration
{
    public function up()
    {
        // ── 1. Tax Withholdings (Retenciones configuración) ──
        $this->forge->addField([
            'id'            => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'    => ['type' => 'CHAR', 'constraint' => 36],
            'tax_type'      => ['type' => 'VARCHAR', 'constraint' => 30, 'comment' => 'iva|iibb|ganancias|suss'],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'rate'          => ['type' => 'DECIMAL', 'constraint' => '8,4', 'default' => 0],
            'min_amount'    => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0, 'comment' => 'Monto mínimo sujeto a retención'],
            'applies_to'    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'purchases', 'comment' => 'sales|purchases|both'],
            'jurisdiction'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'comment' => 'Provincia para IIBB'],
            'active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('tax_withholdings', true);

        // ── 2. Applied Withholdings (Retenciones aplicadas) ──
        $this->forge->addField([
            'id'                 => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'         => ['type' => 'CHAR', 'constraint' => 36],
            'withholding_id'     => ['type' => 'CHAR', 'constraint' => 36],
            'source_type'        => ['type' => 'VARCHAR', 'constraint' => 50, 'comment' => 'purchase_payment|sale'],
            'source_id'          => ['type' => 'CHAR', 'constraint' => 36],
            'supplier_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'base_amount'        => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'rate'               => ['type' => 'DECIMAL', 'constraint' => '8,4', 'default' => 0],
            'amount'             => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'certificate_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'applied_at'         => ['type' => 'DATETIME'],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('source_id');
        $this->forge->createTable('tax_withholdings_applied', true);

        // ── 3. Tax Perceptions (Percepciones configuración) ──
        $this->forge->addField([
            'id'           => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'   => ['type' => 'CHAR', 'constraint' => 36],
            'tax_type'     => ['type' => 'VARCHAR', 'constraint' => 30, 'comment' => 'iva|iibb'],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'rate'         => ['type' => 'DECIMAL', 'constraint' => '8,4', 'default' => 0],
            'jurisdiction' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('tax_perceptions', true);

        // ── 4. Applied Perceptions (Percepciones aplicadas) ──
        $this->forge->addField([
            'id'             => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'     => ['type' => 'CHAR', 'constraint' => 36],
            'perception_id'  => ['type' => 'CHAR', 'constraint' => 36],
            'source_type'    => ['type' => 'VARCHAR', 'constraint' => 50, 'comment' => 'sale|purchase_invoice'],
            'source_id'      => ['type' => 'CHAR', 'constraint' => 36],
            'customer_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'base_amount'    => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'rate'           => ['type' => 'DECIMAL', 'constraint' => '8,4', 'default' => 0],
            'amount'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'applied_at'     => ['type' => 'DATETIME'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('source_id');
        $this->forge->createTable('tax_perceptions_applied', true);

        // ── 5. Integration logs (ARCA request/response tracking) ──
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'       => ['type' => 'CHAR', 'constraint' => 36],
            'service_slug'     => ['type' => 'VARCHAR', 'constraint' => 20, 'comment' => 'wsaa|wsfev1|wsmtxca'],
            'operation'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'environment'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'homologacion'],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'source_type'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'source_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'cae'              => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'request_payload'  => ['type' => 'LONGTEXT', 'null' => true],
            'response_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'error_message'    => ['type' => 'TEXT', 'null' => true],
            'duration_ms'      => ['type' => 'INT', 'null' => true, 'comment' => 'Request duration in milliseconds'],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey(['source_type', 'source_id']);
        $this->forge->createTable('integration_logs', true);
    }

    public function down()
    {
        $this->forge->dropTable('integration_logs', true);
        $this->forge->dropTable('tax_perceptions_applied', true);
        $this->forge->dropTable('tax_perceptions', true);
        $this->forge->dropTable('tax_withholdings_applied', true);
        $this->forge->dropTable('tax_withholdings', true);
    }
}
