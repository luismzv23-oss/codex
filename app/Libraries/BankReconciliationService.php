<?php

namespace App\Libraries;

/**
 * BankReconciliationService — Smart Automated Bank Statement Reconciliation Engine.
 * Parses digital bank statements (CSV/OFX/Excel JSON), runs smart matching against system cash/bank movements,
 * and executes automated reconciliation with variance tracking.
 */
class BankReconciliationService
{
    /**
     * Reconcile an array of bank statement items against system movements.
     */
    public function reconcileStatement(string $companyId, string $bankAccountId, array $statementLines): array
    {
        $db = db_connect();

        // Fetch unreconciled bank movements from cash_movements
        $systemMovements = $db->table('cash_movements')
            ->where('company_id', $companyId)
            ->where('reconciled', 0)
            ->get()->getResultArray();

        $matched = [];
        $unmatchedStatement = [];
        $unmatchedSystem = $systemMovements;
        $totalMatchedAmount = 0.0;

        foreach ($statementLines as $line) {
            $stmtDate = date('Y-m-d', strtotime($line['date'] ?? date('Y-m-d')));
            $stmtAmount = (float) ($line['amount'] ?? 0);
            $stmtRef = trim((string) ($line['reference'] ?? ''));

            $bestMatchIndex = null;
            $matchScore = 0;

            foreach ($unmatchedSystem as $idx => $sys) {
                if (!isset($unmatchedSystem[$idx])) continue;

                $sysAmount = (float) ($sys['amount'] ?? 0);
                if (abs($sysAmount - $stmtAmount) > 0.01) {
                    continue; // Exact amount match required
                }

                $score = 50; // Amount matches exactly

                // Date proximity match (+/- 3 days)
                $sysDate = date('Y-m-d', strtotime($sys['created_at']));
                $diffDays = abs((strtotime($stmtDate) - strtotime($sysDate)) / 86400);
                if ($diffDays <= 1) {
                    $score += 30;
                } elseif ($diffDays <= 3) {
                    $score += 15;
                }

                // Reference code match
                $sysRef = trim((string) ($sys['reference_id'] ?? $sys['description'] ?? ''));
                if ($stmtRef !== '' && $sysRef !== '' && stripos($sysRef, $stmtRef) !== false) {
                    $score += 20;
                }

                if ($score > $matchScore && $score >= 80) {
                    $matchScore = $score;
                    $bestMatchIndex = $idx;
                }
            }

            if ($bestMatchIndex !== null) {
                $matchedSys = $unmatchedSystem[$bestMatchIndex];

                // Mark system movement as reconciled
                $db->table('cash_movements')
                    ->where('id', $matchedSys['id'])
                    ->update([
                        'reconciled'    => 1,
                        'reconciled_at' => date('Y-m-d H:i:s'),
                    ]);

                $matched[] = [
                    'statement_line' => $line,
                    'system_movement' => $matchedSys,
                    'match_score'     => $matchScore,
                ];

                $totalMatchedAmount += abs($stmtAmount);
                unset($unmatchedSystem[$bestMatchIndex]);
            } else {
                $unmatchedStatement[] = $line;
            }
        }

        // Re-index remaining unmatched system movements
        $unmatchedSystem = array_values($unmatchedSystem);

        return [
            'reconciliation_date'   => date('Y-m-d H:i:s'),
            'total_statement_items' => count($statementLines),
            'matched_count'         => count($matched),
            'unmatched_statement'   => count($unmatchedStatement),
            'unmatched_system'      => count($unmatchedSystem),
            'total_matched_amount'  => $totalMatchedAmount,
            'matched_details'       => $matched,
            'unmatched_statement_lines' => $unmatchedStatement,
            'unmatched_system_movements' => $unmatchedSystem,
        ];
    }
}
