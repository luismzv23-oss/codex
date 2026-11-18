<?php

namespace App\Commands;

use App\Libraries\ReplenishmentAnalysisService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Ejecuta el análisis inteligente de reposición. Pensado para correr por cron (por ejemplo, lunes 7:00)
 * y dejar la corrida lista en Compras > Reposición.
 */
class ReplenishmentAnalyze extends BaseCommand
{
    protected $group = 'Compras';
    protected $name = 'compras:reposicion';
    protected $description = 'Analiza inventario y demanda, y guarda las sugerencias de compra de cada empresa.';
    protected $usage = 'compras:reposicion [company_id] [--dias 90] [--servicio 95] [--revision 7] [--ia] [--simular]';
    protected $options = [
        '--dias' => 'Días de historia de demanda (30, 60, 90, 180, 365).',
        '--servicio' => 'Nivel de servicio objetivo en % (90, 95, 97.5, 99).',
        '--revision' => 'Días entre revisiones de compra.',
        '--ia' => 'Genera además el resumen ejecutivo con el proveedor de IA configurado.',
        '--simular' => 'Muestra el resultado sin guardar la corrida.',
    ];

    public function run(array $params)
    {
        $companyId = $params[0] ?? null;
        $companies = db_connect()->table('companies')->select('id, name');
        if ($companyId) {
            $companies->where('id', $companyId);
        }
        $input = array_filter(['history_days' => CLI::getOption('dias'), 'service_level' => CLI::getOption('servicio'), 'review_days' => CLI::getOption('revision')], static fn($v) => $v !== null && $v !== true);
        $service = new ReplenishmentAnalysisService();

        foreach ($companies->get()->getResultArray() as $company) {
            $start = microtime(true);
            if (CLI::getOption('simular')) {
                $result = $service->analyze($company['id'], $input);
            } else {
                $result = $service->run($company['id'], $input, null);
                if (CLI::getOption('ia')) {
                    $ai = $service->aiSummary($company['id'], $result['id'], ['company' => $company['name']], true);
                    CLI::write('  Resumen (' . $ai['provider'] . "):\n" . $ai['summary']);
                }
            }
            $s = $result['summary'];
            CLI::write(sprintf('%s: %d analizados, %d a reponer (%d críticos), inversión $ %s, inmovilizado $ %s, sobrestock $ %s [%.2f s]',
                $company['name'], $s['analyzed'], $s['to_reorder'], $s['critical'], number_format($s['investment'], 2, ',', '.'),
                number_format($s['immobile_value'], 2, ',', '.'), number_format($s['overstock_value'], 2, ',', '.'), microtime(true) - $start), 'green');
            if (CLI::getOption('simular')) {
                foreach (array_slice(array_filter($result['rows'], static fn(array $r): bool => $r['status'] === 'reponer'), 0, 10) as $r) {
                    CLI::write(sprintf('  [%s/%s %s] %s: disp %s, en camino %s, %s u/sem (%s), LT %d, ROP %s → comprar %s', $r['abc'], $r['xyz'], $r['priority'], mb_substr($r['name'], 0, 40),
                        $r['available'], $r['on_order'], $r['weekly_forecast'], $r['forecast_method'], $r['lead_time'], $r['reorder_point'], $r['suggested_qty']));
                }
                $excess = array_filter($result['rows'], static fn(array $r): bool => $r['excess_value'] > 0);
                usort($excess, static fn(array $a, array $b): int => $b['excess_value'] <=> $a['excess_value']);
                foreach (array_slice($excess, 0, 5) as $r) {
                    CLI::write(sprintf('  [exceso] %s: stock %s, máx %s, %.2f u/día, objetivo %s → excedente %s u ($ %s)', mb_substr($r['name'], 0, 40),
                        $r['on_hand'], $r['max_stock'], $r['daily_demand'], $r['target_level'], $r['excess_qty'], number_format($r['excess_value'], 0, ',', '.')), 'yellow');
                }
                foreach ($result['insights'] as $insight) {
                    CLI::write('  - ' . $insight['text']);
                }
            }
        }
    }
}
