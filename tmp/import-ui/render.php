<?php
require getcwd().'/vendor/codeigniter4/framework/system/Test/bootstrap.php';
helper(['app','auth','url']);config('App')->baseURL='http://localhost:8080/';
$html=view('purchases/import',['pageTitle'=>'Importar factura','companyId'=>'qa','context'=>['company'=>['id'=>'qa','name'=>'Empresa de prueba'],'canManage'=>true],'isPopup'=>false,'options'=>['suppliers'=>[['id'=>'s','name'=>'Proveedor Ejemplo','tax_id'=>'30123456789']],'products'=>[['id'=>'p','sku'=>'SKU1','name'=>'Producto']],'receipts'=>[],'currencies'=>['ARS'=>'Pesos argentinos']]],['saveData'=>false]);
file_put_contents('tmp/import-ui/wizard.html',$html);
