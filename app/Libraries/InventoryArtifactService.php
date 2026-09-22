<?php

namespace App\Libraries;

use App\Models\InventoryMovementModel;
use App\Models\InventoryLotModel;
use App\Models\InventorySerialModel;
use App\Models\InventoryCostLayerModel;
use App\Models\InventoryProductModel;
use App\Models\InventorySettingModel;

class InventoryArtifactService
{
    public function sync(string $companyId, string $productId, string $movementId, array $payload): void
    {
        $movementType = (string) ($payload['movement_type'] ?? '');
        $adjustmentMode = (string) ($payload['adjustment_mode'] ?? '');
        $quantity = (float) ($payload['quantity'] ?? 0);
        $unitCost = (float) ($payload['unit_cost'] ?? 0);
        $totalCost = (float) ($payload['total_cost'] ?? ($unitCost * $quantity));
        $occurredAt = (string) ($payload['occurred_at'] ?? date('Y-m-d H:i:s'));
        $lotNumber = trim((string) ($payload['lot_number'] ?? ''));
        $serialNumber = trim((string) ($payload['serial_number'] ?? ''));
        $expirationDate = trim((string) ($payload['expiration_date'] ?? '')) ?: null;

        $direction = 0;
        $warehouseId = null;
        $locationId = null;

        if ($movementType === 'ingreso') {
            $direction = 1;
            $warehouseId = $payload['destination_warehouse_id'] ?? null;
            $locationId = $payload['destination_location_id'] ?? null;
        } elseif ($movementType === 'egreso') {
            $direction = -1;
            $warehouseId = $payload['source_warehouse_id'] ?? null;
            $locationId = $payload['source_location_id'] ?? null;
        } elseif ($movementType === 'transferencia') {
            $sourceWarehouseId = (string) ($payload['source_warehouse_id'] ?? '');
            $sourceLocationId = $payload['source_location_id'] ?? null;
            $destinationWarehouseId = (string) ($payload['destination_warehouse_id'] ?? '');
            $destinationLocationId = $payload['destination_location_id'] ?? null;

            $consumption = $this->consumeCostLayers(
                $companyId,
                $productId,
                $sourceWarehouseId,
                $sourceLocationId,
                $quantity,
                $occurredAt,
                $movementId,
                'transfer_out'
            );

            $unitCost = $consumption['unit_cost'];
            $totalCost = $consumption['total_cost'];

            (new InventoryMovementModel())->update($movementId, [
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
            ]);

            $this->syncLotBalance($companyId, $productId, $sourceWarehouseId, $sourceLocationId, $lotNumber, $expirationDate, $quantity * -1);
            $this->syncLotBalance($companyId, $productId, $destinationWarehouseId, $destinationLocationId, $lotNumber, $expirationDate, $quantity);
            $this->syncSerialRecord($companyId, $productId, $serialNumber, $destinationWarehouseId, $destinationLocationId, $lotNumber, $expirationDate, 'available', $movementId);
            if ($unitCost > 0) {
                $this->createCostLayer($companyId, $productId, $destinationWarehouseId, $destinationLocationId, $movementId, 'transfer_in', $quantity, $unitCost, $totalCost, $occurredAt);
            }
            return;
        } elseif ($movementType === 'ajuste') {
            $direction = $adjustmentMode === 'increase' ? 1 : -1;
            $warehouseId = $payload['source_warehouse_id'] ?? null;
            $locationId = $payload['source_location_id'] ?? null;
        }

        if ($lotNumber !== '' && $warehouseId) {
            $this->syncLotBalance($companyId, $productId, (string) $warehouseId, $locationId, $lotNumber, $expirationDate, $quantity * $direction);
        }

        if ($serialNumber !== '') {
            $this->syncSerialRecord(
                $companyId,
                $productId,
                $serialNumber,
                $warehouseId ? (string) $warehouseId : null,
                $locationId,
                $lotNumber !== '' ? $lotNumber : null,
                $expirationDate,
                $direction >= 0 ? 'available' : 'consumed',
                $movementId
            );
        }

        if ($direction > 0 && $unitCost > 0 && $warehouseId) {
            $this->createCostLayer($companyId, $productId, (string) $warehouseId, $locationId, $movementId, 'entry', $quantity, $unitCost, $totalCost, $occurredAt);
            return;
        }

        if ($direction < 0 && $warehouseId) {
            $layerType = $movementType === 'egreso'
                ? 'consumption'
                : ($movementType === 'ajuste' ? 'adjustment_out' : 'consumption');

            $consumption = $this->consumeCostLayers(
                $companyId,
                $productId,
                (string) $warehouseId,
                $locationId,
                $quantity,
                $occurredAt,
                $movementId,
                $layerType
            );

            (new InventoryMovementModel())->update($movementId, [
                'unit_cost' => $consumption['unit_cost'],
                'total_cost' => $consumption['total_cost'],
            ]);
        }
    }

