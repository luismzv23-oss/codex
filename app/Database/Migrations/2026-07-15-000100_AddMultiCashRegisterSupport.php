<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMultiCashRegisterSupport extends Migration
{
    public function up()
    {
        // 1. Add account_id column to cash_registers for per-register accounting
        $this->forge->addColumn('cash_registers', [
            'account_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'active',
            ],
            'sales_point_of_sale_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'account_id',
            ],
        ]);

        // 2. Add foreign keys
        $this->db->query(
            'ALTER TABLE cash_registers ADD CONSTRAINT fk_cash_registers_account_id FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL ON UPDATE CASCADE'
        );
        $this->db->query(
            'ALTER TABLE cash_registers ADD CONSTRAINT fk_cash_registers_pos_id FOREIGN KEY (sales_point_of_sale_id) REFERENCES sales_points_of_sale(id) ON DELETE SET NULL ON UPDATE CASCADE'
        );

        // 3. Seed max_cash_registers setting for each existing company that lacks it
        $companies = $this->db->table('companies')->select('id')->get()->getResultArray();
        foreach ($companies as $company) {
            $exists = $this->db->table('company_settings')
                ->where('company_id', $company['id'])
                ->where('key', 'max_cash_registers')
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('company_settings')->insert([
                    'id'         => bin2hex(random_bytes(18)),
                    'company_id' => $company['id'],
                    'key'        => 'max_cash_registers',
                    'value'      => '10',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        // Drop foreign keys first
        $db->query('ALTER TABLE cash_registers DROP FOREIGN KEY fk_cash_registers_pos_id');
        $db->query('ALTER TABLE cash_registers DROP FOREIGN KEY fk_cash_registers_account_id');

        // Drop columns
        if ($db->fieldExists('sales_point_of_sale_id', 'cash_registers')) {
            $this->forge->dropColumn('cash_registers', 'sales_point_of_sale_id');
        }
        if ($db->fieldExists('account_id', 'cash_registers')) {
            $this->forge->dropColumn('cash_registers', 'account_id');
        }

        // Remove max_cash_registers settings
        $db->table('company_settings')->where('key', 'max_cash_registers')->delete();
    }
}
