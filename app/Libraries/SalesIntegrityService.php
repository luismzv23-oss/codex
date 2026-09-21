<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class SalesIntegrityService
{
    public function locked(BaseConnection $db, string $table, string $companyId, string $id, array $states, callable $operation)
    {
        if (! in_array($table, ['sales', 'sales_delivery_notes'], true)) {
            throw new RuntimeException('Documento no soportado.');
        }
        $initialDepth = $db->transDepth;
        if (! $db->transBegin()) {
            throw new RuntimeException('No se pudo iniciar la transaccion.');
        }
        try {
            $sql = 'SELECT * FROM ' . $db->protectIdentifiers($db->prefixTable($table)) . ' WHERE company_id = ? AND id = ?';
            if ($db->DBDriver !== 'SQLite3') {
                $sql .= ' FOR UPDATE';
            }
            $row = $db->query($sql, [$companyId, $id])->getRowArray();
            if (! $row || ! in_array($row['status'], $states, true)) {
                throw new RuntimeException('Documento no disponible o estado incompatible con la operacion.');
            }
            $result = $operation($row);
            if (! $db->transStatus()) {
                throw new RuntimeException('No se pudo completar la operacion.');
            }
            if ($db->transDepth !== $initialDepth + 1) {
                throw new RuntimeException('La operacion dejo una transaccion incompleta.');
            }
            if (! $db->transCommit()) {
                throw new RuntimeException('No se pudo confirmar la transaccion.');
            }
            return $result;
        } catch (\Throwable $e) {
            while ($db->transDepth > $initialDepth) {
                if (! $db->transRollback()) {
                    break;
                }
            }
            throw $e;
        }
    }

    public function remainingItems(array $items): array
    {
        foreach ($items as &$item) {
            $item['quantity'] = max(0, (float) $item['quantity'] - (float) ($item['returned_quantity'] ?? 0));
        }
        unset($item);
        return array_values(array_filter($items, static fn(array $item): bool => $item['quantity'] > 0));
    }

    public function netReceivableTotal(array $sale, array $items): float
    {
        $gross = array_sum(array_column($items, 'line_total'));
        $returned = 0.0;
        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            if ($quantity > 0) {
                $returned += (float) $item['line_total'] * min(1, max(0, (float) ($item['returned_quantity'] ?? 0) / $quantity));
            }
        }
        return $gross > 0 ? max(0, round((float) $sale['total'] * (1 - $returned / $gross), 2)) : (float) $sale['total'];
    }

    public function returnUnitPrice(array $sale, array $items, array $item): float
    {
        $gross = array_sum(array_column($items, 'line_total'));
        $quantity = (float) $item['quantity'];
        return $gross > 0 && $quantity > 0
            ? (float) $item['line_total'] / $quantity * (float) $sale['total'] / $gross
            : 0.0;
    }
}
