<?php
return [
    'slug'=>'caja','name'=>'Caja','version'=>'1.0.0','owner'=>'Codex / Tesorería',
    'core_min'=>'1.0.0','core_max'=>'1.99.99','entry'=>'caja','icon'=>'bi-cash-stack','company_scoped'=>true,
    'description'=>'Cajas, sesiones y movimientos de tesorería.','dependencies'=>[],
    'schema'=>['cash_registers'=>['id','company_id'],'cash_sessions'=>['id','company_id'],'cash_movements'=>['id','company_id']],
    'settings'=>[],
];
