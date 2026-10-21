<?php
return [
    'slug'=>'ventas','name'=>'Ventas','version'=>'1.0.0','owner'=>'Codex / Ventas',
    'core_min'=>'1.0.0','core_max'=>'1.99.99','entry'=>'ventas','icon'=>'bi-cart-check','company_scoped'=>true,
    'description'=>'Ventas, facturación y cobranzas.','dependencies'=>['inventario'=>'1.0.0'],
    'schema'=>['sales'=>['id','company_id'],'sale_items'=>['sale_id'],'sales_settings'=>['company_id']],
    'settings'=>[
        ['name'=>'Ventas','path'=>'ventas/configuracion','icon'=>'bi-receipt','description'=>'Argentina ARCA, monedas habilitadas, facturación estándar y kiosco.'],
        ['name'=>'Impresión y Tickets','path'=>'configuracion/tickets','icon'=>'bi-printer','description'=>'Diseño de comprobantes, ancho de papel, cabeceras y leyendas.','popup'=>true],
    ],
];
