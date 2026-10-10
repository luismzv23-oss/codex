<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ConfigureCollections extends BaseCommand
{
    protected $group = 'Accounting';
    protected $name = 'collections:configure';
    protected $description = 'Planifica o configura cuentas de cobranzas sin generar asientos.';
    protected $options = ['--apply' => 'Confirma las asignaciones; sin esta opcion revierte la planificacion.'];

    public function run(array $params)
    {
        helper('app');
        $results = [];
        foreach (db_connect()->table('companies')->select('id')->orderBy('id')->get()->getResultArray() as $company) {
            $results[] = (new \App\Libraries\CollectionAccountSetup())->configure($company['id'], CLI::getOption('apply') !== null);
        }
        CLI::write(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
