<?php
namespace App\Models;
class PurchaseImportJobModel extends BaseUuidModel
{
    protected $table='purchase_import_jobs';
    protected $allowedFields=['company_id','import_id','generation','status','attempts','lease_token','lease_until','available_at','operation_url','error_message','result_json','provider'];
}
