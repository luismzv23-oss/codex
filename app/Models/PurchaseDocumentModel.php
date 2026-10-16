<?php
namespace App\Models;
class PurchaseDocumentModel extends BaseUuidModel
{
    protected $table='purchase_documents';
    protected $allowedFields=['company_id','sha256','storage_path','original_name','mime_type','byte_size','page_count','created_by'];
}
