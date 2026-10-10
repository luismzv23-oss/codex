<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendCollections extends Migration
{
    public function up()
    {
        $columns = [
            'processing_version' => ['type' => 'INT', 'default' => 1],
            'receipt_kind' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'invoice'],
            'received_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'refunded_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
        ];
        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'sales_receipts')) $this->forge->addColumn('sales_receipts', [$name => $definition]);
        }
        if (!$this->db->fieldExists('settled_amount', 'sales_receipt_items')) $this->forge->addColumn('sales_receipt_items', [
            'settled_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
        ]);
        $this->db->table('sales_receipts')->where('processing_version', 1)->where('status', 'applied')->set('received_amount', 'total_amount', false)->update();
        $this->db->table('sales_receipt_items')->whereIn('sales_receipt_id',
            $this->db->table('sales_receipts')->select('id')->where('processing_version', 1)->where('status', 'applied')
        )->set('settled_amount', 'applied_amount', false)->update();
        if ($this->db->tableExists('collection_requests')) return;
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'request_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'payload_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'result_json' => ['type' => 'TEXT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'request_key']);
        $this->forge->createTable('collection_requests');
    }

    public function down()
    {
        if ($this->db->table('sales_receipts')->where('processing_version', 2)->countAllResults()) {
            throw new \RuntimeException('No se puede retirar el esquema mientras existan cobranzas con el nuevo circuito.');
        }
        $this->forge->dropTable('collection_requests');
        $this->forge->dropColumn('sales_receipt_items', 'settled_amount');
        $this->forge->dropColumn('sales_receipts', ['processing_version', 'receipt_kind', 'received_amount', 'refunded_amount']);
    }
}
