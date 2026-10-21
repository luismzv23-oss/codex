<?php
return [
    'slug'=>'comercial','name'=>'Comercial','version'=>'1.0.0','owner'=>'Codex / Comercial',
    'core_min'=>'1.0.0','core_max'=>'1.99.99','entry'=>'ventas/diarios','icon'=>'bi-briefcase','company_scoped'=>true,
    'description'=>'Operación comercial sobre el circuito de ventas.','dependencies'=>['ventas'=>'1.0.0'],
    'schema'=>['sales'=>['id','company_id']],
    'settings'=>[],
];
