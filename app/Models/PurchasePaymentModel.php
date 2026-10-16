<?php

namespace App\Models;

class PurchasePaymentModel extends BaseUuidModel
{
    protected $table = 'purchase_payments';
    protected $allowedFields = [
        'payment_method_id', 'payment_method_code', 'payment_method_name', 'payment_method_snapshot', 'payment_data', 'currency_code', 'status', 'confirmed_by', 'confirmed_at', 'confirmation_evidence', 'request_key', 'request_hash', 'branch_id', 'point_of_sale_id', 'exchange_rate',
        'id', 'company_id', 'supplier_id', 'purchase_payable_id', 'payment_number', 'payment_method',
        'gateway_id', 'cash_check_id', 'currency_code', 'exchange_rate', 'amount', 'reference',
        'external_reference', 'paid_at', 'notes', 'created_by', 'created_at', 'updated_at',
    ];
}
