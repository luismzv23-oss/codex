<?php
namespace App\Models;
class PurchaseImportItemModel extends BaseUuidModel
{
    protected $table='purchase_import_items';
    protected $allowedFields=['import_id','line_number','data_json'];
}
