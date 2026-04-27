<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStatusToSalesReceipts extends Migration
{
    public function up()
    {
        // Add status column to sales_receipts if it doesn't exist
        if (! $this->db->fieldExists('status', 'sales_receipts')) {
            $this->forge->addColumn('sales_receipts', [
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'applied',
                    'after'      => 'total_amount',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('status', 'sales_receipts')) {
            $this->forge->dropColumn('sales_receipts', 'status');
        }
    }
}
