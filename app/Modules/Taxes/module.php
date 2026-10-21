<?php
return [
    'slug'=>'impuestos','name'=>'Impuestos','version'=>'1.0.0','owner'=>'Codex / Impuestos',
    'core_min'=>'1.0.0','core_max'=>'1.99.99','entry'=>'impuestos','icon'=>'bi-percent','company_scoped'=>true,
    'description'=>'Libros de IVA y controles impositivos.','dependencies'=>[],
    'schema'=>['vat_sales_books'=>['company_id'],'vat_purchase_books'=>['company_id']],
    'settings'=>[],
];
