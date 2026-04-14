<?php

namespace App\Models;

class SalesSettingModel extends BaseUuidModel
{
    protected $table = 'sales_settings';
    protected $allowedFields = [
        'id','company_id','profile','invoice_mode_standard_enabled','invoice_mode_kiosk_enabled','default_currency_code',
        'allow_negative_stock_sales','strict_company_currencies','arca_enabled','arca_environment','arca_cuit','arca_iva_condition',
        'arca_iibb','arca_start_activities','arca_alias','arca_auto_authorize','arca_certificate_expires_at','arca_last_wsaa_at',
        'arca_last_ticket_expires_at','arca_last_sync_at','arca_last_error','point_of_sale_standard','point_of_sale_kiosk','kiosk_document_label',
        'wsaa_enabled','wsfev1_enabled','wsmtxca_enabled','wsfexv1_enabled','wsbfev1_enabled','wsct_enabled','wsseg_enabled',
        'certificate_path','private_key_path','token_cache_path','created_at','updated_at'
    ];
}
