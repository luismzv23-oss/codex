<?php
namespace App\Commands;

use App\Libraries\Core\Events\{DeliveryStatus,DeliveryWorker};
use CodeIgniter\CLI\{BaseCommand,CLI};

abstract class IntegrationCommand extends BaseCommand
{
    protected $group='Codex Core';
    protected $options=['--company'=>'Empresa obligatoria','--limit'=>'Límite de entregas (1 a 200)','--delivery'=>'ID de entrega para reintento manual'];
    public function run(array $params)
    {
        $company=(string)(CLI::getOption('company') ?? '');
        if ($company==='' || !db_connect()->table('companies')->where('id',$company)->countAllResults()) {
            CLI::error('Indica una empresa existente mediante --company.'); return EXIT_ERROR;
        }
        try {
            if ($this->name==='integrations:status') $result=DeliveryStatus::summary($company);
            elseif ($this->name==='integrations:work') {
                $result=(new DeliveryWorker())->processBatch($company,(int)(CLI::getOption('limit') ?? 50));
            } else {
                (new DeliveryWorker())->retryFailed($company,(string)(CLI::getOption('delivery') ?? ''));
                $result=['retry_requested'=>true];
            }
            CLI::write(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            return EXIT_SUCCESS;
        } catch (\Throwable $e) {
            CLI::error('No se pudo completar la operación. Revisar empresa, estado, esquema y consumidor.');
            log_message('error','Integration command {command} failed ({type}).',['command'=>$this->name,'type'=>get_class($e)]);
            return EXIT_ERROR;
        }
    }
}
