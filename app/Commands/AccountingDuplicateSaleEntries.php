<?php

namespace App\Commands;

use App\Libraries\AccountingDuplicateSaleEntries as Cleaner;
use CodeIgniter\CLI\{BaseCommand, CLI};

/**
 * Report (default) or remove exact duplicate sale journal entries.
 * Restore: the JSON backup holds the full deleted rows of journal_entries and
 * journal_entry_lines; re-insert them (entries first, then lines) to undo.
 */
class AccountingDuplicateSaleEntries extends BaseCommand
{
    protected $group = 'Contabilidad';
    protected $name = 'contabilidad:depurar-duplicados';
    protected $description = 'Detecta asientos de venta duplicados. Por defecto solo informa; con --aplicar los elimina guardando un respaldo JSON.';
    protected $options = [
        '--company' => 'Limita a el UUID de una empresa.',
        '--aplicar' => 'Elimina los duplicados (antes escribe el respaldo en writable/backups).',
    ];

    public function run(array $params)
    {
        $company = CLI::getOption('company') ?: null;
        $apply = array_key_exists('aplicar', CLI::getOptions());
        $cleaner = new Cleaner();
        $analysis = $cleaner->analyze(is_string($company) ? $company : null);
        $stamp = date('Ymd-His');

        $byCompany = [];
        foreach ($analysis['duplicates'] as $row) {
            $byCompany[$row['company_id']][] = $row;
        }
        CLI::write('Asientos de venta duplicados: ' . count($analysis['duplicates']) . '.');
        foreach ($byCompany as $companyId => $rows) {
            CLI::newLine();
            CLI::write('Empresa ' . $companyId . ': ' . count($rows) . ' asientos a eliminar.', 'yellow');
            $impact = array_map(static fn(array $a): array => [$a['code'], $a['name'], number_format($a['debit'], 2, ',', '.'), number_format($a['credit'], 2, ',', '.')], $analysis['impact'][$companyId] ?? []);
            usort($impact, static fn(array $a, array $b): int => strcmp((string) $a[0], (string) $b[0]));
            CLI::table($impact, ['Cuenta', 'Nombre', 'Debe a quitar', 'Haber a quitar']);
            $csv = $this->writeCsv($companyId, $stamp, $rows);
            CLI::write('Listado: ' . $csv);
        }
        if ($analysis['manual']) {
            CLI::newLine();
            CLI::write('Revisar manualmente (no se tocan):', 'red');
            CLI::table(array_map(static fn(array $r): array => [$r['company_id'], $r['sale'], $r['entry_number'], $r['reason']], $analysis['manual']), ['Empresa', 'Venta', 'Asiento', 'Motivo']);
        }
        if ($analysis['skipped']) {
            CLI::newLine();
            CLI::write('Omitidos:', 'red');
            CLI::table(array_map(static fn(array $r): array => [$r['company_id'], $r['sale'], $r['reason']], $analysis['skipped']), ['Empresa', 'Venta', 'Motivo']);
        }

        if (! $apply) {
            CLI::newLine();
            CLI::write('No se modificaron datos. Usa --aplicar para eliminar los duplicados listados.', 'green');
            return EXIT_SUCCESS;
        }

        try {
            $done = $cleaner->apply($analysis, WRITEPATH . 'backups/asientos-duplicados-' . $stamp . '.json');
        } catch (\Throwable $e) {
            CLI::error('No se aplico ningun cambio: ' . $e->getMessage());
            return EXIT_ERROR;
        }
        CLI::newLine();
        CLI::write('Eliminados ' . $done['entries'] . ' asientos y ' . $done['lines'] . ' lineas.', 'green');
        if ($done['backup']) {
            CLI::write('Respaldo: ' . $done['backup']);
        }

        return EXIT_SUCCESS;
    }

    private function writeCsv(string $companyId, string $stamp, array $rows): string
    {
        $dir = WRITEPATH . 'reports';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'asientos-duplicados-' . substr($companyId, 0, 8) . '-' . $stamp . '.csv';
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Venta', 'Asiento que se conserva', 'Asiento que se elimina', 'Fecha', 'Importe'], ';');
        foreach ($rows as $row) {
            fputcsv($handle, [$row['sale'], $row['keep_number'], $row['remove_number'], $row['entry_date'], number_format($row['total'], 2, ',', '')], ';');
        }
        fclose($handle);

        return $path;
    }
}
