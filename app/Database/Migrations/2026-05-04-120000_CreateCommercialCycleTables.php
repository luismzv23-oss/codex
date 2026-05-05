<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCommercialCycleTables extends Migration
{
    public function up()
    {
        // ── 1. Sales Quotes (Presupuestos) ──────────────
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'       => ['type' => 'CHAR', 'constraint' => 36],
            'customer_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'quote_number'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'quote_date'       => ['type' => 'DATE'],
            'valid_until'      => ['type' => 'DATE', 'null' => true],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft', 'comment' => 'draft|sent|approved|rejected|expired|converted'],
            'currency_code'    => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'ARS'],
            'exchange_rate'    => ['type' => 'DECIMAL', 'constraint' => '12,6', 'default' => 1],
            'subtotal'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'tax_total'        => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'discount_total'   => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'total'            => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'customer_name_snapshot'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'customer_document_snapshot' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'customer_tax_profile'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'sales_agent_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sales_zone_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sales_condition_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'price_list_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'notes'            => ['type' => 'TEXT', 'null' => true],
            'internal_notes'   => ['type' => 'TEXT', 'null' => true],
            'converted_to_order_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_by'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'approved_by'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'approved_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'quote_number']);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('status');
        $this->forge->createTable('sales_quotes', true);

        // ── 2. Sales Quote Items ────────────────────────
        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36],
            'sales_quote_id'  => ['type' => 'CHAR', 'constraint' => 36],
            'product_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sku'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'product_name'    => ['type' => 'VARCHAR', 'constraint' => 200],
            'quantity'        => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 1],
            'unit_price'      => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => 0],
            'discount_pct'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'tax_rate'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 21],
            'line_subtotal'   => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'line_tax'        => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'line_total'      => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'sort_order'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sales_quote_id');
        $this->forge->addForeignKey('sales_quote_id', 'sales_quotes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_quote_items', true);

        // ── 3. Sales Orders (Pedidos) ───────────────────
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'       => ['type' => 'CHAR', 'constraint' => 36],
            'customer_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sales_quote_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'order_number'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'order_date'       => ['type' => 'DATE'],
            'expected_delivery_date' => ['type' => 'DATE', 'null' => true],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending', 'comment' => 'pending|approved|partial|fulfilled|cancelled'],
            'currency_code'    => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'ARS'],
            'exchange_rate'    => ['type' => 'DECIMAL', 'constraint' => '12,6', 'default' => 1],
            'subtotal'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'tax_total'        => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'discount_total'   => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'total'            => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'customer_name_snapshot'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'customer_document_snapshot' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'customer_tax_profile'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'sales_agent_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sales_zone_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sales_condition_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'price_list_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'notes'            => ['type' => 'TEXT', 'null' => true],
            'internal_notes'   => ['type' => 'TEXT', 'null' => true],
            'converted_to_sale_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_by'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'approved_by'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'approved_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'order_number']);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('sales_quote_id');
        $this->forge->addKey('status');
        $this->forge->createTable('sales_orders', true);

        // ── 4. Sales Order Items ────────────────────────
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'sales_order_id'   => ['type' => 'CHAR', 'constraint' => 36],
            'product_id'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sku'              => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'product_name'     => ['type' => 'VARCHAR', 'constraint' => 200],
            'quantity'         => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 1],
            'quantity_delivered' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 0],
            'quantity_invoiced'  => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 0],
            'unit_price'       => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => 0],
            'discount_pct'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'tax_rate'         => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 21],
            'line_subtotal'    => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'line_tax'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'line_total'       => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'sort_order'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sales_order_id');
        $this->forge->addForeignKey('sales_order_id', 'sales_orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_order_items', true);

        // ── 5. Sales Delivery Notes (Remitos) ───────────
        $this->forge->addField([
            'id'               => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'       => ['type' => 'CHAR', 'constraint' => 36],
            'customer_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sales_order_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sale_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'delivery_number'  => ['type' => 'VARCHAR', 'constraint' => 40],
            'delivery_date'    => ['type' => 'DATE'],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending', 'comment' => 'pending|dispatched|delivered|cancelled'],
            'warehouse_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'shipping_address' => ['type' => 'TEXT', 'null' => true],
            'carrier'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tracking_number'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'customer_name_snapshot'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'customer_document_snapshot' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'notes'            => ['type' => 'TEXT', 'null' => true],
            'dispatched_at'    => ['type' => 'DATETIME', 'null' => true],
            'delivered_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_by'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'delivery_number']);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('sales_order_id');
        $this->forge->addKey('sale_id');
        $this->forge->addKey('status');
        $this->forge->createTable('sales_delivery_notes', true);

        // ── 6. Sales Delivery Note Items ────────────────
        $this->forge->addField([
            'id'                     => ['type' => 'CHAR', 'constraint' => 36],
            'sales_delivery_note_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sales_order_item_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'product_id'             => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'sku'                    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'product_name'           => ['type' => 'VARCHAR', 'constraint' => 200],
            'quantity'               => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 1],
            'warehouse_id'           => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'lot_number'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'serial_number'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'sort_order'             => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sales_delivery_note_id');
        $this->forge->addForeignKey('sales_delivery_note_id', 'sales_delivery_notes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_delivery_note_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('sales_delivery_note_items', true);
        $this->forge->dropTable('sales_delivery_notes', true);
        $this->forge->dropTable('sales_order_items', true);
        $this->forge->dropTable('sales_orders', true);
        $this->forge->dropTable('sales_quote_items', true);
        $this->forge->dropTable('sales_quotes', true);
    }
}
