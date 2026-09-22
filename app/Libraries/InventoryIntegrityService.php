<?php

namespace App\Libraries;

use App\Models\InventoryProductModel;
use App\Models\InventoryStockLevelModel;
use RuntimeException;

class InventoryIntegrityService
{
    public function transaction(string $companyId, callable $operation)
    {
        $db = db_connect();
        $depth = $db->transDepth;
        if (! $db->transBegin()) {
            throw new RuntimeException('No se pudo iniciar la transaccion de inventario.');
        }
        try {
            $this->lock('companies', 'id = ?', [$companyId]);
            $result = $operation();
            if (! $db->transStatus() || $db->transDepth !== $depth + 1) {
                throw new RuntimeException('No se pudo completar la operacion de inventario.');
            }
            if (! $db->transCommit()) {
                throw new RuntimeException('No se pudo confirmar la operacion de inventario.');
            }
            return $result;
        } catch (\Throwable $e) {
            while ($db->transDepth > $depth && $db->transRollback()) {
            }
            throw $e;
        }
    }

    private function lock(string $table, string $where, array $params): array
    {
        $db = db_connect();
        $sql = 'SELECT * FROM ' . $db->protectIdentifiers($db->prefixTable($table)) . ' WHERE ' . $where;
        if ($db->DBDriver !== 'SQLite3') {
            $sql .= ' FOR UPDATE';
        }
        return $db->query($sql, $params)->getResultArray();
    }

    public function lockProduct(string $companyId, string $productId): array
    {
        $rows = $this->lock('inventory_products', 'company_id = ? AND id = ?', [$companyId, $productId]);
        if ($rows === []) {
            throw new RuntimeException('Producto no disponible para esta empresa.');
        }
        return $rows[0];
    }

    public function validatePlace(string $companyId, ?string $warehouseId, ?string $locationId): void
    {
        $db = db_connect();
        if ($warehouseId && ! $db->table('inventory_warehouses')->where('company_id', $companyId)->where('id', $warehouseId)->where('active', 1)->countAllResults()) {
            throw new RuntimeException('Deposito no disponible para esta empresa.');
        }
        if ($locationId && (! $warehouseId || ! $db->table('inventory_locations')->where('company_id', $companyId)->where('warehouse_id', $warehouseId)->where('id', $locationId)->where('active', 1)->countAllResults())) {
            throw new RuntimeException('La ubicacion no pertenece al deposito seleccionado.');
        }
    }

    public function balances(string $companyId, string $productId, string $warehouseId): array
    {
        $this->lockProduct($companyId, $productId);
        return $this->lock('inventory_stock_levels', 'company_id = ? AND product_id = ? AND warehouse_id = ? ORDER BY id', [$companyId, $productId, $warehouseId]);
    }

    public function available(string $companyId, string $productId, string $warehouseId, ?string $locationId = null): float
    {
        $rows = $this->balances($companyId, $productId, $warehouseId);
        $reserved = array_sum(array_column($rows, 'reserved_quantity'));
        $total = array_sum(array_column($rows, 'quantity'));
        if ($locationId === null) {
            return $total - $reserved;
        }
        $local = array_sum(array_column(array_filter($rows, static fn(array $r): bool => ($r['location_id'] ?? null) === $locationId), 'quantity'));
        return min($local, $total - $reserved);
    }

    public function quantity(string $companyId, string $productId, string $warehouseId): float
    {
        return (float) array_sum(array_column($this->balances($companyId, $productId, $warehouseId), 'quantity'));
    }

    public function changeStock(string $companyId, string $productId, string $warehouseId, float $delta, ?string $locationId = null): void
    {
        if (! is_finite($delta)) {
            throw new RuntimeException('Cantidad invalida.');
        }
        $rows = $this->balances($companyId, $productId, $warehouseId);
        $model = new InventoryStockLevelModel();
        // Operations without a location consume actual balances in a stable order.
        if ($locationId === null && $delta < 0) {
            $remaining = -$delta;
            foreach ($rows as $row) {
                $take = min($remaining, max(0, (float) $row['quantity']));
                if ($take > 0) {
                    $model->update($row['id'], ['quantity' => (float) $row['quantity'] - $take]);
                    $remaining -= $take;
                }
            }
            if ($remaining <= 0) {
                return;
            }
            $delta = -$remaining;
            $rows = $this->balances($companyId, $productId, $warehouseId);
        }
        foreach ($rows as $row) {
            if (($row['location_id'] ?? null) === $locationId) {
                $model->update($row['id'], ['quantity' => (float) $row['quantity'] + $delta]);
                return;
            }
        }
        $product = (new InventoryProductModel())->find($productId);
        $model->insert(['company_id' => $companyId, 'product_id' => $productId, 'warehouse_id' => $warehouseId,
            'location_id' => $locationId, 'quantity' => $delta, 'reserved_quantity' => 0, 'min_stock' => $product['min_stock'] ?? 0]);
    }

