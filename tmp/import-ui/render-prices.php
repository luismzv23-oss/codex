<?php
require getcwd().'/vendor/codeigniter4/framework/system/Test/bootstrap.php';helper(['app','auth','url']);config('App')->baseURL='http://localhost:8080/';
file_put_contents('tmp/import-ui/prices.html',view('sales/forms/price_list',['pageTitle'=>'Promociones','companyId'=>'qa','isPopup'=>true],['saveData'=>false]));
