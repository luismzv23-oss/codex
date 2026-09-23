<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HardenSalesPayments extends Migration
{
    public function up()
    {
        foreach (['gateway_id' => 36, 'cash_check_id' => 36, 'external_reference' => 120, 'sales_receipt_id' => 36] as $field => $length) {
            if (! $this->db->fieldExists($field, 'sale_payments')) {
                $this->forge->addColumn('sale_payments', [$field => ['type' => 'VARCHAR', 'constraint' => $length, 'null' => true]]);
            }
        }
        foreach (['payment_details', 'confirmation_note', 'reversal_reason'] as $field) {
            if (! $this->db->fieldExists($field, 'sales_receipts')) {
                $this->forge->addColumn('sales_receipts', [$field => ['type' => 'TEXT', 'null' => true]]);
            }
        }
        foreach (['confirmed_by', 'reversed_by'] as $field) {
            $this->forge->addColumn('sales_receipts', [$field => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true]]);
        }
        foreach (['confirmed_at', 'reversed_at'] as $field) {
            $this->forge->addColumn('sales_receipts', [$field => ['type' => 'DATETIME', 'null' => true]]);
        }
    }

    public function down()
    {
        // Preserve the payment history: this migration is intentionally forward-only.
    }
}
