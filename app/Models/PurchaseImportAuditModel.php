<?php
namespace App\Models;
class PurchaseImportAuditModel extends BaseUuidModel
{
    protected $table='purchase_import_audit';
    protected $allowedFields=['company_id','import_id','user_id','action','details_json'];
}