    public function withdrawUnlocated(string $companyId, string $productId, string $warehouseId, float $quantity): void
    {
        $rows = $this->balances($companyId, $productId, $warehouseId);
        if (! is_finite($quantity) || $quantity <= 0 || $this->available($companyId, $productId, $warehouseId) < $quantity) {
            throw new RuntimeException('Stock disponible insuficiente.');
        }
        foreach ($rows as $row) {
            if (($row['location_id'] ?? null) === null && (float) $row['quantity'] >= $quantity) {
                (new InventoryStockLevelModel())->update($row['id'], ['quantity' => (float) $row['quantity'] - $quantity]);
                return;
            }
        }
        throw new RuntimeException('El lote o serie debe estar en el stock sin ubicacion de la recepcion para devolverlo.');
    }

    public function changeReserved(string $companyId, string $productId, string $warehouseId, float $delta): void
    {
        $rows = $this->balances($companyId, $productId, $warehouseId);
        $current = (float) array_sum(array_column($rows, 'reserved_quantity'));
        $quantity = (float) array_sum(array_column($rows, 'quantity'));
        if (! is_finite($delta) || $current + $delta < -0.000001 || ($delta > 0 && $current + $delta > $quantity + 0.000001)) {
            throw new RuntimeException('Saldo reservado o disponibilidad insuficiente.');
        }
        if ($rows === []) {
            throw new RuntimeException('No hay stock para reservar.');
        }
        $model = new InventoryStockLevelModel();
        // Reservations belong to the warehouse; store the aggregate once.
        foreach ($rows as $index => $row) {
            $model->update($row['id'], ['reserved_quantity' => $index === 0 ? max(0, $current + $delta) : 0]);
        }
    }

    public function validateTrace(string $companyId, string $productId, array $payload): void
    {
        $product = $this->lockProduct($companyId, $productId);
        $lot = trim((string) ($payload['lot_number'] ?? ''));
        $serial = trim((string) ($payload['serial_number'] ?? ''));
        $quantity = (float) ($payload['quantity'] ?? 0);
        if (! is_finite($quantity) || $quantity <= 0) {
            throw new RuntimeException('La cantidad debe ser mayor a cero.');
        }
        if ((int) ($product['lot_control'] ?? 0) === 1 && $lot === '') {
            throw new RuntimeException('El producto requiere numero de lote.');
        }
        if ((int) ($product['serial_control'] ?? 0) === 1 && $serial === '') {
            throw new RuntimeException('El producto requiere numero de serie.');
        }
        $outgoing = in_array($payload['movement_type'] ?? '', ['egreso', 'transferencia'], true)
            || (($payload['movement_type'] ?? '') === 'ajuste' && ($payload['adjustment_mode'] ?? '') === 'decrease');
        if ($lot !== '' && $outgoing) {
            $row = db_connect()->table('inventory_lots')->where('company_id', $companyId)->where('product_id', $productId)
                ->where('warehouse_id', $payload['source_warehouse_id'] ?? null)->where('location_id', $payload['source_location_id'] ?? null)
                ->where('lot_number', $lot)->get()->getRowArray();
            if (! $row || (float) $row['quantity_balance'] < $quantity) {
                throw new RuntimeException('El lote no tiene saldo suficiente en el origen indicado.');
            }
        }
        if ($serial !== '') {
            if ($quantity !== 1.0) {
                throw new RuntimeException('Cada numero de serie debe corresponder a una unidad.');
            }
            $row = db_connect()->table('inventory_serials')->where('company_id', $companyId)->where('product_id', $productId)
                ->where('serial_number', $serial)->get()->getRowArray();
            if ($outgoing && (! $row || $row['status'] !== 'available'
                || $row['warehouse_id'] !== ($payload['source_warehouse_id'] ?? null)
                || ($row['location_id'] ?? null) !== ($payload['source_location_id'] ?? null)
                || ($lot !== '' && ($row['lot_number'] ?? '') !== $lot))) {
                throw new RuntimeException('La serie no esta disponible en el origen indicado.');
            }
            if (! $outgoing && $row && $row['status'] === 'available') {
                throw new RuntimeException('La serie ya tiene una unidad disponible.');
            }
        }
    }
}
