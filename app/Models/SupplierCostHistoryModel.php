<?php

namespace App\Models;

class SupplierCostHistoryModel extends BaseUuidModel
{
    protected $table = 'supplier_cost_history';
    protected $allowedFields = [
        'id', 'company_id', 'supplier_id', 'product_id', 'purchase_receipt_id', 'purchase_invoice_id',
        'currency_code', 'exchange_rate', 'unit_cost', 'observed_at', 'created_at', 'updated_at',
    ];
}
