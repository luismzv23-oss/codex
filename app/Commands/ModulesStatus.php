<?php

namespace App\Commands;

use App\Libraries\Core\ModuleInstallationService;
use CodeIgniter\CLI\{BaseCommand, CLI};

final class ModulesStatus extends BaseCommand
{
    protected $group = 'Codex Core';
    protected $name = 'modules:status';
    protected $description = 'Diagnóstico de manifiestos, rutas, esquema y verificaciones; solo lectura.';

    public function run(array $params)
    {
        $report = (new ModuleInstallationService())->report();
        CLI::write('Contrato básico de integración. No certifica operación ni servicios externos.');
        CLI::write('Registro de verificaciones: '.($report['tracking_available'] ? 'disponible' : 'pendiente: php spark migrate -n CodexCore'));
        $invalid = $report['errors'] !== [];
        foreach ($report['errors'] as $error) CLI::error($error);
        foreach ($report['systems'] as $row) {
            $check = $row['technical'];
            $invalid = $invalid || !$check['ok'];
            CLI::write($row['slug'].' | '.($check['version'] ?? '-').' | '.($check['ok'] ? 'contrato OK' : 'revisar').' | '.($row['current'] ? 'verificación vigente' : 'sin verificación vigente'));
            foreach ($check['issues'] as $issue) CLI::write('  '.$issue);
        }
        return $invalid ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
