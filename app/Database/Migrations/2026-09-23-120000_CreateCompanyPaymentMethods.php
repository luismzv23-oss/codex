<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompanyPaymentMethods extends Migration
{
    public function up()
    {
        $fields = [
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'funds_destination' => ['type' => 'VARCHAR', 'constraint' => 20],
        ];
        foreach (['currency_ids', 'required_fields', 'branch_ids', 'point_of_sale_ids'] as $field) {
            $fields[$field] = ['type' => 'TEXT'];
        }
        foreach (['active', 'allows_installments', 'requires_confirmation'] as $field) {
            $fields[$field] = ['type' => 'TINYINT', 'default' => $field === 'active' ? 1 : 0];
        }
        foreach (['created_at', 'updated_at', 'deleted_at'] as $field) {
            $fields[$field] = ['type' => 'DATETIME', 'null' => true];
        }
        $this->forge->addField($fields);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->createTable('company_payment_methods');
    }

    public function down()
    {
        $this->forge->dropTable('company_payment_methods');
    }
}
