<?php
return [
    'slug'=>'contabilidad','name'=>'Contabilidad','version'=>'1.0.0','owner'=>'Codex / Contabilidad',
    'core_min'=>'1.0.0','core_max'=>'1.99.99','entry'=>'contabilidad/diario','icon'=>'bi-journal-bookmark','company_scoped'=>true,
    'description'=>'Cuentas y asientos contables.','dependencies'=>[],
    'schema'=>['accounting_accounts'=>['id','company_id'],'accounting_entries'=>['id','company_id'],'accounting_entry_items'=>['accounting_entry_id']],
    'settings'=>[],
];
