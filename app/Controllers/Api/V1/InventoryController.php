<?php

namespace App\Controllers\Api\V1;

use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\InventoryAssemblyItemModel;
use App\Models\InventoryAssemblyModel;
use App\Models\InventoryCostLayerModel;
use App\Models\InventoryKitItemModel;
use App\Models\InventoryLocationModel;
use App\Models\InventoryLotModel;
use App\Models\InventoryMovementModel;
use App\Models\InventoryPeriodClosureModel;
use App\Models\InventoryProductModel;
use App\Models\InventoryReservationModel;
use App\Models\InventoryRevaluationModel;
use App\Models\InventorySerialModel;
use App\Models\InventorySettingModel;
use App\Models\InventoryStockLevelModel;
use App\Models\InventoryWarehouseModel;
use App\Models\SystemModel;
use App\Models\UserSystemModel;

class InventoryController extends BaseApiController
{
    public function index()
    {
        $context = $this->inventoryContext('view');

        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success([
            'company' => $context['company'],
            'access_level' => $context['access_level'],
            'summary' => $this->summaryMetrics($context['company']['id']),
            'settings' => $this->inventorySettings($context['company']['id']),
            'alerts' => $this->alerts($context['company']['id']),
            'warehouses' => $this->warehouseList($context['company']['id']),
            'locations' => $this->locationList($context['company']['id']),
            'products' => $this->productRows($context['company']['id']),
            'active_reservations' => $this->activeReservations($context['company']['id'], null, 20),
            'recent_movements' => $this->movementRows($context['company']['id'], null, 10),
            'assemblies' => $this->assemblyRows($context['company']['id']),
            'period_closures' => $this->periodClosureRows($context['company']['id']),
            'revaluations' => $this->revaluationRows($context['company']['id']),
        ]);
    }

