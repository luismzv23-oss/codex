<?php
return [
    'slug'=>'inventario','name'=>'Inventario','version'=>'1.0.0','owner'=>'Codex / Inventario',
    'core_min'=>'1.0.0','core_max'=>'1.99.99','entry'=>'inventario','icon'=>'bi-box-seam','company_scoped'=>true,
    'description'=>'Productos, existencias, movimientos y reservas.','dependencies'=>[],
    'schema'=>['inventory_products'=>['id','company_id'],'inventory_stock_levels'=>['company_id','product_id'],'inventory_settings'=>['company_id']],
    'settings'=>[['name'=>'Inventario','path'=>'inventario/configuracion','icon'=>'bi-box-seam','description'=>'Depósitos, alertas, stock negativo y políticas operativas.']],
];
