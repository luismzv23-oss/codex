<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesSettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'profile' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'argentina_arca'],
            'invoice_mode_standard_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'invoice_mode_kiosk_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'default_currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'allow_negative_stock_sales' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'strict_company_currencies' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'arca_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'arca_environment' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'homologacion'],
            'arca_cuit' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'arca_iva_condition' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'arca_iibb' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'arca_start_activities' => ['type' => 'DATE', 'null' => true],
            'point_of_sale_standard' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'point_of_sale_kiosk' => ['type' => 'INT', 'constraint' => 11, 'default' => 2],
            'kiosk_document_label' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Ticket Consumidor Final'],
            'wsaa_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'wsfev1_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'wsmtxca_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'wsfexv1_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'wsbfev1_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'wsct_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'wsseg_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'certificate_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'private_key_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'token_cache_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_settings');
    }

    public function down()
    {
        $this->forge->dropTable('sales_settings', true);
    }
}
