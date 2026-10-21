<?php

namespace App\Commands;

use App\Libraries\Core\ModuleInstallationService;
use CodeIgniter\CLI\{BaseCommand, CLI};

final class ModulesRegister extends BaseCommand
{
    protected $group = 'Codex Core';
    protected $name = 'modules:register';
    protected $description = 'Previsualiza el alta técnica de un módulo; --apply registra sin otorgar accesos.';
    protected $usage = 'modules:register <slug> [--apply]';
    protected $options = ['--apply'=>'Guardar la verificación; un sistema nuevo se crea inactivo.'];

    public function run(array $params)
    {
        try {
            $result = (new ModuleInstallationService())->register((string)($params[0] ?? ''), CLI::getOption('apply') !== null);
            CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return EXIT_SUCCESS;
        } catch (\Throwable $e) {
            CLI::error($e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar. Revisar el log del servidor.');
            log_message('error', 'Module registration failed: {message}', ['message'=>$e->getMessage()]);
            return EXIT_ERROR;
        }
    }
}
