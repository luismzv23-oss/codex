<?php

namespace App\Models;

class CashPaymentGatewayModel extends BaseUuidModel
{
    protected $table = 'cash_payment_gateways';
    protected $allowedFields = [
        'id', 'company_id', 'name', 'code', 'gateway_type', 'provider', 'settings_json', 'active', 'created_at', 'updated_at',
    ];
}
