<?php
namespace App\Models;
class PurchaseImportModel extends BaseUuidModel
{
    protected $table='purchase_imports';
    protected $allowedFields=['company_id','document_id','processing_status','review_status','version','generation','data_json','extraction_json','invoice_id','confirmation_key','created_by','confirmed_by','confirmed_at'];
}
