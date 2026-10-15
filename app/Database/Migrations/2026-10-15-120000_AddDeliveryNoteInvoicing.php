<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeliveryNoteInvoicing extends Migration
{
    public function up()
    {
        $uuid = ['type' => 'CHAR', 'constraint' => 36];
        $this->forge->addField([
            'id' => $uuid, 'company_id' => $uuid,
            'source_sale_id' => $uuid + ['null' => true],
            'delivery_note_id' => $uuid + ['null' => true],
            'invoice_id' => $uuid,
            'mode' => ['type' => 'VARCHAR', 'constraint' => 10],
            'request_key' => $uuid,
            'created_by' => $uuid + ['null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'request_key']);
        $this->forge->addUniqueKey('invoice_id');
        $this->forge->addKey(['company_id', 'source_sale_id']);
        $this->forge->addKey(['company_id', 'delivery_note_id']);
        $this->forge->addForeignKey('source_sale_id', 'sales', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('delivery_note_id', 'sales_delivery_notes', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('invoice_id', 'sales', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('sales_delivery_invoices', true);
        $this->forge->addField([
            'id' => $uuid, 'delivery_invoice_id' => $uuid,
            'source_sale_item_id' => $uuid + ['null' => true],
            'delivery_note_item_id' => $uuid + ['null' => true],
            'invoice_item_id' => $uuid,
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '12,4'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('invoice_item_id');
        $this->forge->addKey('source_sale_item_id');
        $this->forge->addKey('delivery_note_item_id');
        $this->forge->addForeignKey('delivery_invoice_id', 'sales_delivery_invoices', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('source_sale_item_id', 'sale_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('delivery_note_item_id', 'sales_delivery_note_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('invoice_item_id', 'sale_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('sales_delivery_invoice_items', true);
        foreach (['cancelled_by' => $uuid + ['null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'cancellation_reason' => ['type' => 'TEXT', 'null' => true],
            'return_confirmed_at' => ['type' => 'DATETIME', 'null' => true],
        ] as $column => $definition) {
            if (!$this->db->fieldExists($column, 'sales_delivery_notes')) {
                $this->forge->addColumn('sales_delivery_notes', [$column => $definition]);
            }
        }
    }

    public function down()
    {
        if ($this->db->table('sales_delivery_invoices')->countAllResults()) {
            throw new \RuntimeException('Existen facturas vinculadas a remitos; no se puede retirar su trazabilidad.');
        }
        $this->forge->dropTable('sales_delivery_invoice_items');
        $this->forge->dropTable('sales_delivery_invoices');
        $this->forge->dropColumn('sales_delivery_notes', ['cancelled_by', 'cancelled_at', 'cancellation_reason', 'return_confirmed_at']);
    }
}