    public function settings()
    {
        $context = $this->inventoryContext('view');

        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->inventorySettings($context['company']['id']));
    }

    public function updateSettings()
    {
        $context = $this->inventoryContext('configure');

        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $settings = $this->inventorySettings($context['company']['id']);
        (new InventorySettingModel())->update($settings['id'], [
            'alert_email' => trim((string) ($payload['alert_email'] ?? '')),
            'unusual_movement_threshold' => (float) ($payload['unusual_movement_threshold'] ?? $settings['unusual_movement_threshold']),
            'no_rotation_days' => max(1, (int) ($payload['no_rotation_days'] ?? $settings['no_rotation_days'])),
            'allow_negative_stock' => ! empty($payload['allow_negative_stock']) ? 1 : 0,
            'low_stock_alerts' => array_key_exists('low_stock_alerts', $payload) ? (int) $payload['low_stock_alerts'] : $settings['low_stock_alerts'],
            'internal_notifications' => array_key_exists('internal_notifications', $payload) ? (int) $payload['internal_notifications'] : $settings['internal_notifications'],
            'email_notifications' => ! empty($payload['email_notifications']) ? 1 : 0,
            'valuation_method' => trim((string) ($payload['valuation_method'] ?? $settings['valuation_method'] ?? 'weighted_average')) ?: 'weighted_average',
            'negative_stock_scope' => trim((string) ($payload['negative_stock_scope'] ?? ($settings['negative_stock_scope'] ?? 'global'))) ?: 'global',
            'allow_negative_on_sales' => array_key_exists('allow_negative_on_sales', $payload) ? (int) $payload['allow_negative_on_sales'] : ($settings['allow_negative_on_sales'] ?? 0),
            'allow_negative_on_transfers' => array_key_exists('allow_negative_on_transfers', $payload) ? (int) $payload['allow_negative_on_transfers'] : ($settings['allow_negative_on_transfers'] ?? 0),
            'allow_negative_on_adjustments' => array_key_exists('allow_negative_on_adjustments', $payload) ? (int) $payload['allow_negative_on_adjustments'] : ($settings['allow_negative_on_adjustments'] ?? 0),
        ]);

        return $this->success($this->inventorySettings($context['company']['id']));
    }

    public function warehouses()
    {
        $context = $this->inventoryContext('view');

        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->warehouseList($context['company']['id']));
    }

    public function locations()
    {
        $context = $this->inventoryContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->locationList($context['company']['id']));
    }

    public function storeLocation()
    {
        $context = $this->inventoryContext('configure');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $warehouseId = trim((string) ($payload['warehouse_id'] ?? ''));
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));

        if (! $this->ownedWarehouse($context['company']['id'], $warehouseId) || $code === '' || $name === '') {
            return $this->fail('Debes indicar deposito, codigo y nombre de la ubicacion.', 422);
        }

        $model = new InventoryLocationModel();
        if ($model->where('warehouse_id', $warehouseId)->where('code', $code)->first()) {
            return $this->fail('Ya existe una ubicacion con ese codigo en el deposito.', 422);
        }

        $id = $model->insert([
            'company_id' => $context['company']['id'],
            'warehouse_id' => $warehouseId,
            'name' => $name,
            'code' => $code,
            'zone' => trim((string) ($payload['zone'] ?? '')),
            'rack' => trim((string) ($payload['rack'] ?? '')),
            'level' => trim((string) ($payload['level'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success($model->find($id), 201);
    }

    public function updateLocation(string $id)
    {
        $context = $this->inventoryContext('configure');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $location = $this->ownedLocation($context['company']['id'], $id);
        if (! $location) {
            return $this->fail('Ubicacion no disponible.', 404);
        }

        $payload = $this->payload();
        $warehouseId = trim((string) ($payload['warehouse_id'] ?? $location['warehouse_id']));
        $code = strtoupper(trim((string) ($payload['code'] ?? $location['code'])));
        $name = trim((string) ($payload['name'] ?? $location['name']));

        if (! $this->ownedWarehouse($context['company']['id'], $warehouseId) || $code === '' || $name === '') {
            return $this->fail('Debes indicar deposito, codigo y nombre de la ubicacion.', 422);
        }

        $model = new InventoryLocationModel();
        $duplicate = $model->where('warehouse_id', $warehouseId)->where('code', $code)->where('id !=', $id)->first();
        if ($duplicate) {
            return $this->fail('Ya existe una ubicacion con ese codigo en el deposito.', 422);
        }

        $model->update($id, [
            'warehouse_id' => $warehouseId,
            'name' => $name,
            'code' => $code,
            'zone' => trim((string) ($payload['zone'] ?? $location['zone'] ?? '')),
            'rack' => trim((string) ($payload['rack'] ?? $location['rack'] ?? '')),
            'level' => trim((string) ($payload['level'] ?? $location['level'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? $location['description'] ?? '')),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : $location['active'],
        ]);

        return $this->success($model->find($id));
    }

    public function toggleLocation(string $id)
    {
        $context = $this->inventoryContext('configure');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $location = $this->ownedLocation($context['company']['id'], $id);
        if (! $location) {
            return $this->fail('Ubicacion no disponible.', 404);
        }

        $model = new InventoryLocationModel();
        $model->update($id, ['active' => (int) $location['active'] === 1 ? 0 : 1]);

        return $this->success($model->find($id));
    }

    public function deleteLocation(string $id)
    {
        $context = $this->inventoryContext('configure');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $location = $this->ownedLocation($context['company']['id'], $id);
        if (! $location) {
            return $this->fail('Ubicacion no disponible.', 404);
        }

        $hasStock = (new InventoryStockLevelModel())->where('location_id', $id)->where('quantity !=', 0)->first() !== null;
        $hasMovements = (new InventoryMovementModel())->groupStart()->where('source_location_id', $id)->orWhere('destination_location_id', $id)->groupEnd()->first() !== null;
        if ($hasStock || $hasMovements) {
            return $this->fail('No puedes eliminar una ubicacion con stock o trazabilidad registrada.', 422);
        }

        (new InventoryLocationModel())->delete($id);
        return $this->success(['id' => $id, 'deleted' => true]);
    }

    public function storeWarehouse()
    {
        $context = $this->inventoryContext('configure');

        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));

        if ($code === '' || $name === '') {
            return $this->fail('Debes indicar nombre y codigo del deposito.', 422);
        }

        $model = new InventoryWarehouseModel();
        if ($model->where('company_id', $context['company']['id'])->where('code', $code)->first()) {
            return $this->fail('Ya existe un deposito con ese codigo en la empresa.', 422);
        }

        if (! empty($payload['is_default'])) {
            $model->where('company_id', $context['company']['id'])->set(['is_default' => 0])->update();
        }

        $id = $model->insert([
            'company_id' => $context['company']['id'],
            'branch_id' => trim((string) ($payload['branch_id'] ?? '')) ?: null,
            'name' => $name,
            'code' => $code,
            'type' => trim((string) ($payload['type'] ?? 'general')) ?: 'general',
            'description' => trim((string) ($payload['description'] ?? '')),
            'is_default' => ! empty($payload['is_default']) ? 1 : 0,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success($model->find($id), 201);
    }

    public function updateWarehouse(string $id)
    {
        $context = $this->inventoryContext('configure');

        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $model = new InventoryWarehouseModel();
        $warehouse = $this->ownedWarehouse($context['company']['id'], $id);

        if (! $warehouse) {
            return $this->fail('Deposito no disponible.', 404);
        }

        $payload = $this->payload();
        $code = strtoupper(trim((string) ($payload['code'] ?? $warehouse['code'])));
        $name = trim((string) ($payload['name'] ?? $warehouse['name']));

        if ($code === '' || $name === '') {
            return $this->fail('Debes indicar nombre y codigo del deposito.', 422);
        }

        $duplicate = $model->where('company_id', $context['company']['id'])->where('code', $code)->where('id !=', $id)->first();
        if ($duplicate) {
            return $this->fail('Ya existe un deposito con ese codigo en la empresa.', 422);
        }

        $isDefault = array_key_exists('is_default', $payload) ? (int) $payload['is_default'] : (int) $warehouse['is_default'];
        if ($isDefault === 1) {
            $model->where('company_id', $context['company']['id'])->set(['is_default' => 0])->update();
        }

        $model->update($id, [
            'branch_id' => array_key_exists('branch_id', $payload) ? (trim((string) $payload['branch_id']) ?: null) : $warehouse['branch_id'],
            'name' => $name,
            'code' => $code,
            'type' => trim((string) ($payload['type'] ?? $warehouse['type'])) ?: 'general',
            'description' => trim((string) ($payload['description'] ?? $warehouse['description'] ?? '')),
            'is_default' => $warehouse['is_default'] ? 1 : $isDefault,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : $warehouse['active'],
        ]);

        return $this->success($model->find($id));
    }

    public function toggleWarehouse(string $id)
    {
        $context = $this->inventoryContext('configure');

        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $model = new InventoryWarehouseModel();
        $warehouse = $this->ownedWarehouse($context['company']['id'], $id);

        if (! $warehouse) {
            return $this->fail('Deposito no disponible.', 404);
        }

        if ((int) $warehouse['is_default'] === 1 && (int) $warehouse['active'] === 1) {
            return $this->fail('El deposito base no puede deshabilitarse.', 422);
        }

        $model->update($id, ['active' => (int) $warehouse['active'] === 1 ? 0 : 1]);

        return $this->success($model->find($id));
    }

    public function deleteWarehouse(string $id)
    {
        $context = $this->inventoryContext('configure');

        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $model = new InventoryWarehouseModel();
        $warehouse = $this->ownedWarehouse($context['company']['id'], $id);

        if (! $warehouse) {
            return $this->fail('Deposito no disponible.', 404);
        }

        if ((int) $warehouse['is_default'] === 1) {
            return $this->fail('El deposito base no puede eliminarse.', 422);
        }

        $hasStock = (new InventoryStockLevelModel())->where('warehouse_id', $id)->where('quantity !=', 0)->first() !== null;
        $hasMovements = (new InventoryMovementModel())->groupStart()->where('source_warehouse_id', $id)->orWhere('destination_warehouse_id', $id)->groupEnd()->first() !== null;
        $hasReservations = (new InventoryReservationModel())->where('warehouse_id', $id)->where('status', 'active')->first() !== null;

        if ($hasStock || $hasMovements || $hasReservations) {
            return $this->fail('No puedes eliminar un deposito con stock o trazabilidad registrada.', 422);
        }

        $model->delete($id);
        return $this->success(['id' => $id, 'deleted' => true]);
    }

    public function products()
    {
        $context = $this->inventoryContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }
        return $this->success($this->productRows($context['company']['id']));
    }

    public function storeProduct()
    {
        $context = $this->inventoryContext('configure');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $sku = strtoupper(trim((string) ($payload['sku'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));

        if ($sku === '' || $name === '') {
            return $this->fail('Debes indicar SKU y nombre del producto.', 422);
        }

        $model = new InventoryProductModel();
        if ($model->where('company_id', $context['company']['id'])->where('sku', $sku)->first()) {
            return $this->fail('Ya existe un producto con ese SKU en la empresa.', 422);
        }

        $id = $model->insert([
            'company_id' => $context['company']['id'],
            'sku' => $sku,
            'name' => $name,
            'category' => trim((string) ($payload['category'] ?? '')),
            'brand' => trim((string) ($payload['brand'] ?? '')),
            'barcode' => trim((string) ($payload['barcode'] ?? '')),
            'product_type' => trim((string) ($payload['product_type'] ?? 'simple')) ?: 'simple',
            'description' => trim((string) ($payload['description'] ?? '')),
            'unit' => trim((string) ($payload['unit'] ?? 'unidad')) ?: 'unidad',
            'min_stock' => (float) ($payload['min_stock'] ?? 0),
            'max_stock' => (float) ($payload['max_stock'] ?? 0),
            'cost_price' => (float) ($payload['cost_price'] ?? 0),
            'sale_price' => (float) ($payload['sale_price'] ?? 0),
            'lot_control' => ! empty($payload['lot_control']) ? 1 : 0,
            'serial_control' => ! empty($payload['serial_control']) ? 1 : 0,
            'expiration_control' => ! empty($payload['expiration_control']) ? 1 : 0,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        $this->syncKitItems($id, $this->requestKitItems($context['company']['id'], $id, $payload));

        return $this->success($model->find($id), 201);
    }

    public function updateProduct(string $id)
    {
        $context = $this->inventoryContext('configure');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $model = new InventoryProductModel();
        $product = $this->ownedProduct($context['company']['id'], $id);
        if (! $product) {
            return $this->fail('Producto no disponible.', 404);
        }

        $payload = $this->payload();
        $sku = strtoupper(trim((string) ($payload['sku'] ?? $product['sku'])));
        $name = trim((string) ($payload['name'] ?? $product['name']));

        if ($sku === '' || $name === '') {
            return $this->fail('Debes indicar SKU y nombre del producto.', 422);
        }

        $duplicate = $model->where('company_id', $context['company']['id'])->where('sku', $sku)->where('id !=', $id)->first();
        if ($duplicate) {
            return $this->fail('Ya existe un producto con ese SKU en la empresa.', 422);
        }

        $min = (float) ($payload['min_stock'] ?? $product['min_stock']);
        $model->update($id, [
            'sku' => $sku,
            'name' => $name,
            'category' => trim((string) ($payload['category'] ?? $product['category'] ?? '')),
            'brand' => trim((string) ($payload['brand'] ?? $product['brand'] ?? '')),
            'barcode' => trim((string) ($payload['barcode'] ?? $product['barcode'] ?? '')),
            'product_type' => trim((string) ($payload['product_type'] ?? $product['product_type'] ?? 'simple')) ?: 'simple',
            'description' => trim((string) ($payload['description'] ?? $product['description'] ?? '')),
            'unit' => trim((string) ($payload['unit'] ?? $product['unit'] ?? 'unidad')) ?: 'unidad',
            'min_stock' => $min,
            'max_stock' => (float) ($payload['max_stock'] ?? $product['max_stock'] ?? 0),
            'cost_price' => (float) ($payload['cost_price'] ?? $product['cost_price'] ?? 0),
            'sale_price' => (float) ($payload['sale_price'] ?? $product['sale_price'] ?? 0),
            'lot_control' => array_key_exists('lot_control', $payload) ? (int) $payload['lot_control'] : $product['lot_control'],
            'serial_control' => array_key_exists('serial_control', $payload) ? (int) $payload['serial_control'] : $product['serial_control'],
            'expiration_control' => array_key_exists('expiration_control', $payload) ? (int) $payload['expiration_control'] : $product['expiration_control'],
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : $product['active'],
        ]);

        (new InventoryStockLevelModel())->where('company_id', $context['company']['id'])->where('product_id', $id)->set(['min_stock' => $min])->update();
        $this->syncKitItems($id, $this->requestKitItems($context['company']['id'], $id, $payload));

        return $this->success($model->find($id));
    }

    public function toggleProduct(string $id)
    {
        $context = $this->inventoryContext('configure');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $model = new InventoryProductModel();
        $product = $this->ownedProduct($context['company']['id'], $id);
        if (! $product) {
            return $this->fail('Producto no disponible.', 404);
        }

        $model->update($id, ['active' => (int) $product['active'] === 1 ? 0 : 1]);
        return $this->success($model->find($id));
    }

    public function deleteProduct(string $id)
    {
        $context = $this->inventoryContext('configure');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $model = new InventoryProductModel();
        $product = $this->ownedProduct($context['company']['id'], $id);
        if (! $product) {
            return $this->fail('Producto no disponible.', 404);
        }

        $hasStock = (new InventoryStockLevelModel())->where('product_id', $id)->where('quantity !=', 0)->first() !== null;
        $hasMovements = (new InventoryMovementModel())->where('product_id', $id)->first() !== null;
        $hasReservations = (new InventoryReservationModel())->where('product_id', $id)->where('status', 'active')->first() !== null;
        if ($hasStock || $hasMovements || $hasReservations) {
            return $this->fail('No puedes eliminar un producto con stock o trazabilidad registrada.', 422);
        }

        $model->delete($id);
        return $this->success(['id' => $id, 'deleted' => true]);
    }

    public function traceability(string $id)
    {
        $context = $this->inventoryContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $product = $this->ownedProduct($context['company']['id'], $id);
        if (! $product) {
            return $this->fail('Producto no disponible.', 404);
        }

        return $this->success([
            'product' => $product,
            'stock_by_warehouse' => $this->productTraceabilityStock($context['company']['id'], $id),
            'stock_by_location' => $this->productLocationStock($context['company']['id'], $id),
            'reservations' => $this->activeReservations($context['company']['id'], $id, 50),
            'movements' => $this->movementRows($context['company']['id'], $id, 100),
            'lots' => $this->lotRows($context['company']['id'], $id),
            'serials' => $this->serialRows($context['company']['id'], $id),
            'kit_items' => $this->kitItemRows($id),
            'cost_layers' => $this->costLayerRows($context['company']['id'], $id, 50),
        ]);
    }

    public function movements()
    {
        $context = $this->inventoryContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $productId = trim((string) ($this->request->getGet('product_id') ?? ''));
        return $this->success([
            'summary' => $this->productRows($context['company']['id']),
            'rows' => $this->movementRows($context['company']['id'], $productId !== '' ? $productId : null, 200),
        ]);
    }

    public function reservations()
    {
        $context = $this->inventoryContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $productId = trim((string) ($this->request->getGet('product_id') ?? ''));
        return $this->success($this->activeReservations($context['company']['id'], $productId !== '' ? $productId : null, 100));
    }

    public function storeReservation()
    {
        $context = $this->inventoryContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $companyId = $context['company']['id'];
        $productId = trim((string) ($payload['product_id'] ?? ''));
        $warehouseId = trim((string) ($payload['warehouse_id'] ?? ''));
        $quantity = (float) ($payload['quantity'] ?? 0);

        if (! $this->ownedProduct($companyId, $productId) || ! $this->ownedWarehouse($companyId, $warehouseId)) {
            return $this->fail('Debes seleccionar un producto y un deposito validos.', 422);
        }

        if ($quantity <= 0) {
            return $this->fail('La cantidad reservada debe ser mayor a cero.', 422);
        }

        if (! $this->canReserve($companyId, $productId, $warehouseId, $quantity)) {
            return $this->fail('No hay stock disponible suficiente para reservar esa cantidad.', 422);
        }

        $db = db_connect();
        $db->transStart();

        $this->applyReserved($companyId, $productId, $warehouseId, $quantity);

        $id = (new InventoryReservationModel())->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'reference' => trim((string) ($payload['reference'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'status' => 'active',
            'reserved_by' => $this->apiUser()['id'],
            'reserved_at' => date('Y-m-d H:i:s'),
        ], true);

        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->fail('No se pudo registrar la reserva.', 500);
        }

        return $this->success((new InventoryReservationModel())->find($id), 201);
    }

    public function releaseReservation(string $id)
    {
        $context = $this->inventoryContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $reservation = $this->ownedReservation($context['company']['id'], $id);
        if (! $reservation || ($reservation['status'] ?? '') !== 'active') {
            return $this->fail('La reserva no esta disponible.', 404);
        }

        $db = db_connect();
        $db->transStart();

        $this->applyReserved(
            $context['company']['id'],
            (string) $reservation['product_id'],
            (string) $reservation['warehouse_id'],
            ((float) $reservation['quantity']) * -1
        );

        (new InventoryReservationModel())->update($id, [
            'status' => 'released',
            'released_by' => $this->apiUser()['id'],
            'released_at' => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->fail('No se pudo liberar la reserva.', 500);
        }

        return $this->success((new InventoryReservationModel())->find($id));
    }

    public function storeAssembly()
    {
        $context = $this->inventoryContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $companyId = $context['company']['id'];
        $productId = trim((string) ($payload['product_id'] ?? ''));
        $warehouseId = trim((string) ($payload['warehouse_id'] ?? ''));
        $assemblyType = trim((string) ($payload['assembly_type'] ?? 'assembly'));
        $quantity = (float) ($payload['quantity'] ?? 0);

        if (! in_array($assemblyType, ['assembly', 'disassembly'], true) || ! $this->ownedProduct($companyId, $productId) || ! $this->ownedWarehouse($companyId, $warehouseId) || $quantity <= 0) {
            return $this->fail('Debes indicar producto, deposito, tipo y cantidad validos.', 422);
        }

        $components = $this->kitItemRows($productId);
        if ($components === []) {
            return $this->fail('El producto no tiene componentes configurados.', 422);
        }

        $settings = $this->inventorySettings($companyId);
        $allowNegative = $this->allowsNegativeFor('assembly', $settings);
        $issuedAt = trim((string) ($payload['issued_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $assemblyNumber = 'ENS-' . date('YmdHis');
        $db = db_connect();
        $db->transStart();

        $totalCost = 0.0;
        foreach ($components as $component) {
            $componentQty = (float) ($component['quantity'] ?? 0) * $quantity;
            if ($assemblyType === 'assembly') {
                if (! $this->canWithdraw($companyId, (string) $component['component_product_id'], $warehouseId, $componentQty, $allowNegative, null)) {
                    $db->transRollback();
                    return $this->fail('No hay stock suficiente para los componentes del ensamble.', 422);
                }
                $this->applyStock($companyId, (string) $component['component_product_id'], $warehouseId, $componentQty * -1, null);
                $movementId = (new InventoryMovementModel())->insert([
                    'company_id' => $companyId,
                    'product_id' => $component['component_product_id'],
                    'movement_type' => 'egreso',
                    'quantity' => $componentQty,
                    'source_warehouse_id' => $warehouseId,
                    'performed_by' => $this->apiUser()['id'],
                    'occurred_at' => $issuedAt,
                    'reason' => 'ensamble_componente',
                    'source_document' => $assemblyNumber,
                    'notes' => 'Consumo de componente por ensamble',
                ], true);
                $consumption = $this->consumeCostLayers($companyId, (string) $component['component_product_id'], $warehouseId, null, $componentQty, $issuedAt, $movementId, 'assembly_component');
                (new InventoryMovementModel())->update($movementId, ['unit_cost' => $consumption['unit_cost'], 'total_cost' => $consumption['total_cost']]);
                $totalCost += (float) $consumption['total_cost'];
            } else {
                $componentUnitCost = (float) ($component['unit_cost'] ?? 0);
                $componentTotal = $componentUnitCost * $componentQty;
                $this->applyStock($companyId, (string) $component['component_product_id'], $warehouseId, $componentQty, null);
                $movementId = (new InventoryMovementModel())->insert([
                    'company_id' => $companyId,
                    'product_id' => $component['component_product_id'],
                    'movement_type' => 'ingreso',
                    'quantity' => $componentQty,
                    'destination_warehouse_id' => $warehouseId,
                    'performed_by' => $this->apiUser()['id'],
                    'occurred_at' => $issuedAt,
                    'reason' => 'desensamble_componente',
                    'source_document' => $assemblyNumber,
                    'notes' => 'Reingreso de componente por desensamble',
                    'unit_cost' => $componentUnitCost,
                    'total_cost' => $componentTotal,
                ], true);
                $this->createCostLayer($companyId, (string) $component['component_product_id'], $warehouseId, null, $movementId, 'disassembly_component', $componentQty, $componentUnitCost, $componentTotal, $issuedAt);
                $totalCost += $componentTotal;
            }
        }

        if ($assemblyType === 'assembly') {
            $unitCost = $quantity > 0 ? $totalCost / $quantity : 0;
            $this->applyStock($companyId, $productId, $warehouseId, $quantity, null);
            $productMovementId = (new InventoryMovementModel())->insert([
                'company_id' => $companyId,
                'product_id' => $productId,
                'movement_type' => 'ingreso',
                'quantity' => $quantity,
                'destination_warehouse_id' => $warehouseId,
                'performed_by' => $this->apiUser()['id'],
                'occurred_at' => $issuedAt,
                'reason' => 'ensamble_producto',
                'source_document' => $assemblyNumber,
                'notes' => 'Ingreso por ensamble',
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
            ], true);
            $this->createCostLayer($companyId, $productId, $warehouseId, null, $productMovementId, 'assembly_output', $quantity, $unitCost, $totalCost, $issuedAt);
        } else {
            if (! $this->canWithdraw($companyId, $productId, $warehouseId, $quantity, $allowNegative, null)) {
                $db->transRollback();
                return $this->fail('No hay stock suficiente del producto principal para desensamblar.', 422);
            }
            $this->applyStock($companyId, $productId, $warehouseId, $quantity * -1, null);
            $productMovementId = (new InventoryMovementModel())->insert([
                'company_id' => $companyId,
                'product_id' => $productId,
                'movement_type' => 'egreso',
                'quantity' => $quantity,
                'source_warehouse_id' => $warehouseId,
                'performed_by' => $this->apiUser()['id'],
                'occurred_at' => $issuedAt,
                'reason' => 'desensamble_producto',
                'source_document' => $assemblyNumber,
                'notes' => 'Egreso por desensamble',
            ], true);
            $consumption = $this->consumeCostLayers($companyId, $productId, $warehouseId, null, $quantity, $issuedAt, $productMovementId, 'disassembly_output');
            $totalCost = (float) $consumption['total_cost'];
            (new InventoryMovementModel())->update($productMovementId, ['unit_cost' => $consumption['unit_cost'], 'total_cost' => $consumption['total_cost']]);
        }

        $assemblyId = (new InventoryAssemblyModel())->insert([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'assembly_number' => $assemblyNumber,
            'assembly_type' => $assemblyType,
            'quantity' => $quantity,
            'unit_cost' => $quantity > 0 ? $totalCost / $quantity : 0,
            'total_cost' => $totalCost,
            'issued_at' => $issuedAt,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        $itemModel = new InventoryAssemblyItemModel();
        foreach ($components as $component) {
            $componentQty = (float) ($component['quantity'] ?? 0) * $quantity;
            $itemModel->insert([
                'inventory_assembly_id' => $assemblyId,
                'component_product_id' => $component['component_product_id'],
                'quantity' => $componentQty,
                'unit_cost' => (float) ($component['unit_cost'] ?? 0),
                'total_cost' => (float) ($component['unit_cost'] ?? 0) * $componentQty,
            ]);
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->fail('No se pudo registrar el ensamble.', 500);
        }

        return $this->success((new InventoryAssemblyModel())->find($assemblyId), 201);
    }

    public function storeClosure()
    {
        $context = $this->inventoryContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $companyId = $context['company']['id'];
        $warehouseId = trim((string) ($payload['warehouse_id'] ?? '')) ?: null;
        $periodCode = trim((string) ($payload['period_code'] ?? ''));
        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));

        if ($periodCode === '' || $startDate === '' || $endDate === '') {
            return $this->fail('Debes indicar periodo, fecha inicial y fecha final.', 422);
        }
        if ($warehouseId && ! $this->ownedWarehouse($companyId, $warehouseId)) {
            return $this->fail('El deposito seleccionado no existe.', 422);
        }

        $model = new InventoryPeriodClosureModel();
        $duplicate = $model->where('company_id', $companyId)->where('period_code', $periodCode);
        $duplicate = $warehouseId ? $duplicate->where('warehouse_id', $warehouseId) : $duplicate->where('warehouse_id', null);
        if ($duplicate->first()) {
            return $this->fail('Ya existe un cierre para ese periodo.', 422);
        }

        $id = $model->insert([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'period_code' => $periodCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'closed',
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        return $this->success($model->find($id), 201);
    }

    public function storeRevaluation()
    {
        $context = $this->inventoryContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $companyId = $context['company']['id'];
        $productId = trim((string) ($payload['product_id'] ?? ''));
        $warehouseId = trim((string) ($payload['warehouse_id'] ?? ''));
        $newUnitCost = (float) ($payload['new_unit_cost'] ?? 0);
        if (! $this->ownedProduct($companyId, $productId) || ! $this->ownedWarehouse($companyId, $warehouseId) || $newUnitCost <= 0) {
            return $this->fail('Debes indicar producto, deposito y nuevo costo validos.', 422);
        }

        $stockSnapshot = (new InventoryStockLevelModel())->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->first();
        $quantitySnapshot = (float) ($stockSnapshot['quantity'] ?? 0);
        if ($quantitySnapshot <= 0) {
            return $this->fail('No hay stock disponible para revalorizar.', 422);
        }

        $layerModel = new InventoryCostLayerModel();
        $layers = $layerModel->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->where('remaining_quantity >', 0)->findAll();
        $previousTotal = 0.0;
        $remainingTotal = 0.0;
        foreach ($layers as $layer) {
            $remaining = (float) ($layer['remaining_quantity'] ?? 0);
            $remainingTotal += $remaining;
            $previousTotal += $remaining * (float) ($layer['unit_cost'] ?? 0);
        }
        $previousUnitCost = $remainingTotal > 0 ? $previousTotal / $remainingTotal : 0;
        $differenceAmount = ($newUnitCost - $previousUnitCost) * $quantitySnapshot;

        foreach ($layers as $layer) {
            $remaining = (float) ($layer['remaining_quantity'] ?? 0);
            $layerModel->update($layer['id'], ['unit_cost' => $newUnitCost, 'total_cost' => $remaining * $newUnitCost]);
        }

        $id = (new InventoryRevaluationModel())->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'previous_unit_cost' => $previousUnitCost,
            'new_unit_cost' => $newUnitCost,
            'quantity_snapshot' => $quantitySnapshot,
            'difference_amount' => $differenceAmount,
            'issued_at' => trim((string) ($payload['issued_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        return $this->success((new InventoryRevaluationModel())->find($id), 201);
    }

    public function storeMovement()
    {
        $context = $this->inventoryContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $companyId = $context['company']['id'];
        $productId = trim((string) ($payload['product_id'] ?? ''));
        $movementType = trim((string) ($payload['movement_type'] ?? ''));
        $quantity = (float) ($payload['quantity'] ?? 0);
        $sourceWarehouseId = trim((string) ($payload['source_warehouse_id'] ?? '')) ?: null;
        $sourceLocationId = trim((string) ($payload['source_location_id'] ?? '')) ?: null;
        $destinationWarehouseId = trim((string) ($payload['destination_warehouse_id'] ?? '')) ?: null;
        $destinationLocationId = trim((string) ($payload['destination_location_id'] ?? '')) ?: null;
        $adjustmentMode = trim((string) ($payload['adjustment_mode'] ?? '')) ?: null;
        $unitCost = array_key_exists('unit_cost', $payload) && $payload['unit_cost'] !== '' ? (float) $payload['unit_cost'] : null;
        $totalCost = $unitCost !== null ? $unitCost * $quantity : null;

        if (! in_array($movementType, ['ingreso', 'egreso', 'transferencia', 'ajuste'], true)) {
            return $this->fail('Tipo de movimiento no valido.', 422);
        }
        if ($quantity <= 0) {
            return $this->fail('La cantidad debe ser mayor a cero.', 422);
        }
        if (! $this->ownedProduct($companyId, $productId)) {
            return $this->fail('Debes seleccionar un producto valido.', 422);
        }
        if ($movementType === 'ingreso' && ! $destinationWarehouseId) {
            return $this->fail('El ingreso requiere deposito destino.', 422);
        }
        if ($movementType === 'egreso' && ! $sourceWarehouseId) {
            return $this->fail('El egreso requiere deposito origen.', 422);
        }
        if ($movementType === 'transferencia' && (! $sourceWarehouseId || ! $destinationWarehouseId || $sourceWarehouseId === $destinationWarehouseId)) {
            return $this->fail('La transferencia requiere deposito origen y destino diferentes.', 422);
        }
        if ($movementType === 'ajuste' && (! $sourceWarehouseId || ! in_array($adjustmentMode, ['increase', 'decrease'], true))) {
            return $this->fail('El ajuste requiere deposito y modo de ajuste.', 422);
        }
        if (($sourceLocationId && ! $this->ownedLocation($companyId, $sourceLocationId)) || ($destinationLocationId && ! $this->ownedLocation($companyId, $destinationLocationId))) {
            return $this->fail('Debes seleccionar ubicaciones internas validas.', 422);
        }

        $settings = $this->inventorySettings($companyId);
        $allowNegative = match ($movementType) {
            'transferencia' => $this->allowsNegativeFor('transfer', $settings),
            'ajuste' => $this->allowsNegativeFor('adjustment', $settings),
            default => (int) ($settings['allow_negative_stock'] ?? 0) === 1,
        };
        $db = db_connect();
        $db->transStart();

        if ($movementType === 'ingreso') {
            $this->applyStock($companyId, $productId, $destinationWarehouseId, $quantity, $destinationLocationId);
        } elseif ($movementType === 'egreso') {
            if (! $this->canWithdraw($companyId, $productId, $sourceWarehouseId, $quantity, $allowNegative, $sourceLocationId)) {
                $db->transRollback();
                return $this->fail('No hay stock suficiente en el deposito origen.', 422);
            }
            $this->applyStock($companyId, $productId, $sourceWarehouseId, $quantity * -1, $sourceLocationId);
        } elseif ($movementType === 'transferencia') {
            if (! $this->canWithdraw($companyId, $productId, $sourceWarehouseId, $quantity, $allowNegative, $sourceLocationId)) {
                $db->transRollback();
                return $this->fail('No hay stock suficiente en el deposito origen para transferir.', 422);
            }
            $this->applyStock($companyId, $productId, $sourceWarehouseId, $quantity * -1, $sourceLocationId);
            $this->applyStock($companyId, $productId, $destinationWarehouseId, $quantity, $destinationLocationId);
        } else {
            if ($adjustmentMode === 'decrease' && ! $this->canWithdraw($companyId, $productId, $sourceWarehouseId, $quantity, $allowNegative, $sourceLocationId)) {
                $db->transRollback();
                return $this->fail('No hay stock suficiente para ajustar a la baja.', 422);
            }
            $this->applyStock($companyId, $productId, $sourceWarehouseId, $adjustmentMode === 'increase' ? $quantity : $quantity * -1, $sourceLocationId);
        }

        $id = (new InventoryMovementModel())->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'adjustment_mode' => $movementType === 'ajuste' ? $adjustmentMode : null,
            'source_warehouse_id' => in_array($movementType, ['egreso', 'transferencia', 'ajuste'], true) ? $sourceWarehouseId : null,
            'source_location_id' => in_array($movementType, ['egreso', 'transferencia', 'ajuste'], true) ? $sourceLocationId : null,
            'destination_warehouse_id' => in_array($movementType, ['ingreso', 'transferencia'], true) ? $destinationWarehouseId : null,
            'destination_location_id' => in_array($movementType, ['ingreso', 'transferencia'], true) ? $destinationLocationId : null,
            'performed_by' => $this->apiUser()['id'],
            'occurred_at' => trim((string) ($payload['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            'reason' => trim((string) ($payload['reason'] ?? '')),
            'source_document' => trim((string) ($payload['source_document'] ?? '')),
            'lot_number' => trim((string) ($payload['lot_number'] ?? '')),
            'serial_number' => trim((string) ($payload['serial_number'] ?? '')),
            'expiration_date' => trim((string) ($payload['expiration_date'] ?? '')) ?: null,
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ], true);

        $this->syncAdvancedInventoryArtifacts($companyId, $productId, $id, [
            'movement_type' => $movementType,
            'adjustment_mode' => $adjustmentMode,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'source_warehouse_id' => $sourceWarehouseId,
            'source_location_id' => $sourceLocationId,
            'destination_warehouse_id' => $destinationWarehouseId,
            'destination_location_id' => $destinationLocationId,
            'lot_number' => trim((string) ($payload['lot_number'] ?? '')),
            'serial_number' => trim((string) ($payload['serial_number'] ?? '')),
            'expiration_date' => trim((string) ($payload['expiration_date'] ?? '')) ?: null,
            'occurred_at' => trim((string) ($payload['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->fail('No se pudo registrar el movimiento.', 500);
        }

        return $this->success((new InventoryMovementModel())->find($id), 201);
    }

    public function kardex()
    {
        return $this->movements();
    }

    private function inventoryContext(string $requiredAccess): array
    {
        $companyId = $this->resolveCompanyId();
        if (! $companyId) {
            return ['error' => 'Debes seleccionar una empresa para operar Inventario.', 'status' => 422];
        }

        $company = (new CompanyModel())->find($companyId);
        if (! $company) {
            return ['error' => 'La empresa seleccionada no existe.', 'status' => 404];
        }

        $system = (new SystemModel())->where('slug', 'inventario')->first();
        if (! $system || (int) ($system['active'] ?? 0) !== 1) {
            return ['error' => 'El sistema Inventario no esta disponible.', 'status' => 404];
        }

        $companyAssignment = (new CompanySystemModel())->where('company_id', $companyId)->where('system_id', $system['id'])->where('active', 1)->first();
        $accessLevel = 'view';

        if (! $this->apiIsSuperadmin()) {
            if (! $companyAssignment) {
                return ['error' => 'La empresa no tiene Inventario asignado.', 'status' => 403];
            }

            $assignment = (new UserSystemModel())
                ->where('company_id', $companyId)
                ->where('user_id', $this->apiUser()['id'] ?? '')
                ->where('system_id', $system['id'])
                ->where('active', 1)
                ->first();

            if (! $assignment) {
                return ['error' => 'Tu usuario no tiene acceso activo a Inventario.', 'status' => 403];
            }

            $accessLevel = $assignment['access_level'] ?? 'view';
        }

        if ($requiredAccess === 'configure' && ! ($this->apiIsSuperadmin() || (($this->apiUser()['role_slug'] ?? null) === 'admin' && $accessLevel === 'manage'))) {
            return ['error' => 'Solo superadmin o admin pueden configurar Inventario.', 'status' => 403];
        }

        if ($requiredAccess === 'manage' && ! ($this->apiIsSuperadmin() || $accessLevel === 'manage')) {
            return ['error' => 'Tu usuario solo tiene acceso de consulta en Inventario.', 'status' => 403];
        }

        $this->ensureDefaults($companyId, $company);

        return [
            'company' => $company,
            'system' => $system,
            'access_level' => $accessLevel,
        ];
    }

    private function resolveCompanyId(): ?string
    {
        if ($this->apiIsSuperadmin()) {
            $companyId = trim((string) ($this->request->getGet('company_id') ?? ($this->payload()['company_id'] ?? '')));
            if ($companyId !== '') {
                return $companyId;
            }
            $company = (new CompanyModel())->orderBy('name', 'ASC')->first();
            return $company['id'] ?? null;
        }

        return $this->apiCompanyId();
    }

    private function ensureDefaults(string $companyId, array $company): void
    {
        $settingsModel = new InventorySettingModel();
        if (! $settingsModel->where('company_id', $companyId)->first()) {
            $settingsModel->insert([
                'company_id' => $companyId,
                'alert_email' => $company['email'] ?? null,
                'unusual_movement_threshold' => 100,
                'no_rotation_days' => 30,
                'allow_negative_stock' => 0,
                'low_stock_alerts' => 1,
                'internal_notifications' => 1,
                'email_notifications' => 0,
                'valuation_method' => 'weighted_average',
                'negative_stock_scope' => 'global',
                'allow_negative_on_sales' => 0,
                'allow_negative_on_transfers' => 0,
                'allow_negative_on_adjustments' => 0,
            ]);
        }

        $warehouseModel = new InventoryWarehouseModel();
        if (! $warehouseModel->where('company_id', $companyId)->first()) {
            $warehouseModel->insert([
                'company_id' => $companyId,
                'branch_id' => $this->apiUser()['branch_id'] ?? null,
                'name' => 'Deposito Central',
                'code' => 'DEP-CENTRAL',
                'type' => 'central',
                'description' => 'Deposito inicial del sistema de inventario.',
                'is_default' => 1,
                'active' => 1,
            ]);
        }
    }

    private function inventorySettings(string $companyId): array
    {
        return (new InventorySettingModel())->where('company_id', $companyId)->first() ?? [];
    }

    private function summaryMetrics(string $companyId): array
    {
        $total = (new InventoryStockLevelModel())->selectSum('quantity', 'total')->where('company_id', $companyId)->first();
        return [
            'products' => (new InventoryProductModel())->where('company_id', $companyId)->countAllResults(),
            'warehouses' => (new InventoryWarehouseModel())->where('company_id', $companyId)->countAllResults(),
            'total_stock' => (float) ($total['total'] ?? 0),
            'reserved_stock' => (float) (((new InventoryStockLevelModel())->selectSum('reserved_quantity', 'reserved')->where('company_id', $companyId)->first()['reserved'] ?? 0)),
            'active_reservations' => (new InventoryReservationModel())->where('company_id', $companyId)->where('status', 'active')->countAllResults(),
        ];
    }

    private function warehouseList(string $companyId): array
    {
        return db_connect()->table('inventory_warehouses w')
            ->select('w.*, branches.name AS branch_name')
            ->join('branches', 'branches.id = w.branch_id', 'left')
            ->where('w.company_id', $companyId)
            ->orderBy('w.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function locationList(string $companyId): array
    {
        return db_connect()->table('inventory_locations l')
            ->select('l.*, w.name AS warehouse_name, w.code AS warehouse_code')
            ->join('inventory_warehouses w', 'w.id = l.warehouse_id')
            ->where('l.company_id', $companyId)
            ->orderBy('w.name', 'ASC')
            ->orderBy('l.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function productRows(string $companyId): array
    {
        $rows = db_connect()->table('inventory_products p')
            ->select('p.id, p.company_id, p.sku, p.name, p.category, p.brand, p.barcode, p.product_type, p.description, p.unit, p.min_stock, p.max_stock, p.cost_price, p.sale_price, p.lot_control, p.serial_control, p.expiration_control, p.active, COALESCE(SUM(s.quantity), 0) AS total_stock, COALESCE(SUM(s.reserved_quantity), 0) AS reserved_stock', false)
            ->join('inventory_stock_levels s', 's.product_id = p.id', 'left')
            ->where('p.company_id', $companyId)
            ->groupBy('p.id, p.company_id, p.sku, p.name, p.category, p.brand, p.barcode, p.product_type, p.description, p.unit, p.min_stock, p.max_stock, p.cost_price, p.sale_price, p.lot_control, p.serial_control, p.expiration_control, p.active')
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();

        $valuationMap = $this->productValuationMap($companyId);

        return array_map(function (array $row) use ($valuationMap): array {
            $valuation = $valuationMap[$row['id']] ?? ['stock_value' => 0.0, 'average_cost' => 0.0];
            $row['total_stock'] = (float) ($row['total_stock'] ?? 0);
            $row['reserved_stock'] = (float) ($row['reserved_stock'] ?? 0);
            $row['min_stock'] = (float) ($row['min_stock'] ?? 0);
            $row['max_stock'] = (float) ($row['max_stock'] ?? 0);
            $row['available_stock'] = $row['total_stock'] - $row['reserved_stock'];
            $row['is_critical'] = $row['available_stock'] <= $row['min_stock'];
            $row['is_overstock'] = $row['max_stock'] > 0 && $row['total_stock'] > $row['max_stock'];
            $row['stock_value'] = (float) ($valuation['stock_value'] ?? 0);
            $row['average_cost'] = (float) ($valuation['average_cost'] ?? 0);

            return $row;
        }, $rows);
    }

    private function productValuationMap(string $companyId): array
    {
        $layerRows = db_connect()->table('inventory_cost_layers')
            ->select('product_id, COALESCE(SUM(total_cost), 0) AS stock_value, COALESCE(SUM(remaining_quantity), 0) AS remaining_quantity', false)
            ->where('company_id', $companyId)
            ->where('remaining_quantity >', 0)
            ->groupBy('product_id')
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($layerRows as $row) {
            $remaining = (float) ($row['remaining_quantity'] ?? 0);
            $value = (float) ($row['stock_value'] ?? 0);
            $map[$row['product_id']] = [
                'stock_value' => $value,
                'average_cost' => $remaining > 0 ? $value / $remaining : 0.0,
            ];
        }

        foreach ($rows = db_connect()->table('inventory_products')->select('id, cost_price')->where('company_id', $companyId)->get()->getResultArray() as $product) {
            if (isset($map[$product['id']])) {
                continue;
            }

            $stockRow = db_connect()->table('inventory_stock_levels')
                ->select('COALESCE(SUM(quantity), 0) AS quantity', false)
                ->where('company_id', $companyId)
                ->where('product_id', $product['id'])
                ->get()
                ->getRowArray() ?? [];

            $quantity = (float) ($stockRow['quantity'] ?? 0);
            $cost = (float) ($product['cost_price'] ?? 0);
            $map[$product['id']] = [
                'stock_value' => $quantity * $cost,
                'average_cost' => $quantity > 0 ? $cost : 0.0,
            ];
        }

        return $map;
    }

    private function alerts(string $companyId): array
    {
        $settings = $this->inventorySettings($companyId);
        $threshold = (float) ($settings['unusual_movement_threshold'] ?? 100);

        return [
            'critical' => array_values(array_filter($this->productRows($companyId), static fn(array $row): bool => (bool) ($row['is_critical'] ?? false))),
            'overstock' => array_values(array_filter($this->productRows($companyId), static fn(array $row): bool => (bool) ($row['is_overstock'] ?? false))),
            'reservations' => $this->activeReservations($companyId, null, 10),
            'unusual' => db_connect()->table('inventory_movements m')
                ->select('m.occurred_at, m.quantity, m.movement_type, p.name AS product_name')
                ->join('inventory_products p', 'p.id = m.product_id')
                ->where('m.company_id', $companyId)
                ->where('m.quantity >=', $threshold)
                ->orderBy('m.occurred_at', 'DESC')
                ->limit(10)
                ->get()
                ->getResultArray(),
        ];
    }

    private function movementRows(string $companyId, ?string $productId, int $limit): array
    {
        $builder = db_connect()->table('inventory_movements m')
            ->select('m.*, p.name AS product_name, p.sku, p.category, p.brand, u.name AS user_name, source.name AS source_name, destination.name AS destination_name, sl.name AS source_location_name, dl.name AS destination_location_name')
            ->join('inventory_products p', 'p.id = m.product_id')
            ->join('users u', 'u.id = m.performed_by')
            ->join('inventory_warehouses source', 'source.id = m.source_warehouse_id', 'left')
            ->join('inventory_warehouses destination', 'destination.id = m.destination_warehouse_id', 'left')
            ->join('inventory_locations sl', 'sl.id = m.source_location_id', 'left')
            ->join('inventory_locations dl', 'dl.id = m.destination_location_id', 'left')
            ->where('m.company_id', $companyId)
            ->orderBy('m.occurred_at', 'DESC');

        if ($productId) {
            $builder->where('m.product_id', $productId);
        }

        return $builder->limit($limit)->get()->getResultArray();
    }

    private function productTraceabilityStock(string $companyId, string $productId): array
    {
        return db_connect()->table('inventory_warehouses w')
            ->select('w.name, w.code, w.type, w.active, COALESCE(SUM(s.quantity), 0) AS quantity, COALESCE(SUM(s.reserved_quantity), 0) AS reserved_quantity, (COALESCE(SUM(s.quantity), 0) - COALESCE(SUM(s.reserved_quantity), 0)) AS available_quantity, COALESCE(MAX(s.min_stock), p.min_stock) AS min_stock', false)
            ->join('inventory_stock_levels s', 's.warehouse_id = w.id AND s.product_id = ' . db_connect()->escape($productId), 'left')
            ->join('inventory_products p', 'p.id = ' . db_connect()->escape($productId), 'left')
            ->where('w.company_id', $companyId)
            ->groupBy('w.id, w.name, w.code, w.type, w.active, p.min_stock')
            ->orderBy('w.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function productLocationStock(string $companyId, string $productId): array
    {
        return db_connect()->table('inventory_locations l')
            ->select('l.*, w.name AS warehouse_name, COALESCE(s.quantity, 0) AS quantity, COALESCE(s.reserved_quantity, 0) AS reserved_quantity, (COALESCE(s.quantity, 0) - COALESCE(s.reserved_quantity, 0)) AS available_quantity', false)
            ->join('inventory_warehouses w', 'w.id = l.warehouse_id')
            ->join('inventory_stock_levels s', 's.location_id = l.id AND s.product_id = ' . db_connect()->escape($productId), 'left')
            ->where('l.company_id', $companyId)
            ->orderBy('w.name', 'ASC')
            ->orderBy('l.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function lotRows(string $companyId, ?string $productId = null): array
    {
        $builder = db_connect()->table('inventory_lots l')
            ->select('l.*, p.name AS product_name, p.sku, w.name AS warehouse_name, loc.name AS location_name')
            ->join('inventory_products p', 'p.id = l.product_id')
            ->join('inventory_warehouses w', 'w.id = l.warehouse_id')
            ->join('inventory_locations loc', 'loc.id = l.location_id', 'left')
            ->where('l.company_id', $companyId)
            ->orderBy('l.updated_at', 'DESC');

        if ($productId) {
            $builder->where('l.product_id', $productId);
        }

        return $builder->get()->getResultArray();
    }

    private function serialRows(string $companyId, ?string $productId = null): array
    {
        $builder = db_connect()->table('inventory_serials s')
            ->select('s.*, p.name AS product_name, p.sku, w.name AS warehouse_name, loc.name AS location_name')
            ->join('inventory_products p', 'p.id = s.product_id')
            ->join('inventory_warehouses w', 'w.id = s.warehouse_id', 'left')
            ->join('inventory_locations loc', 'loc.id = s.location_id', 'left')
            ->where('s.company_id', $companyId)
            ->orderBy('s.updated_at', 'DESC');

        if ($productId) {
            $builder->where('s.product_id', $productId);
        }

        return $builder->get()->getResultArray();
    }

    private function kitItemRows(string $productId): array
    {
        return db_connect()->table('inventory_kit_items k')
            ->select('k.*, p.sku AS component_sku, p.name AS component_name')
            ->join('inventory_products p', 'p.id = k.component_product_id')
            ->where('k.product_id', $productId)
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function costLayerRows(string $companyId, ?string $productId = null, int $limit = 50): array
    {
        $builder = db_connect()->table('inventory_cost_layers c')
            ->select('c.*, p.name AS product_name, p.sku, w.name AS warehouse_name, loc.name AS location_name')
            ->join('inventory_products p', 'p.id = c.product_id')
            ->join('inventory_warehouses w', 'w.id = c.warehouse_id', 'left')
            ->join('inventory_locations loc', 'loc.id = c.location_id', 'left')
            ->where('c.company_id', $companyId)
            ->orderBy('c.occurred_at', 'DESC');

        if ($productId) {
            $builder->where('c.product_id', $productId);
        }

        return $builder->limit($limit)->get()->getResultArray();
    }

    private function assemblyRows(string $companyId, int $limit = 20): array
    {
        return db_connect()->table('inventory_assemblies a')
            ->select('a.*, p.name AS product_name, p.sku, w.name AS warehouse_name')
            ->join('inventory_products p', 'p.id = a.product_id')
            ->join('inventory_warehouses w', 'w.id = a.warehouse_id', 'left')
            ->where('a.company_id', $companyId)
            ->orderBy('a.issued_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function periodClosureRows(string $companyId, int $limit = 20): array
    {
        return db_connect()->table('inventory_period_closures c')
            ->select('c.*, w.name AS warehouse_name')
            ->join('inventory_warehouses w', 'w.id = c.warehouse_id', 'left')
            ->where('c.company_id', $companyId)
            ->orderBy('c.end_date', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function revaluationRows(string $companyId, int $limit = 20): array
    {
        return db_connect()->table('inventory_revaluations r')
            ->select('r.*, p.name AS product_name, p.sku, w.name AS warehouse_name')
            ->join('inventory_products p', 'p.id = r.product_id')
            ->join('inventory_warehouses w', 'w.id = r.warehouse_id', 'left')
            ->where('r.company_id', $companyId)
            ->orderBy('r.issued_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function activeReservations(string $companyId, ?string $productId = null, int $limit = 20): array
    {
        $builder = db_connect()->table('inventory_reservations r')
            ->select('r.*, p.name AS product_name, p.sku, w.name AS warehouse_name, w.code AS warehouse_code, u.name AS reserved_by_name')
            ->join('inventory_products p', 'p.id = r.product_id')
            ->join('inventory_warehouses w', 'w.id = r.warehouse_id')
            ->join('users u', 'u.id = r.reserved_by')
            ->where('r.company_id', $companyId)
            ->where('r.status', 'active')
            ->orderBy('r.reserved_at', 'DESC');

        if ($productId) {
            $builder->where('r.product_id', $productId);
        }

        return $builder->limit($limit)->get()->getResultArray();
    }

    private function ownedWarehouse(string $companyId, string $warehouseId): ?array
    {
        $row = (new InventoryWarehouseModel())->where('company_id', $companyId)->where('id', $warehouseId)->first();
        return $row ?: null;
    }

    private function ownedProduct(string $companyId, string $productId): ?array
    {
        $row = (new InventoryProductModel())->where('company_id', $companyId)->where('id', $productId)->first();
        return $row ?: null;
    }

    private function ownedLocation(string $companyId, string $locationId): ?array
    {
        $row = (new InventoryLocationModel())->where('company_id', $companyId)->where('id', $locationId)->first();
        return $row ?: null;
    }

    private function canWithdraw(string $companyId, string $productId, ?string $warehouseId, float $quantity, bool $allowNegative, ?string $locationId = null): bool
    {
        if ($allowNegative || $warehouseId === null) {
            return true;
        }

        $builder = (new InventoryStockLevelModel())
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if ($locationId !== null) {
            $builder->where('location_id', $locationId);
        }

        $row = $builder->first();

        return (((float) ($row['quantity'] ?? 0)) - ((float) ($row['reserved_quantity'] ?? 0))) >= $quantity;
    }

    private function allowsNegativeFor(string $operation, array $settings): bool
    {
        if ((int) ($settings['allow_negative_stock'] ?? 0) === 1) {
            return true;
        }

        return match ($operation) {
            'sale' => (int) ($settings['allow_negative_on_sales'] ?? 0) === 1,
            'transfer', 'assembly' => (int) ($settings['allow_negative_on_transfers'] ?? 0) === 1,
            'adjustment' => (int) ($settings['allow_negative_on_adjustments'] ?? 0) === 1,
            default => false,
        };
    }

    private function canReserve(string $companyId, string $productId, ?string $warehouseId, float $quantity): bool
    {
        if ($warehouseId === null) {
            return false;
        }

        $row = (new InventoryStockLevelModel())
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return (((float) ($row['quantity'] ?? 0)) - ((float) ($row['reserved_quantity'] ?? 0))) >= $quantity;
    }

    private function applyStock(string $companyId, string $productId, ?string $warehouseId, float $delta, ?string $locationId = null): void
    {
        if ($warehouseId === null) {
            return;
        }

        $stockModel = new InventoryStockLevelModel();
        $product = (new InventoryProductModel())->find($productId);
        $existing = $stockModel->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->where('location_id', $locationId)->first();

        if ($existing) {
            $stockModel->update($existing['id'], ['quantity' => ((float) $existing['quantity']) + $delta]);
            return;
        }

        $stockModel->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'quantity' => $delta,
            'reserved_quantity' => 0,
            'min_stock' => $product['min_stock'] ?? 0,
        ]);
    }

    private function applyReserved(string $companyId, string $productId, ?string $warehouseId, float $delta): void
    {
        if ($warehouseId === null) {
            return;
        }

        $stockModel = new InventoryStockLevelModel();
        $product = (new InventoryProductModel())->find($productId);
        $existing = $stockModel->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->first();

        if ($existing) {
            $stockModel->update($existing['id'], [
                'reserved_quantity' => max(0, ((float) $existing['reserved_quantity']) + $delta),
            ]);
            return;
        }

        $stockModel->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'reserved_quantity' => max(0, $delta),
            'min_stock' => $product['min_stock'] ?? 0,
        ]);
    }

    private function ownedReservation(string $companyId, string $reservationId): ?array
    {
        $row = (new InventoryReservationModel())
            ->where('company_id', $companyId)
            ->where('id', $reservationId)
            ->first();

        return $row ?: null;
    }

    private function requestKitItems(string $companyId, string $productId, array $payload): array
    {
        $componentIds = (array) ($payload['component_product_id'] ?? []);
        $quantities = (array) ($payload['component_quantity'] ?? []);
        $rows = [];

        foreach ($componentIds as $index => $componentId) {
            $componentId = trim((string) $componentId);
            $quantity = (float) ($quantities[$index] ?? 0);
            if ($componentId === '' || $quantity <= 0 || $componentId === $productId) {
                continue;
            }
            if (! $this->ownedProduct($companyId, $componentId)) {
                continue;
            }
            $rows[] = ['component_product_id' => $componentId, 'quantity' => $quantity];
        }

        return $rows;
    }

    private function syncKitItems(string $productId, array $rows): void
    {
        $model = new InventoryKitItemModel();
        $model->where('product_id', $productId)->delete();

        foreach ($rows as $row) {
            $model->insert([
                'product_id' => $productId,
                'component_product_id' => $row['component_product_id'],
                'quantity' => $row['quantity'],
            ]);
        }
    }

    private function syncAdvancedInventoryArtifacts(string $companyId, string $productId, string $movementId, array $payload): void
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
            $this->syncSerialRecord($companyId, $productId, $serialNumber, $warehouseId ? (string) $warehouseId : null, $locationId, $lotNumber !== '' ? $lotNumber : null, $expirationDate, $direction >= 0 ? 'available' : 'consumed', $movementId);
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
        $lot = $model->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->where('location_id', $locationId)->where('lot_number', $lotNumber)->first();
        if ($lot) {
            $balance = max(0, ((float) $lot['quantity_balance']) + $delta);
            $model->update($lot['id'], ['expiration_date' => $expirationDate ?: $lot['expiration_date'], 'quantity_balance' => $balance, 'status' => $balance > 0 ? 'active' : 'closed']);
            return;
        }

        if ($delta <= 0) {
            return;
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
        $serial = $model->where('company_id', $companyId)->where('product_id', $productId)->where('serial_number', $serialNumber)->first();
        $payload = ['warehouse_id' => $warehouseId, 'location_id' => $locationId, 'lot_number' => $lotNumber, 'expiration_date' => $expirationDate, 'status' => $status, 'last_movement_id' => $movementId];

        if ($serial) {
            $model->update($serial['id'], $payload);
            return;
        }

        $model->insert(array_merge($payload, ['company_id' => $companyId, 'product_id' => $productId, 'serial_number' => $serialNumber]));
    }

    private function createCostLayer(string $companyId, string $productId, string $warehouseId, ?string $locationId, string $movementId, string $layerType, float $quantity, float $unitCost, float $totalCost, string $occurredAt): void
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
        $method = strtolower((string) ($this->inventorySettings($companyId)['valuation_method'] ?? 'weighted_average'));

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

        if ($locationId === null) {
            $builder->where('location_id', null);
        } else {
            $builder->where('location_id', $locationId);
        }

        $row = $builder->get()->getRowArray() ?? [];

        return [
            'quantity' => (float) ($row['quantity'] ?? 0),
            'value' => (float) ($row['value'] ?? 0),
        ];
    }

    private function consumeCostLayers(string $companyId, string $productId, string $warehouseId, ?string $locationId, float $quantity, string $occurredAt, string $movementId, string $layerType): array
    {
        $model = new InventoryCostLayerModel();
        $method = $this->valuationMethod($companyId);
        $builder = $model
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_quantity >', 0);

        if ($locationId === null) {
            $builder->where('location_id', null);
        } else {
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
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
        ];
    }
}
