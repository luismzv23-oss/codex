<?php
return [
    'slug'=>'compras','name'=>'Compras','version'=>'1.0.0','owner'=>'Codex / Compras',
    'core_min'=>'1.0.0','core_max'=>'1.99.99','entry'=>'compras','icon'=>'bi-bag-check','company_scoped'=>true,
    'description'=>'Proveedores, compras y recepción de mercadería.','dependencies'=>['inventario'=>'1.0.0'],
    'schema'=>['suppliers'=>['id','company_id'],'purchase_orders'=>['id','company_id'],'purchase_receipts'=>['id','company_id']],
    'settings'=>[],
];
