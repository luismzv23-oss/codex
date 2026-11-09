<?php
namespace App\Models;
class SupplierProductLinkModel extends BaseUuidModel
{
    protected $table='supplier_product_links';
    protected $allowedFields=['company_id','supplier_id','supplier_code','product_id','units_per_package','confirmed_by'];
}
