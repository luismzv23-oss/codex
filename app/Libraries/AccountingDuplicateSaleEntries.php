<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Finds and removes sale journal entries that were posted twice by the pre-2026-10-16 bug.
 * Only exact copies (same date, total, status and identical lines) are removed; the lowest
 * entry_number of each sale is kept. Anything else is reported for manual review.
 */
final class AccountingDuplicateSaleEntries
{
    public function analyze(?string $companyId = null): array
    {
        $db = db_connect();
        $groups = $db->table('journal_entries')->select('company_id, reference_id')
            ->where('reference_type', 'sale')->where('reference_id IS NOT NULL', null, false);
        if ($companyId) {
            $groups->where('company_id', $companyId);
        }
        $groups = $groups->groupBy('company_id, reference_id')->having('COUNT(*) >', 1)->get()->getResultArray();

        $result = ['duplicates' => [], 'manual' => [], 'skipped' => [], 'impact' => []];
        $accounts = $db->tableExists('accounts')
            ? array_column($db->table('accounts')->select('id, code, name')->get()->getResultArray(), null, 'id')
            : [];

        foreach ($groups as $group) {
            $company = (string) $group['company_id'];
            $saleId = (string) $group['reference_id'];
            $saleNumber = $db->table('sales')->select('sale_number')->where('id', $saleId)->get()->getRowArray()['sale_number'] ?? $saleId;
            $entries = $db->table('journal_entries')->where('company_id', $company)->where('reference_type', 'sale')
                ->where('reference_id', $saleId)->orderBy('entry_number', 'ASC')->get()->getResultArray();

            if ($db->table('journal_entries')->where('company_id', $company)->where('reference_type', 'sale_cancellation')->where('reference_id', $saleId)->countAllResults() > 0) {
                $result['skipped'][] = ['company_id' => $company, 'sale' => $saleNumber, 'reason' => 'La venta tiene asiento de anulacion.'];
                continue;
            }

            $keep = array_shift($entries);
            $keepLines = $this->lines((string) $keep['id']);
            $keepSignature = $this->signature($keepLines);

            foreach ($entries as $entry) {
                $lines = $this->lines((string) $entry['id']);
                $sameHeader = $entry['entry_date'] === $keep['entry_date'] && $entry['status'] === 'posted' && $keep['status'] === 'posted'
                    && round((float) $entry['total_debit'], 2) === round((float) $keep['total_debit'], 2);
                if (! $sameHeader || $this->signature($lines) !== $keepSignature) {
                    $result['manual'][] = ['company_id' => $company, 'sale' => $saleNumber, 'entry_number' => $entry['entry_number'], 'reason' => 'No es copia exacta del asiento ' . $keep['entry_number'] . '.'];
                    continue;
                }
                if ($this->inClosedPeriod($company, (string) $entry['entry_date'])) {
                    $result['skipped'][] = ['company_id' => $company, 'sale' => $saleNumber, 'reason' => 'Periodo fiscal cerrado (' . $entry['entry_date'] . ').'];
                    continue;
                }

                $result['duplicates'][] = [
                    'company_id' => $company, 'sale_id' => $saleId, 'sale' => $saleNumber,
                    'keep_id' => $keep['id'], 'keep_number' => (int) $keep['entry_number'],
                    'remove_id' => $entry['id'], 'remove_number' => (int) $entry['entry_number'],
                    'entry_date' => $entry['entry_date'], 'total' => round((float) $entry['total_debit'], 2),
                ];
                foreach ($lines as $line) {
                    $account = (string) $line['account_id'];
                    $row = &$result['impact'][$company][$account];
                    $row ??= ['code' => $accounts[$account]['code'] ?? $account, 'name' => $accounts[$account]['name'] ?? '', 'debit' => 0.0, 'credit' => 0.0];
                    $row['debit'] += (float) $line['debit'];
                    $row['credit'] += (float) $line['credit'];
                    unset($row);
                }
            }
        }

        return $result;
    }

