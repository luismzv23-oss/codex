<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PurchaseDocumentPayables extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('purchase_payables', ['purchase_receipt_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true]]);
        $this->forge->addColumn('purchase_payables', ['purchase_invoice_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true]]);
        $this->forge->addColumn('purchase_credit_notes', ['purchase_return_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true]]);
    }

    public function down()
    {
        if ($this->db->table('purchase_payables')->where('purchase_receipt_id', null)->countAllResults()
            || $this->db->table('purchase_credit_notes')->where('purchase_return_id !=', null)->countAllResults()) {
            throw new \RuntimeException('No se puede revertir: existen facturas directas o notas vinculadas a devoluciones.');
        }
        $this->forge->dropColumn('purchase_credit_notes', 'purchase_return_id');
        $this->forge->dropColumn('purchase_payables', 'purchase_invoice_id');
        $this->forge->modifyColumn('purchase_payables', ['purchase_receipt_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false]]);
    }
}