    private function syncLotBalance(string $companyId, string $productId, string $warehouseId, ?string $locationId, string $lotNumber, ?string $expirationDate, float $delta): void
    {
        if ($lotNumber === '' || $warehouseId === '') {
            return;
        }

        $model = new InventoryLotModel();
        $lot = $model
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('lot_number', $lotNumber)
            ->first();

        if ($lot) {
            $balance = ((float) $lot['quantity_balance']) + $delta;
            if ($balance < 0) { throw new \RuntimeException('Saldo de lote insuficiente.'); }
            $model->update($lot['id'], [
                'expiration_date' => $expirationDate ?: $lot['expiration_date'],
                'quantity_balance' => $balance,
                'status' => $balance > 0 ? 'active' : 'closed',
            ]);
            return;
        }

        if ($delta <= 0) {
            throw new \RuntimeException('El lote no existe en el origen indicado.');
        }

        $model->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'lot_number' => $lotNumber,
            'expiration_date' => $expirationDate,
            'quantity_balance' => $delta,
            'status' => 'active',
        ]);
    }

    private function syncSerialRecord(string $companyId, string $productId, string $serialNumber, ?string $warehouseId, ?string $locationId, ?string $lotNumber, ?string $expirationDate, string $status, string $movementId): void
    {
        if ($serialNumber === '') {
            return;
        }

        $model = new InventorySerialModel();
        $serial = $model
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('serial_number', $serialNumber)
            ->first();

        $payload = [
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'lot_number' => $lotNumber,
            'expiration_date' => $expirationDate,
            'status' => $status,
            'last_movement_id' => $movementId,
        ];

        if ($serial) {
            $model->update($serial['id'], $payload);
            return;
        }

        $model->insert(array_merge($payload, [
            'company_id' => $companyId,
            'product_id' => $productId,
            'serial_number' => $serialNumber,
        ]));
    }

    public function createCostLayer(string $companyId, string $productId, string $warehouseId, ?string $locationId, string $movementId, string $layerType, float $quantity, float $unitCost, float $totalCost, string $occurredAt): void
    {
        (new InventoryCostLayerModel())->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'movement_id' => $movementId,
            'layer_type' => $layerType,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function valuationMethod(string $companyId): string
    {
        $method = strtolower((string) (((new InventorySettingModel())->where('company_id', $companyId)->first() ?? [])['valuation_method'] ?? 'weighted_average'));

        return in_array($method, ['fifo', 'lifo', 'weighted_average'], true) ? $method : 'weighted_average';
    }

    private function fallbackUnitCost(string $companyId, string $productId, string $warehouseId, ?string $locationId): float
    {
        $snapshot = $this->openLayerSnapshot($companyId, $productId, $warehouseId, $locationId);

        if ($snapshot['quantity'] > 0 && $snapshot['value'] > 0) {
            return $snapshot['value'] / $snapshot['quantity'];
        }

        $product = (new InventoryProductModel())->find($productId);

        return (float) ($product['cost_price'] ?? 0);
    }

    private function openLayerSnapshot(string $companyId, string $productId, string $warehouseId, ?string $locationId): array
    {
        $builder = db_connect()->table('inventory_cost_layers')
            ->select('COALESCE(SUM(remaining_quantity), 0) AS quantity, COALESCE(SUM(total_cost), 0) AS value', false)
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_quantity >', 0);

        if ($locationId !== null) {
            $builder->where('location_id', $locationId);
        }

        $row = $builder->get()->getRowArray() ?? [];

        return [
            'quantity' => (float) ($row['quantity'] ?? 0),
            'value' => (float) ($row['value'] ?? 0),
        ];
    }

    public function consumeCostLayers(string $companyId, string $productId, string $warehouseId, ?string $locationId, float $quantity, string $occurredAt, string $movementId, string $layerType): array
    {
        $model = new InventoryCostLayerModel();
        $method = $this->valuationMethod($companyId);
        $builder = $model
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_quantity >', 0);

        if ($locationId !== null) {
            $builder->where('location_id', $locationId);
        }

        if ($method === 'lifo') {
            $builder->orderBy('occurred_at', 'DESC')->orderBy('created_at', 'DESC');
        } else {
            $builder->orderBy('occurred_at', 'ASC')->orderBy('created_at', 'ASC');
        }

        $layers = $builder->findAll();
        $remaining = $quantity;
        $totalCost = 0.0;

        if ($method === 'weighted_average' && $layers !== []) {
            $totalQty = 0.0;
            foreach ($layers as $layer) {
                $totalQty += (float) ($layer['remaining_quantity'] ?? 0);
            }

            if ($totalQty > 0) {
                $consumed = 0.0;
                $layerCount = count($layers);

                foreach ($layers as $index => $layer) {
                    $layerRemaining = (float) ($layer['remaining_quantity'] ?? 0);
                    if ($layerRemaining <= 0) {
                        continue;
                    }

                    if ($index === $layerCount - 1) {
                        $consumeQty = max(0, min($remaining, $layerRemaining));
                    } else {
                        $ratioQty = round($quantity * ($layerRemaining / $totalQty), 6);
                        $consumeQty = max(0, min($ratioQty, $layerRemaining, $remaining));
                    }

                    if ($consumeQty <= 0) {
                        continue;
                    }

                    $remaining -= $consumeQty;
                    $consumed += $consumeQty;
                    $totalCost += $consumeQty * (float) ($layer['unit_cost'] ?? 0);
                    $newRemaining = max(0, $layerRemaining - $consumeQty);

                    $model->update($layer['id'], [
                        'remaining_quantity' => $newRemaining,
                        'total_cost' => $newRemaining * (float) ($layer['unit_cost'] ?? 0),
                    ]);
                }
            }
        } else {
            foreach ($layers as $layer) {
                if ($remaining <= 0) {
                    break;
                }

                $layerRemaining = (float) ($layer['remaining_quantity'] ?? 0);
                if ($layerRemaining <= 0) {
                    continue;
                }

                $consumeQty = min($remaining, $layerRemaining);
                $remaining -= $consumeQty;
                $totalCost += $consumeQty * (float) ($layer['unit_cost'] ?? 0);
                $newRemaining = max(0, $layerRemaining - $consumeQty);

                $model->update($layer['id'], [
                    'remaining_quantity' => $newRemaining,
                    'total_cost' => $newRemaining * (float) ($layer['unit_cost'] ?? 0),
                ]);
            }
        }

        $coveredQuantity = max(0.0, $quantity - $remaining);
        $fallbackUnitCost = $this->fallbackUnitCost($companyId, $productId, $warehouseId, $locationId);

        if ($remaining > 0) {
            $totalCost += $remaining * $fallbackUnitCost;
        }

        $unitCost = $quantity > 0 ? $totalCost / $quantity : 0.0;

        $this->createCostLayer(
            $companyId,
            $productId,
            $warehouseId,
            $locationId,
            $movementId,
            $layerType,
            $quantity,
            $unitCost,
            $totalCost,
            $occurredAt
        );

        (new InventoryCostLayerModel())->where('movement_id', $movementId)->where('layer_type', $layerType)->set(['remaining_quantity' => 0])->update();

        return [
            'covered_quantity' => $coveredQuantity,
            'fallback_quantity' => $remaining,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
        ];
    }

}