    /** Writes the backup first, then deletes inside one transaction. Returns counts. */
    public function apply(array $analysis, string $backupPath): array
    {
        $db = db_connect();
        $duplicates = $analysis['duplicates'] ?? [];
        if ($duplicates === []) {
            return ['entries' => 0, 'lines' => 0, 'backup' => null];
        }

        $ids = array_column($duplicates, 'remove_id');
        $entries = [];
        $lines = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            $entries = array_merge($entries, $db->table('journal_entries')->whereIn('id', $chunk)->get()->getResultArray());
            $lines = array_merge($lines, $db->table('journal_entry_lines')->whereIn('journal_entry_id', $chunk)->get()->getResultArray());
        }
        if (count($entries) !== count($ids)) {
            throw new RuntimeException('Los asientos cambiaron desde el analisis. Vuelve a ejecutar el informe.');
        }

        $dir = dirname($backupPath);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de respaldo: ' . $dir);
        }
        $json = json_encode(['created_at' => date('c'), 'journal_entries' => $entries, 'journal_entry_lines' => $lines], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($backupPath, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('No se pudo escribir el respaldo; no se modificaron datos.');
        }

        if (! $db->transBegin()) {
            throw new RuntimeException('No se pudo iniciar la transaccion.');
        }
        try {
            $deletedLines = 0;
            $deletedEntries = 0;
            foreach (array_chunk($duplicates, 200) as $chunk) {
                $chunkIds = array_column($chunk, 'remove_id');
                $db->table('journal_entry_lines')->whereIn('journal_entry_id', $chunkIds)->delete();
                $deletedLines += $db->affectedRows();
                foreach (array_unique(array_column($chunk, 'company_id')) as $company) {
                    $companyIds = array_column(array_filter($chunk, static fn(array $d): bool => $d['company_id'] === $company), 'remove_id');
                    $db->table('journal_entries')->where('company_id', $company)->where('reference_type', 'sale')->whereIn('id', $companyIds)->delete();
                    $deletedEntries += $db->affectedRows();
                }
            }
            if ($deletedEntries !== count($ids) || $deletedLines !== count($lines)) {
                throw new RuntimeException("Se esperaban " . count($ids) . " asientos y " . count($lines) . " lineas; se borraron $deletedEntries y $deletedLines.");
            }

            foreach (array_unique(array_column($duplicates, 'sale_id')) as $saleId) {
                if ($db->table('journal_entries')->where('reference_type', 'sale')->where('reference_id', $saleId)->countAllResults() !== 1) {
                    throw new RuntimeException('La venta ' . $saleId . ' no quedo con un unico asiento.');
                }
            }
            foreach (array_unique(array_column($duplicates, 'company_id')) as $company) {
                $sum = $db->table('journal_entry_lines l')->select('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c', false)
                    ->join('journal_entries j', 'j.id = l.journal_entry_id')->where('j.company_id', $company)->get()->getRowArray();
                if (abs((float) $sum['d'] - (float) $sum['c']) > 0.01) {
                    throw new RuntimeException('El libro de la empresa ' . $company . ' quedaria desbalanceado.');
                }
            }

            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new RuntimeException('No se pudo confirmar la depuracion.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return ['entries' => $deletedEntries, 'lines' => $deletedLines, 'backup' => $backupPath];
    }

    private function lines(string $entryId): array
    {
        return db_connect()->table('journal_entry_lines')->where('journal_entry_id', $entryId)->get()->getResultArray();
    }

    private function signature(array $lines): string
    {
        $parts = array_map(static fn(array $l): string => $l['account_id'] . ':' . number_format((float) $l['debit'], 2, '.', '') . ':' . number_format((float) $l['credit'], 2, '.', ''), $lines);
        sort($parts);

        return implode('|', $parts);
    }

    private function inClosedPeriod(string $companyId, string $date): bool
    {
        $db = db_connect();
        if (! $db->tableExists('fiscal_periods')) {
            return false;
        }

        return $db->table('fiscal_periods')->where('company_id', $companyId)->where('status !=', 'open')
            ->where('start_date <=', $date)->where('end_date >=', $date)->countAllResults() > 0;
    }
}
