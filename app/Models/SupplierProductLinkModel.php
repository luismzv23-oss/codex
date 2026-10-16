<?php
namespace App\Models;
class SupplierProductLinkModel extends BaseUuidModel
{
    protected $table='supplier_product_links';
    protected $allowedFields=['company_id','supplier_id','supplier_code','product_id','confirmed_by'];
}
