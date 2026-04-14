<?php

namespace App\Controllers;

use App\Models\BranchModel;
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
use App\Models\SaleModel;
use App\Models\SystemModel;
use App\Models\UserSystemModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\RedirectResponse;
use Dompdf\Dompdf;
use Dompdf\Options;

class InventoryController extends BaseController
{
    public function index()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $this->ensureInventoryDefaults($context['company']['id']);
        $settings = $this->inventorySettings($context['company']['id']);

        return view('inventory/index', [
            'pageTitle' => 'Inventario',
            'user' => $this->currentUser(),
            'context' => $context,
            'companies' => $this->inventoryCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'settings' => $settings,
            'summary' => $this->summaryMetrics($context['company']['id']),
            'alerts' => $this->alerts($context['company']['id'], $settings),
            'warehouses' => $this->warehouseOverview($context['company']['id']),
            'products' => $this->productStockRows($context['company']['id']),
            'recentMovements' => $this->recentMovements($context['company']['id']),
            'activeReservations' => $this->activeReservations($context['company']['id'], null, 8),
            'kardexPreview' => $this->kardexRows($context['company']['id'], [], 10),
        ]);
    }

    public function configuration()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'Solo superadmin o admin pueden configurar Inventario.');
        }

        $this->ensureInventoryDefaults($context['company']['id']);

        return view('inventory/configuration', [
            'pageTitle' => 'Configuracion de Inventario',
            'user' => $this->currentUser(),
            'context' => $context,
            'companies' => $this->inventoryCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'settings' => $this->inventorySettings($context['company']['id']),
            'warehouses' => $this->warehouseList($context['company']['id']),
            'locations' => $this->locationList($context['company']['id']),
            'products' => $this->productStockRows($context['company']['id']),
            'reservations' => $this->activeReservations($context['company']['id'], null, 20),
            'costLayers' => $this->costLayerRows($context['company']['id'], null, 20),
            'assemblies' => $this->assemblyRows($context['company']['id']),
            'periodClosures' => $this->periodClosureRows($context['company']['id']),
            'revaluations' => $this->revaluationRows($context['company']['id']),
            'branches' => $this->branchOptions($context['company']['id']),
        ]);
    }

    public function kardex()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $filters = $this->kardexFilters();
        $productId = $filters['product_id'];

        return view('inventory/kardex', [
            'pageTitle' => 'Kardex de Inventario',
            'user' => $this->currentUser(),
            'context' => $context,
            'companies' => $this->inventoryCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'products' => $this->activeProducts($context['company']['id']),
            'warehouses' => $this->activeWarehouses($context['company']['id']),
            'selectedProductId' => $productId,
            'filters' => $filters,
            'settings' => $this->inventorySettings($context['company']['id']),
            'summaryRows' => $this->kardexProductSummaryRows($context['company']['id'], $filters),
            'rows' => $this->kardexRows($context['company']['id'], $filters, 400),
        ]);
    }

    public function kardexDetail(string $productId)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $product = $this->ownedProduct($context['company']['id'], $productId);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario/kardex', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        $filters = $this->kardexFilters();
        $filters['product_id'] = $productId;

        return view('inventory/forms/kardex_detail', [
            'pageTitle' => 'Detalle de kardex',
            'product' => $product,
            'rows' => $this->kardexRows($context['company']['id'], $filters, 300),
            'filters' => $filters,
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function kardexPdf()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $filters = $this->kardexFilters();

        return $this->renderPdf(
            'inventory/pdf/kardex',
            [
                'company' => $context['company'],
                'filters' => $filters,
                'rows' => $this->kardexRows($context['company']['id'], $filters, 1000),
                'summaryRows' => $this->kardexProductSummaryRows($context['company']['id'], $filters),
                'generatedAt' => date('d/m/Y H:i'),
            ],
            'kardex-' . date('Ymd-His') . '.pdf',
            'landscape'
        );
    }

    public function kardexProductPdf(string $productId)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $product = $this->ownedProduct($context['company']['id'], $productId);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario/kardex', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        $filters = $this->kardexFilters();
        $filters['product_id'] = $productId;

        return $this->renderPdf(
            'inventory/pdf/product_movements',
            [
                'company' => $context['company'],
                'product' => $product,
                'filters' => $filters,
                'rows' => $this->kardexRows($context['company']['id'], $filters, 1000),
                'generatedAt' => date('d/m/Y H:i'),
            ],
            'movimientos-' . $product['sku'] . '.pdf',
            'landscape'
        );
    }

    public function productPdf(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $product = $this->ownedProduct($context['company']['id'], $id);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario/kardex', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        return $this->renderPdf(
            'inventory/pdf/product',
            [
                'company' => $context['company'],
                'product' => $product,
                'stockByWarehouse' => $this->productWarehouseStock($context['company']['id'], $id),
                'reservations' => $this->activeReservations($context['company']['id'], $id, 100),
                'movements' => $this->kardexRows($context['company']['id'], ['product_id' => $id], 20),
                'generatedAt' => date('d/m/Y H:i'),
            ],
            'producto-' . $product['sku'] . '.pdf'
        );
    }

    public function productTraceabilityPdf(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $product = $this->ownedProduct($context['company']['id'], $id);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario/kardex', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        return $this->renderPdf(
            'inventory/pdf/traceability',
            [
                'company' => $context['company'],
                'product' => $product,
                'stockByWarehouse' => $this->productWarehouseStock($context['company']['id'], $id),
                'reservations' => $this->activeReservations($context['company']['id'], $id, 100),
                'movements' => $this->kardexRows($context['company']['id'], ['product_id' => $id], 200),
                'generatedAt' => date('d/m/Y H:i'),
            ],
            'trazabilidad-' . $product['sku'] . '.pdf',
            'landscape'
        );
    }

    public function editSettingsForm()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'Solo superadmin o admin pueden configurar Inventario.');
        }

        $this->ensureInventoryDefaults($context['company']['id']);

        return view('inventory/forms/settings', [
            'pageTitle' => 'Parametros de inventario',
            'settings' => $this->inventorySettings($context['company']['id']),
            'company' => $context['company'],
            'formAction' => site_url('inventario/configuracion/parametros'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function updateSettings()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'Solo superadmin o admin pueden configurar Inventario.');
        }

        $settingsModel = new InventorySettingModel();
        $settings = $this->inventorySettings($context['company']['id']);

        $payload = [
            'company_id' => $context['company']['id'],
            'alert_email' => trim((string) $this->request->getPost('alert_email')),
            'unusual_movement_threshold' => (float) $this->request->getPost('unusual_movement_threshold'),
            'no_rotation_days' => max(1, (int) $this->request->getPost('no_rotation_days')),
            'allow_negative_stock' => $this->request->getPost('allow_negative_stock') === '1' ? 1 : 0,
            'low_stock_alerts' => $this->request->getPost('low_stock_alerts') === '0' ? 0 : 1,
            'internal_notifications' => $this->request->getPost('internal_notifications') === '0' ? 0 : 1,
            'email_notifications' => $this->request->getPost('email_notifications') === '1' ? 1 : 0,
            'valuation_method' => trim((string) $this->request->getPost('valuation_method')) ?: 'weighted_average',
            'negative_stock_scope' => trim((string) $this->request->getPost('negative_stock_scope')) ?: 'global',
            'allow_negative_on_sales' => $this->request->getPost('allow_negative_on_sales') === '1' ? 1 : 0,
            'allow_negative_on_transfers' => $this->request->getPost('allow_negative_on_transfers') === '1' ? 1 : 0,
            'allow_negative_on_adjustments' => $this->request->getPost('allow_negative_on_adjustments') === '1' ? 1 : 0,
        ];

        $settingsModel->update($settings['id'], $payload);

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $context['company']['id']), 'Parametros de inventario actualizados correctamente.');
    }

    public function createWarehouseForm()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para registrar depositos.');
        }

        return view('inventory/forms/warehouse', [
            'pageTitle' => 'Deposito',
            'warehouse' => null,
            'formAction' => site_url('inventario/depositos'),
            'branches' => $this->branchOptions($context['company']['id']),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeWarehouse()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para registrar depositos.');
        }

        $warehouseModel = new InventoryWarehouseModel();
        $code = strtoupper(trim((string) $this->request->getPost('code')));

        if ($code === '' || trim((string) $this->request->getPost('name')) === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar nombre y codigo del deposito.');
        }

        if ($warehouseModel->where('company_id', $context['company']['id'])->where('code', $code)->first()) {
            return redirect()->back()->withInput()->with('error', 'Ya existe un deposito con ese codigo en la empresa.');
        }

        $payload = [
            'company_id' => $context['company']['id'],
            'branch_id' => trim((string) $this->request->getPost('branch_id')) ?: null,
            'name' => trim((string) $this->request->getPost('name')),
            'code' => $code,
            'type' => trim((string) $this->request->getPost('type')) ?: 'general',
            'description' => trim((string) $this->request->getPost('description')),
            'is_default' => $this->request->getPost('is_default') === '1' ? 1 : 0,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ];

        if ($payload['is_default'] === 1) {
            $warehouseModel->where('company_id', $context['company']['id'])->set(['is_default' => 0])->update();
        }

        $warehouseModel->insert($payload);

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $context['company']['id']), 'Deposito registrado correctamente.');
    }

    public function editWarehouseForm(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para editar depositos.');
        }

        $warehouse = $this->ownedWarehouse($context['company']['id'], $id);

        if (! $warehouse) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Deposito no disponible.');
        }

        return view('inventory/forms/warehouse', [
            'pageTitle' => 'Deposito',
            'warehouse' => $warehouse,
            'formAction' => site_url('inventario/depositos/' . $id . '/actualizar'),
            'branches' => $this->branchOptions($context['company']['id']),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function updateWarehouse(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para editar depositos.');
        }

        $warehouseModel = new InventoryWarehouseModel();
        $warehouse = $this->ownedWarehouse($context['company']['id'], $id);

        if (! $warehouse) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Deposito no disponible.');
        }

        $code = strtoupper(trim((string) $this->request->getPost('code')));

        if ($code === '' || trim((string) $this->request->getPost('name')) === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar nombre y codigo del deposito.');
        }

        $duplicate = $warehouseModel
            ->where('company_id', $context['company']['id'])
            ->where('code', $code)
            ->where('id !=', $id)
            ->first();

        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Ya existe un deposito con ese codigo en la empresa.');
        }

        $payload = [
            'branch_id' => trim((string) $this->request->getPost('branch_id')) ?: null,
            'name' => trim((string) $this->request->getPost('name')),
            'code' => $code,
            'type' => trim((string) $this->request->getPost('type')) ?: 'general',
            'description' => trim((string) $this->request->getPost('description')),
            'is_default' => $this->request->getPost('is_default') === '1' ? 1 : 0,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ];

        if ($payload['is_default'] === 1) {
            $warehouseModel->where('company_id', $context['company']['id'])->set(['is_default' => 0])->update();
        } elseif ((int) $warehouse['is_default'] === 1) {
            $payload['is_default'] = 1;
        }

        $warehouseModel->update($id, $payload);

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $context['company']['id']), 'Deposito actualizado correctamente.');
    }

    public function toggleWarehouse(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para cambiar el estado del deposito.');
        }

        $warehouseModel = new InventoryWarehouseModel();
        $warehouse = $this->ownedWarehouse($context['company']['id'], $id);

        if (! $warehouse) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Deposito no disponible.');
        }

        if ((int) $warehouse['is_default'] === 1 && (int) $warehouse['active'] === 1) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'El deposito base no puede deshabilitarse.');
        }

        $warehouseModel->update($id, [
            'active' => (int) $warehouse['active'] === 1 ? 0 : 1,
        ]);

        return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('message', 'Estado del deposito actualizado correctamente.');
    }

    public function deleteWarehouse(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para eliminar depositos.');
        }

        $warehouseModel = new InventoryWarehouseModel();
        $warehouse = $this->ownedWarehouse($context['company']['id'], $id);

        if (! $warehouse) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Deposito no disponible.');
        }

        if ((int) $warehouse['is_default'] === 1) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'El deposito base no puede eliminarse.');
        }

        $hasStock = (new InventoryStockLevelModel())
            ->where('warehouse_id', $id)
            ->where('quantity !=', 0)
            ->first() !== null;

        $hasMovements = (new InventoryMovementModel())
            ->groupStart()
            ->where('source_warehouse_id', $id)
            ->orWhere('destination_warehouse_id', $id)
            ->groupEnd()
            ->first() !== null;

        $hasReservations = (new InventoryReservationModel())
            ->where('warehouse_id', $id)
            ->where('status', 'active')
            ->first() !== null;

        if ($hasStock || $hasMovements || $hasReservations) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'No puedes eliminar un deposito con stock o trazabilidad registrada.');
        }

        $warehouseModel->delete($id);

        return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('message', 'Deposito eliminado correctamente.');
    }

    public function createLocationForm()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para registrar ubicaciones.');
        }

        return view('inventory/forms/location', [
            'pageTitle' => 'Ubicacion interna',
            'location' => null,
            'warehouses' => $this->activeWarehouses($context['company']['id']),
            'formAction' => site_url('inventario/ubicaciones'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeLocation()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para registrar ubicaciones.');
        }

        $warehouseId = trim((string) $this->request->getPost('warehouse_id'));
        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $name = trim((string) $this->request->getPost('name'));

        if (! $this->ownedWarehouse($context['company']['id'], $warehouseId) || $code === '' || $name === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar deposito, codigo y nombre de la ubicacion.');
        }

        $model = new InventoryLocationModel();
        if ($model->where('warehouse_id', $warehouseId)->where('code', $code)->first()) {
            return redirect()->back()->withInput()->with('error', 'Ya existe una ubicacion con ese codigo en el deposito.');
        }

        $model->insert([
            'company_id' => $context['company']['id'],
            'warehouse_id' => $warehouseId,
            'name' => $name,
            'code' => $code,
            'zone' => trim((string) $this->request->getPost('zone')),
            'rack' => trim((string) $this->request->getPost('rack')),
            'level' => trim((string) $this->request->getPost('level')),
            'description' => trim((string) $this->request->getPost('description')),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $context['company']['id']), 'Ubicacion registrada correctamente.');
    }

    public function editLocationForm(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para editar ubicaciones.');
        }

        $location = $this->ownedLocation($context['company']['id'], $id);
        if (! $location) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Ubicacion no disponible.');
        }

        return view('inventory/forms/location', [
            'pageTitle' => 'Ubicacion interna',
            'location' => $location,
            'warehouses' => $this->activeWarehouses($context['company']['id']),
            'formAction' => site_url('inventario/ubicaciones/' . $id . '/actualizar'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function updateLocation(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para editar ubicaciones.');
        }

        $location = $this->ownedLocation($context['company']['id'], $id);
        if (! $location) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Ubicacion no disponible.');
        }

        $warehouseId = trim((string) $this->request->getPost('warehouse_id'));
        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $name = trim((string) $this->request->getPost('name'));

        if (! $this->ownedWarehouse($context['company']['id'], $warehouseId) || $code === '' || $name === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar deposito, codigo y nombre de la ubicacion.');
        }

        $model = new InventoryLocationModel();
        $duplicate = $model->where('warehouse_id', $warehouseId)->where('code', $code)->where('id !=', $id)->first();
        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Ya existe una ubicacion con ese codigo en el deposito.');
        }

        $model->update($id, [
            'warehouse_id' => $warehouseId,
            'name' => $name,
            'code' => $code,
            'zone' => trim((string) $this->request->getPost('zone')),
            'rack' => trim((string) $this->request->getPost('rack')),
            'level' => trim((string) $this->request->getPost('level')),
            'description' => trim((string) $this->request->getPost('description')),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $context['company']['id']), 'Ubicacion actualizada correctamente.');
    }

    public function toggleLocation(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para cambiar el estado de la ubicacion.');
        }

        $location = $this->ownedLocation($context['company']['id'], $id);
        if (! $location) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Ubicacion no disponible.');
        }

        (new InventoryLocationModel())->update($id, [
            'active' => (int) $location['active'] === 1 ? 0 : 1,
        ]);

        return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('message', 'Estado de la ubicacion actualizado correctamente.');
    }

    public function deleteLocation(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para eliminar ubicaciones.');
        }

        $location = $this->ownedLocation($context['company']['id'], $id);
        if (! $location) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Ubicacion no disponible.');
        }

        $hasStock = (new InventoryStockLevelModel())->where('location_id', $id)->where('quantity !=', 0)->first() !== null;
        $hasMovements = (new InventoryMovementModel())->groupStart()->where('source_location_id', $id)->orWhere('destination_location_id', $id)->groupEnd()->first() !== null;
        if ($hasStock || $hasMovements) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'No puedes eliminar una ubicacion con stock o trazabilidad registrada.');
        }

        (new InventoryLocationModel())->delete($id);
        return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('message', 'Ubicacion eliminada correctamente.');
    }

    public function createProductForm()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para registrar productos.');
        }

        return view('inventory/forms/product', [
            'pageTitle' => 'Producto',
            'product' => null,
            'kitItems' => [],
            'componentOptions' => $this->productComponentOptions($context['company']['id']),
            'formAction' => site_url('inventario/productos'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeProduct()
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para registrar productos.');
        }

        $productModel = new InventoryProductModel();
        $sku = strtoupper(trim((string) $this->request->getPost('sku')));

        if ($sku === '' || trim((string) $this->request->getPost('name')) === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar SKU y nombre del producto.');
        }

        if ($productModel->where('company_id', $context['company']['id'])->where('sku', $sku)->first()) {
            return redirect()->back()->withInput()->with('error', 'Ya existe un producto con ese SKU en la empresa.');
        }

        $productId = $productModel->insert([
            'company_id' => $context['company']['id'],
            'sku' => $sku,
            'name' => trim((string) $this->request->getPost('name')),
            'category' => trim((string) $this->request->getPost('category')),
            'brand' => trim((string) $this->request->getPost('brand')),
            'barcode' => trim((string) $this->request->getPost('barcode')),
            'product_type' => trim((string) $this->request->getPost('product_type')) ?: 'simple',
            'description' => trim((string) $this->request->getPost('description')),
            'unit' => trim((string) $this->request->getPost('unit')) ?: 'unidad',
            'min_stock' => (float) $this->request->getPost('min_stock'),
            'max_stock' => (float) $this->request->getPost('max_stock'),
            'cost_price' => (float) $this->request->getPost('cost_price'),
            'sale_price' => (float) $this->request->getPost('sale_price'),
            'lot_control' => $this->request->getPost('lot_control') === '1' ? 1 : 0,
            'serial_control' => $this->request->getPost('serial_control') === '1' ? 1 : 0,
            'expiration_control' => $this->request->getPost('expiration_control') === '1' ? 1 : 0,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ], true);

        $this->syncKitItems($productId, $this->requestKitItems($context['company']['id'], $productId));

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $context['company']['id']), 'Producto registrado correctamente.');
    }

    public function editProductForm(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para editar productos.');
        }

        $product = $this->ownedProduct($context['company']['id'], $id);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        return view('inventory/forms/product', [
            'pageTitle' => 'Producto',
            'product' => $product,
            'kitItems' => $this->kitItemRows($id),
            'componentOptions' => $this->productComponentOptions($context['company']['id'], $id),
            'formAction' => site_url('inventario/productos/' . $id . '/actualizar'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function updateProduct(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para editar productos.');
        }

        $productModel = new InventoryProductModel();
        $product = $this->ownedProduct($context['company']['id'], $id);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        $sku = strtoupper(trim((string) $this->request->getPost('sku')));

        if ($sku === '' || trim((string) $this->request->getPost('name')) === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar SKU y nombre del producto.');
        }

        $duplicate = $productModel
            ->where('company_id', $context['company']['id'])
            ->where('sku', $sku)
            ->where('id !=', $id)
            ->first();

        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Ya existe un producto con ese SKU en la empresa.');
        }

        $payload = [
            'sku' => $sku,
            'name' => trim((string) $this->request->getPost('name')),
            'category' => trim((string) $this->request->getPost('category')),
            'brand' => trim((string) $this->request->getPost('brand')),
            'barcode' => trim((string) $this->request->getPost('barcode')),
            'product_type' => trim((string) $this->request->getPost('product_type')) ?: 'simple',
            'description' => trim((string) $this->request->getPost('description')),
            'unit' => trim((string) $this->request->getPost('unit')) ?: 'unidad',
            'min_stock' => (float) $this->request->getPost('min_stock'),
            'max_stock' => (float) $this->request->getPost('max_stock'),
            'cost_price' => (float) $this->request->getPost('cost_price'),
            'sale_price' => (float) $this->request->getPost('sale_price'),
            'lot_control' => $this->request->getPost('lot_control') === '1' ? 1 : 0,
            'serial_control' => $this->request->getPost('serial_control') === '1' ? 1 : 0,
            'expiration_control' => $this->request->getPost('expiration_control') === '1' ? 1 : 0,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ];

        $productModel->update($id, $payload);
        $this->syncProductMinimums($context['company']['id'], $id, (float) $payload['min_stock']);
        $this->syncKitItems($id, $this->requestKitItems($context['company']['id'], $id));

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $context['company']['id']), 'Producto actualizado correctamente.');
    }

    public function toggleProduct(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para cambiar el estado del producto.');
        }

        $productModel = new InventoryProductModel();
        $product = $this->ownedProduct($context['company']['id'], $id);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        $productModel->update($id, [
            'active' => (int) $product['active'] === 1 ? 0 : 1,
        ]);

        return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('message', 'Estado del producto actualizado correctamente.');
    }

    public function deleteProduct(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->canConfigureInventory($context)) {
            return redirect()->to('/inventario')->with('error', 'No tienes permisos para eliminar productos.');
        }

        $productModel = new InventoryProductModel();
        $product = $this->ownedProduct($context['company']['id'], $id);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        $hasStock = (new InventoryStockLevelModel())
            ->where('product_id', $id)
            ->where('quantity !=', 0)
            ->first() !== null;

        $hasMovements = (new InventoryMovementModel())
            ->where('product_id', $id)
            ->first() !== null;

        $hasReservations = (new InventoryReservationModel())
            ->where('product_id', $id)
            ->where('status', 'active')
            ->first() !== null;

        if ($hasStock || $hasMovements || $hasReservations) {
            return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('error', 'No puedes eliminar un producto con stock o trazabilidad registrada.');
        }

        $productModel->delete($id);

        return redirect()->to($this->inventoryRoute('inventario/configuracion', $context['company']['id']))->with('message', 'Producto eliminado correctamente.');
    }

    public function productTraceability(string $id)
    {
        $context = $this->inventoryContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $product = $this->ownedProduct($context['company']['id'], $id);

        if (! $product) {
            return redirect()->to($this->inventoryRoute('inventario', $context['company']['id']))->with('error', 'Producto no disponible.');
        }

        return view('inventory/forms/traceability', [
            'pageTitle' => 'Trazabilidad del producto',
            'product' => $product,
            'stockByWarehouse' => $this->productWarehouseStock($context['company']['id'], $id),
            'stockByLocation' => $this->productLocationStock($context['company']['id'], $id),
            'movements' => $this->kardexRows($context['company']['id'], ['product_id' => $id], 30),
            'reservations' => $this->activeReservations($context['company']['id'], $id, 20),
            'lots' => $this->lotRows($context['company']['id'], $id),
            'serials' => $this->serialRows($context['company']['id'], $id),
            'kitItems' => $this->kitItemRows($id),
            'costLayers' => $this->costLayerRows($context['company']['id'], $id, 20),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function createMovementForm()
    {
        $context = $this->inventoryContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $defaultWarehouse = (new InventoryWarehouseModel())
            ->where('company_id', $context['company']['id'])
            ->where('active', 1)
            ->orderBy('is_default', 'DESC')
            ->orderBy('name', 'ASC')
            ->first();

        $defaults = [
            'product_id' => trim((string) $this->request->getGet('product_id')),
            'movement_type' => trim((string) $this->request->getGet('movement_type')) ?: 'ingreso',
            'quantity' => trim((string) $this->request->getGet('quantity')) ?: '1',
            'source_warehouse_id' => trim((string) $this->request->getGet('source_warehouse_id')) ?: ($defaultWarehouse['id'] ?? ''),
            'source_location_id' => trim((string) $this->request->getGet('source_location_id')),
            'destination_warehouse_id' => trim((string) $this->request->getGet('destination_warehouse_id')),
            'destination_location_id' => trim((string) $this->request->getGet('destination_location_id')),
            'adjustment_mode' => trim((string) $this->request->getGet('adjustment_mode')),
            'occurred_at' => date('Y-m-d\TH:i'),
            'reason' => trim((string) $this->request->getGet('reason')),
            'source_document' => trim((string) $this->request->getGet('source_document')),
            'unit_cost' => trim((string) $this->request->getGet('unit_cost')),
            'lot_number' => trim((string) $this->request->getGet('lot_number')),
            'serial_number' => trim((string) $this->request->getGet('serial_number')),
            'expiration_date' => trim((string) $this->request->getGet('expiration_date')),
            'notes' => trim((string) $this->request->getGet('notes')),
        ];

        return view('inventory/forms/movement', [
            'pageTitle' => 'Nuevo movimiento',
            'formAction' => site_url('inventario/movimientos'),
            'products' => $this->activeProducts($context['company']['id']),
            'warehouses' => $this->activeWarehouses($context['company']['id']),
            'locations' => $this->activeLocations($context['company']['id']),
            'companyId' => $context['company']['id'],
            'defaults' => $defaults,
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeMovement()
    {
        $context = $this->inventoryContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $productId = trim((string) $this->request->getPost('product_id'));
        $movementType = trim((string) $this->request->getPost('movement_type'));
        $quantity = (float) $this->request->getPost('quantity');
        $sourceWarehouseId = trim((string) $this->request->getPost('source_warehouse_id')) ?: null;
        $sourceLocationId = trim((string) $this->request->getPost('source_location_id')) ?: null;
        $destinationWarehouseId = trim((string) $this->request->getPost('destination_warehouse_id')) ?: null;
        $destinationLocationId = trim((string) $this->request->getPost('destination_location_id')) ?: null;
        $adjustmentMode = trim((string) $this->request->getPost('adjustment_mode')) ?: null;
        $unitCost = $this->request->getPost('unit_cost') !== null && $this->request->getPost('unit_cost') !== '' ? (float) $this->request->getPost('unit_cost') : null;
        $totalCost = $unitCost !== null ? $unitCost * $quantity : null;

        if (! in_array($movementType, ['ingreso', 'egreso', 'transferencia', 'ajuste'], true)) {
            return redirect()->back()->withInput()->with('error', 'Tipo de movimiento no valido.');
        }

        if ($quantity <= 0) {
            return redirect()->back()->withInput()->with('error', 'La cantidad debe ser mayor a cero.');
        }

        if (! $this->validCompanyProduct($companyId, $productId)) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar un producto valido.');
        }

        $warehouseModel = new InventoryWarehouseModel();
        $validWarehouse = static function (?string $warehouseId) use ($warehouseModel, $companyId): bool {
            if ($warehouseId === null) {
                return true;
            }

            return $warehouseModel->where('id', $warehouseId)->where('company_id', $companyId)->where('active', 1)->first() !== null;
        };
        $validLocation = function (?string $locationId, ?string $warehouseId) use ($companyId): bool {
            if ($locationId === null) {
                return true;
            }

            $location = (new InventoryLocationModel())
                ->where('id', $locationId)
                ->where('company_id', $companyId)
                ->where('active', 1)
                ->first();

            return $location !== null && ($warehouseId === null || (string) $location['warehouse_id'] === (string) $warehouseId);
        };

        if (! $validWarehouse($sourceWarehouseId) || ! $validWarehouse($destinationWarehouseId)) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar depositos validos de la empresa.');
        }

        if (! $validLocation($sourceLocationId, $sourceWarehouseId) || ! $validLocation($destinationLocationId, $destinationWarehouseId)) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar ubicaciones internas validas para el deposito indicado.');
        }

        if ($movementType === 'ingreso' && $destinationWarehouseId === null) {
            return redirect()->back()->withInput()->with('error', 'El ingreso requiere deposito destino.');
        }

        if ($movementType === 'egreso' && $sourceWarehouseId === null) {
            return redirect()->back()->withInput()->with('error', 'El egreso requiere deposito origen.');
        }

        if ($movementType === 'transferencia' && ($sourceWarehouseId === null || $destinationWarehouseId === null || $sourceWarehouseId === $destinationWarehouseId)) {
            return redirect()->back()->withInput()->with('error', 'La transferencia requiere deposito origen y destino diferentes.');
        }

        if ($movementType === 'ajuste' && ($sourceWarehouseId === null || ! in_array($adjustmentMode, ['increase', 'decrease'], true))) {
            return redirect()->back()->withInput()->with('error', 'El ajuste requiere deposito y modo de ajuste.');
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
            $this->applyStockDelta($companyId, $productId, $destinationWarehouseId, $quantity, $destinationLocationId);
        }

        if ($movementType === 'egreso') {
            if (! $this->canWithdraw($companyId, $productId, $sourceWarehouseId, $quantity, $allowNegative, $sourceLocationId)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', 'No hay stock suficiente en el deposito origen.');
            }

            $this->applyStockDelta($companyId, $productId, $sourceWarehouseId, $quantity * -1, $sourceLocationId);
        }

        if ($movementType === 'transferencia') {
            if (! $this->canWithdraw($companyId, $productId, $sourceWarehouseId, $quantity, $allowNegative, $sourceLocationId)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', 'No hay stock suficiente en el deposito origen para transferir.');
            }

            $this->applyStockDelta($companyId, $productId, $sourceWarehouseId, $quantity * -1, $sourceLocationId);
            $this->applyStockDelta($companyId, $productId, $destinationWarehouseId, $quantity, $destinationLocationId);
        }

        if ($movementType === 'ajuste') {
            if ($adjustmentMode === 'decrease' && ! $this->canWithdraw($companyId, $productId, $sourceWarehouseId, $quantity, $allowNegative, $sourceLocationId)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', 'No hay stock suficiente para ajustar a la baja.');
            }

            $this->applyStockDelta($companyId, $productId, $sourceWarehouseId, $adjustmentMode === 'increase' ? $quantity : $quantity * -1, $sourceLocationId);
        }

        $movementId = (new InventoryMovementModel())->insert([
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
            'performed_by' => $this->currentUser()['id'],
            'occurred_at' => trim((string) $this->request->getPost('occurred_at')) ?: date('Y-m-d H:i:s'),
            'reason' => trim((string) $this->request->getPost('reason')),
            'source_document' => trim((string) $this->request->getPost('source_document')),
            'lot_number' => trim((string) $this->request->getPost('lot_number')),
            'serial_number' => trim((string) $this->request->getPost('serial_number')),
            'expiration_date' => trim((string) $this->request->getPost('expiration_date')) ?: null,
            'notes' => trim((string) $this->request->getPost('notes')),
        ], true);

        $this->syncAdvancedInventoryArtifacts($companyId, $productId, $movementId, [
            'movement_type' => $movementType,
            'adjustment_mode' => $adjustmentMode,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'source_warehouse_id' => $sourceWarehouseId,
            'source_location_id' => $sourceLocationId,
            'destination_warehouse_id' => $destinationWarehouseId,
            'destination_location_id' => $destinationLocationId,
            'lot_number' => trim((string) $this->request->getPost('lot_number')),
            'serial_number' => trim((string) $this->request->getPost('serial_number')),
            'expiration_date' => trim((string) $this->request->getPost('expiration_date')) ?: null,
            'occurred_at' => trim((string) $this->request->getPost('occurred_at')) ?: date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar el movimiento.');
        }

        return $this->popupOrRedirect($this->inventoryRoute('inventario', $companyId), 'Movimiento registrado correctamente.');
    }

    public function createReservationForm()
    {
        $context = $this->inventoryContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('inventory/forms/reservation', [
            'pageTitle' => 'Reserva de stock',
            'products' => $this->activeProducts($context['company']['id']),
            'warehouses' => $this->activeWarehouses($context['company']['id']),
            'formAction' => site_url('inventario/reservas'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeReservation()
    {
        $context = $this->inventoryContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $productId = trim((string) $this->request->getPost('product_id'));
        $warehouseId = trim((string) $this->request->getPost('warehouse_id'));
        $quantity = (float) $this->request->getPost('quantity');

        if (! $this->validCompanyProduct($companyId, $productId) || ! $this->ownedWarehouse($companyId, $warehouseId)) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar un producto y un deposito validos.');
        }

        if ($quantity <= 0) {
            return redirect()->back()->withInput()->with('error', 'La cantidad reservada debe ser mayor a cero.');
        }

        if (! $this->canReserve($companyId, $productId, $warehouseId, $quantity)) {
            return redirect()->back()->withInput()->with('error', 'No hay stock disponible suficiente para reservar esa cantidad.');
        }

        $db = db_connect();
        $db->transStart();

        $this->applyReservedDelta($companyId, $productId, $warehouseId, $quantity);

        (new InventoryReservationModel())->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'reference' => trim((string) $this->request->getPost('reference')),
            'notes' => trim((string) $this->request->getPost('notes')),
            'status' => 'active',
            'reserved_by' => $this->currentUser()['id'],
            'reserved_at' => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar la reserva.');
        }

        return $this->popupOrRedirect($this->inventoryRoute('inventario', $companyId), 'Reserva registrada correctamente.');
    }

    public function releaseReservation(string $id)
    {
        $context = $this->inventoryContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $reservation = $this->ownedReservation($context['company']['id'], $id);

        if (! $reservation || ($reservation['status'] ?? '') !== 'active') {
            return redirect()->to($this->inventoryRoute('inventario', $context['company']['id']))->with('error', 'La reserva no esta disponible.');
        }

        $db = db_connect();
        $db->transStart();

        $this->applyReservedDelta(
            $context['company']['id'],
            (string) $reservation['product_id'],
            (string) $reservation['warehouse_id'],
            ((float) $reservation['quantity']) * -1
        );

        (new InventoryReservationModel())->update($id, [
            'status' => 'released',
            'released_by' => $this->currentUser()['id'],
            'released_at' => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($this->inventoryRoute('inventario', $context['company']['id']))->with('error', 'No se pudo liberar la reserva.');
        }

        return redirect()->to($this->inventoryRoute('inventario', $context['company']['id']))->with('message', 'Reserva liberada correctamente.');
    }

    public function createAssemblyForm()
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('inventory/forms/assembly', [
            'pageTitle' => 'Ensamble de inventario',
            'products' => $this->activeProducts($context['company']['id']),
            'warehouses' => $this->activeWarehouses($context['company']['id']),
            'formAction' => site_url('inventario/ensambles'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeAssembly()
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $productId = trim((string) $this->request->getPost('product_id'));
        $warehouseId = trim((string) $this->request->getPost('warehouse_id'));
        $assemblyType = trim((string) $this->request->getPost('assembly_type')) ?: 'assembly';
        $quantity = (float) $this->request->getPost('quantity');

        if (! in_array($assemblyType, ['assembly', 'disassembly'], true) || ! $this->ownedProduct($companyId, $productId) || ! $this->ownedWarehouse($companyId, $warehouseId) || $quantity <= 0) {
            return redirect()->back()->withInput()->with('error', 'Debes indicar producto, deposito, tipo y cantidad validos.');
        }

        $components = $this->kitItemRows($productId);
        if ($components === []) {
            return redirect()->back()->withInput()->with('error', 'El producto seleccionado no tiene componentes de kit configurados.');
        }

        $settings = $this->inventorySettings($companyId);
        $allowNegative = $this->allowsNegativeFor('assembly', $settings);
        $issuedAt = trim((string) $this->request->getPost('issued_at')) ?: date('Y-m-d H:i:s');
        $assemblyNumber = 'ENS-' . date('YmdHis');
        $db = db_connect();
        $db->transStart();

        $totalCost = 0.0;
        foreach ($components as $component) {
            $componentQty = (float) ($component['quantity'] ?? 0) * $quantity;
            if ($assemblyType === 'assembly') {
                if (! $this->canWithdraw($companyId, (string) $component['component_product_id'], $warehouseId, $componentQty, $allowNegative, null)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', 'No hay stock suficiente para consumir los componentes del ensamble.');
                }

                $this->applyStockDelta($companyId, (string) $component['component_product_id'], $warehouseId, $componentQty * -1);
                $movementId = (new InventoryMovementModel())->insert([
                    'company_id' => $companyId,
                    'product_id' => $component['component_product_id'],
                    'movement_type' => 'egreso',
                    'quantity' => $componentQty,
                    'source_warehouse_id' => $warehouseId,
                    'performed_by' => $this->currentUser()['id'],
                    'occurred_at' => $issuedAt,
                    'reason' => 'ensamble_componente',
                    'source_document' => $assemblyNumber,
                    'notes' => 'Consumo de componente por ensamble',
                ], true);

                $consumption = $this->consumeCostLayers($companyId, (string) $component['component_product_id'], $warehouseId, null, $componentQty, $issuedAt, $movementId, 'assembly_component');
                (new InventoryMovementModel())->update($movementId, [
                    'unit_cost' => $consumption['unit_cost'],
                    'total_cost' => $consumption['total_cost'],
                ]);
                $totalCost += (float) $consumption['total_cost'];
            } else {
                $componentUnitCost = (float) ($component['unit_cost'] ?? 0);
                $componentTotal = $componentUnitCost * $componentQty;
                $this->applyStockDelta($companyId, (string) $component['component_product_id'], $warehouseId, $componentQty);
                $movementId = (new InventoryMovementModel())->insert([
                    'company_id' => $companyId,
                    'product_id' => $component['component_product_id'],
                    'movement_type' => 'ingreso',
                    'quantity' => $componentQty,
                    'destination_warehouse_id' => $warehouseId,
                    'performed_by' => $this->currentUser()['id'],
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
            $this->applyStockDelta($companyId, $productId, $warehouseId, $quantity);
            $productMovementId = (new InventoryMovementModel())->insert([
                'company_id' => $companyId,
                'product_id' => $productId,
                'movement_type' => 'ingreso',
                'quantity' => $quantity,
                'destination_warehouse_id' => $warehouseId,
                'performed_by' => $this->currentUser()['id'],
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
                return redirect()->back()->withInput()->with('error', 'No hay stock suficiente del producto principal para desensamblar.');
            }
            $this->applyStockDelta($companyId, $productId, $warehouseId, $quantity * -1);
            $productMovementId = (new InventoryMovementModel())->insert([
                'company_id' => $companyId,
                'product_id' => $productId,
                'movement_type' => 'egreso',
                'quantity' => $quantity,
                'source_warehouse_id' => $warehouseId,
                'performed_by' => $this->currentUser()['id'],
                'occurred_at' => $issuedAt,
                'reason' => 'desensamble_producto',
                'source_document' => $assemblyNumber,
                'notes' => 'Egreso por desensamble',
            ], true);
            $consumption = $this->consumeCostLayers($companyId, $productId, $warehouseId, null, $quantity, $issuedAt, $productMovementId, 'disassembly_output');
            $totalCost = (float) $consumption['total_cost'];
            (new InventoryMovementModel())->update($productMovementId, [
                'unit_cost' => $consumption['unit_cost'],
                'total_cost' => $consumption['total_cost'],
            ]);
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
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
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
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar el ensamble.');
        }

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $companyId), 'Proceso de ensamble registrado correctamente.');
    }

    public function createClosureForm()
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('inventory/forms/period_closure', [
            'pageTitle' => 'Cierre de inventario',
            'warehouses' => $this->activeWarehouses($context['company']['id']),
            'formAction' => site_url('inventario/cierres'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeClosure()
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $warehouseId = trim((string) $this->request->getPost('warehouse_id')) ?: null;
        $periodCode = trim((string) $this->request->getPost('period_code'));
        $startDate = trim((string) $this->request->getPost('start_date'));
        $endDate = trim((string) $this->request->getPost('end_date'));

        if ($periodCode === '' || $startDate === '' || $endDate === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar periodo, fecha inicial y fecha final.');
        }
        if ($warehouseId && ! $this->ownedWarehouse($companyId, $warehouseId)) {
            return redirect()->back()->withInput()->with('error', 'El deposito seleccionado no existe.');
        }

        $model = new InventoryPeriodClosureModel();
        $duplicate = $model->where('company_id', $companyId)->where('period_code', $periodCode);
        $duplicate = $warehouseId ? $duplicate->where('warehouse_id', $warehouseId) : $duplicate->where('warehouse_id', null);
        if ($duplicate->first()) {
            return redirect()->back()->withInput()->with('error', 'Ya existe un cierre para ese periodo.');
        }

        $model->insert([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'period_code' => $periodCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'closed',
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ]);

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $companyId), 'Cierre de inventario registrado correctamente.');
    }

    public function createRevaluationForm()
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('inventory/forms/revaluation', [
            'pageTitle' => 'Revalorizacion',
            'products' => $this->activeProducts($context['company']['id']),
            'warehouses' => $this->activeWarehouses($context['company']['id']),
            'formAction' => site_url('inventario/revalorizaciones'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeRevaluation()
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $productId = trim((string) $this->request->getPost('product_id'));
        $warehouseId = trim((string) $this->request->getPost('warehouse_id'));
        $newUnitCost = (float) $this->request->getPost('new_unit_cost');

        if (! $this->ownedProduct($companyId, $productId) || ! $this->ownedWarehouse($companyId, $warehouseId) || $newUnitCost <= 0) {
            return redirect()->back()->withInput()->with('error', 'Debes indicar producto, deposito y nuevo costo validos.');
        }

        $stockSnapshot = (new InventoryStockLevelModel())
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $quantitySnapshot = (float) ($stockSnapshot['quantity'] ?? 0);
        if ($quantitySnapshot <= 0) {
            return redirect()->back()->withInput()->with('error', 'No hay stock disponible para revalorizar en ese deposito.');
        }

        $layerModel = new InventoryCostLayerModel();
        $layers = $layerModel
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_quantity >', 0)
            ->findAll();

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
            $layerModel->update($layer['id'], [
                'unit_cost' => $newUnitCost,
                'total_cost' => $remaining * $newUnitCost,
            ]);
        }

        (new InventoryRevaluationModel())->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'previous_unit_cost' => $previousUnitCost,
            'new_unit_cost' => $newUnitCost,
            'quantity_snapshot' => $quantitySnapshot,
            'difference_amount' => $differenceAmount,
            'issued_at' => trim((string) $this->request->getPost('issued_at')) ?: date('Y-m-d H:i:s'),
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ]);

        return $this->popupOrRedirect($this->inventoryRoute('inventario/configuracion', $companyId), 'Revalorizacion registrada correctamente.');
    }

    private function inventoryContext(string $requiredAccess = 'view')
    {
        $companyId = $this->resolveInventoryCompanyId();

        if (! $companyId) {
            return redirect()->to('/sistemas')->with('error', 'Debes seleccionar una empresa para operar Inventario.');
        }

        $company = (new CompanyModel())->find($companyId);

        if (! $company) {
            return redirect()->to('/sistemas')->with('error', 'La empresa seleccionada no existe.');
        }

        $system = (new SystemModel())->where('slug', 'inventario')->first();

        if (! $system || (int) ($system['active'] ?? 0) !== 1) {
            return redirect()->to('/sistemas')->with('error', 'El sistema Inventario no esta disponible.');
        }

        $accessLevel = 'view';
        $companyAssignment = (new CompanySystemModel())
            ->where('company_id', $companyId)
            ->where('system_id', $system['id'])
            ->where('active', 1)
            ->first();

        if (! $this->isSuperadmin()) {
            if (! $companyAssignment) {
                return redirect()->to('/sistemas')->with('error', 'La empresa no tiene Inventario asignado.');
            }

            $userAssignment = (new UserSystemModel())
                ->where('company_id', $companyId)
                ->where('user_id', $this->currentUser()['id'] ?? '')
                ->where('system_id', $system['id'])
                ->where('active', 1)
                ->first();

            if (! $userAssignment) {
                return redirect()->to('/sistemas')->with('error', 'Tu usuario no tiene acceso activo a Inventario.');
            }

            $accessLevel = $userAssignment['access_level'] ?? 'view';
        }

        if ($requiredAccess === 'manage' && ! $this->isSuperadmin() && $accessLevel !== 'manage') {
            return redirect()->to($this->inventoryRoute('inventario', $companyId))->with('error', 'Tu usuario solo tiene acceso de consulta en Inventario.');
        }

        return [
            'company' => $company,
            'system' => $system,
            'access_level' => $accessLevel,
            'canManage' => $this->isSuperadmin() || $accessLevel === 'manage',
            'canConfigure' => $this->isSuperadmin() || (($this->roleSlug() === 'admin') && $accessLevel === 'manage'),
        ];
    }

    private function canConfigureInventory(array $context): bool
    {
        return (bool) ($context['canConfigure'] ?? false);
    }

    private function resolveInventoryCompanyId(): ?string
    {
        if ($this->isSuperadmin()) {
            $companyId = trim((string) ($this->request->getGet('company_id') ?: $this->request->getPost('company_id')));

            if ($companyId !== '') {
                return $companyId;
            }

            $company = (new CompanyModel())->orderBy('name', 'ASC')->first();

            return $company['id'] ?? null;
        }

        return $this->companyId();
    }

    private function inventoryCompanies(): array
    {
        if (! $this->isSuperadmin()) {
            return [];
        }

        return (new CompanyModel())->orderBy('name', 'ASC')->findAll();
    }

    private function inventoryRoute(string $path, ?string $companyId): string
    {
        if (! $this->isSuperadmin() || ! $companyId) {
            return site_url($path);
        }

        return site_url($path . '?company_id=' . $companyId);
    }

    private function ensureInventoryDefaults(string $companyId): void
    {
        $company = (new CompanyModel())->find($companyId);
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
            $mainBranch = (new BranchModel())
                ->where('company_id', $companyId)
                ->where('code', 'MAIN')
                ->first();

            $warehouseModel->insert([
                'company_id' => $companyId,
                'branch_id' => $mainBranch['id'] ?? null,
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
        $this->ensureInventoryDefaults($companyId);

        return (new InventorySettingModel())
            ->where('company_id', $companyId)
            ->first() ?? [];
    }

    private function summaryMetrics(string $companyId): array
    {
        $stockModel = new InventoryStockLevelModel();
        $productModel = new InventoryProductModel();
        $warehouseModel = new InventoryWarehouseModel();

        $totalStock = $stockModel
            ->selectSum('quantity', 'total')
            ->where('company_id', $companyId)
            ->first();

        $todayFrom = date('Y-m-d') . ' 00:00:00';
        $todayTo = date('Y-m-d') . ' 23:59:59';

        return [
            'products' => $productModel->where('company_id', $companyId)->where('active', 1)->countAllResults(),
            'warehouses' => $warehouseModel->where('company_id', $companyId)->where('active', 1)->countAllResults(),
            'total_stock' => (float) ($totalStock['total'] ?? 0),
            'reserved_stock' => (float) (($stockModel->selectSum('reserved_quantity', 'reserved')->where('company_id', $companyId)->first()['reserved'] ?? 0)),
            'active_reservations' => (new InventoryReservationModel())->where('company_id', $companyId)->where('status', 'active')->countAllResults(),
            'critical_products' => count($this->criticalProducts($companyId, 6)),
            'sales_today' => (new SaleModel())->where('company_id', $companyId)->whereIn('status', ['confirmed', 'returned_partial', 'returned_total'])->where('issue_date >=', $todayFrom)->where('issue_date <=', $todayTo)->countAllResults(),
            'sales_kiosk_today' => (new SaleModel())->where('company_id', $companyId)->whereIn('status', ['confirmed', 'returned_partial', 'returned_total'])->where('pos_mode', 1)->where('issue_date >=', $todayFrom)->where('issue_date <=', $todayTo)->countAllResults(),
        ];
    }

    private function productStockRows(string $companyId): array
    {
        $builder = db_connect()->table('inventory_products p');
        $builder
            ->select('p.id, p.sku, p.name, p.category, p.brand, p.barcode, p.product_type, p.unit, p.min_stock, p.max_stock, p.lot_control, p.serial_control, p.expiration_control, p.active, COALESCE(SUM(s.quantity), 0) AS total_stock, COALESCE(SUM(s.reserved_quantity), 0) AS reserved_stock, COUNT(s.id) AS warehouse_count', false)
            ->join('inventory_stock_levels s', 's.product_id = p.id', 'left')
            ->where('p.company_id', $companyId)
            ->groupBy('p.id, p.sku, p.name, p.category, p.brand, p.barcode, p.product_type, p.unit, p.min_stock, p.max_stock, p.lot_control, p.serial_control, p.expiration_control, p.active')
            ->orderBy('p.name', 'ASC');

        $rows = $builder->get()->getResultArray();

        return array_map(static function (array $row): array {
            $row['total_stock'] = (float) ($row['total_stock'] ?? 0);
            $row['reserved_stock'] = (float) ($row['reserved_stock'] ?? 0);
            $row['available_stock'] = $row['total_stock'] - $row['reserved_stock'];
            $row['min_stock'] = (float) ($row['min_stock'] ?? 0);
            $row['max_stock'] = (float) ($row['max_stock'] ?? 0);
            $row['is_critical'] = $row['available_stock'] <= $row['min_stock'];
            $row['is_overstock'] = $row['max_stock'] > 0 && $row['total_stock'] > $row['max_stock'];

            return $row;
        }, $rows);
    }

    private function criticalProducts(string $companyId, int $limit = 10): array
    {
        return array_slice(array_values(array_filter(
            $this->productStockRows($companyId),
            static fn(array $row): bool => (bool) ($row['is_critical'] ?? false)
        )), 0, $limit);
    }

    private function warehouseOverview(string $companyId): array
    {
        return db_connect()->table('inventory_warehouses w')
            ->select('w.id, w.name, w.code, w.type, w.active, COALESCE(SUM(s.quantity), 0) AS total_stock, COUNT(DISTINCT s.product_id) AS product_count', false)
            ->join('inventory_stock_levels s', 's.warehouse_id = w.id', 'left')
            ->where('w.company_id', $companyId)
            ->groupBy('w.id, w.name, w.code, w.type, w.active')
            ->orderBy('w.name', 'ASC')
            ->get()
            ->getResultArray();
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

    private function recentMovements(string $companyId): array
    {
        $rows = $this->movementBaseQuery($companyId)
            ->orderBy('m.occurred_at', 'DESC')
            ->get()
            ->getResultArray();

        $latestByProduct = [];

        foreach ($rows as $row) {
            $productId = (string) ($row['product_id'] ?? '');

            if ($productId === '' || isset($latestByProduct[$productId])) {
                continue;
            }

            $latestByProduct[$productId] = $row;

            if (count($latestByProduct) >= 8) {
                break;
            }
        }

        return array_values($latestByProduct);
    }

    private function kardexRows(string $companyId, array $filters = [], int $limit = 200): array
    {
        $builder = $this->movementBaseQuery($companyId)->orderBy('m.occurred_at', 'DESC');

        $this->applyKardexFilters($builder, $filters);

        return $builder->limit($limit)->get()->getResultArray();
    }

    private function kardexProductSummaryRows(string $companyId, array $filters = []): array
    {
        $builder = db_connect()->table('inventory_movements m')
            ->select("
                p.id AS product_id,
                p.sku,
                p.name AS product_name,
                p.unit,
                MAX(m.occurred_at) AS last_movement_at,
                COUNT(m.id) AS movement_count,
                SUM(CASE WHEN m.movement_type = 'ingreso' THEN m.quantity WHEN m.movement_type = 'ajuste' AND m.adjustment_mode = 'increase' THEN m.quantity ELSE 0 END) AS total_in,
                SUM(CASE WHEN m.movement_type = 'egreso' THEN m.quantity WHEN m.movement_type = 'ajuste' AND m.adjustment_mode = 'decrease' THEN m.quantity ELSE 0 END) AS total_out,
                SUM(CASE WHEN m.movement_type = 'transferencia' THEN m.quantity ELSE 0 END) AS total_transfer
            ", false)
            ->join('inventory_products p', 'p.id = m.product_id')
            ->where('m.company_id', $companyId)
            ->groupBy('p.id, p.sku, p.name, p.unit')
            ->orderBy('p.name', 'ASC');

        $this->applyKardexFilters($builder, $filters);

        $rows = $builder->get()->getResultArray();
        $stockMap = [];
        $valuationMap = $this->productValuationMap($companyId);

        foreach ($this->productStockRows($companyId) as $product) {
            $stockMap[$product['id']] = $product;
        }

        return array_map(function (array $row) use ($stockMap, $valuationMap): array {
            $stock = $stockMap[$row['product_id']] ?? null;
            $valuation = $valuationMap[$row['product_id']] ?? ['stock_value' => 0.0, 'average_cost' => 0.0];
            $row['movement_count'] = (int) ($row['movement_count'] ?? 0);
            $row['total_in'] = (float) ($row['total_in'] ?? 0);
            $row['total_out'] = (float) ($row['total_out'] ?? 0);
            $row['total_transfer'] = (float) ($row['total_transfer'] ?? 0);
            $row['current_stock'] = (float) ($stock['total_stock'] ?? 0);
            $row['available_stock'] = (float) ($stock['available_stock'] ?? 0);
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

        $products = db_connect()->table('inventory_products p')
            ->select('p.id, p.cost_price, COALESCE(SUM(s.quantity), 0) AS total_stock', false)
            ->join('inventory_stock_levels s', 's.product_id = p.id', 'left')
            ->where('p.company_id', $companyId)
            ->groupBy('p.id, p.cost_price')
            ->get()
            ->getResultArray();

        foreach ($products as $product) {
            $productId = $product['id'];
            if (isset($map[$productId])) {
                continue;
            }

            $stock = (float) ($product['total_stock'] ?? 0);
            $cost = (float) ($product['cost_price'] ?? 0);
            $map[$productId] = [
                'stock_value' => $stock * $cost,
                'average_cost' => $stock > 0 ? $cost : 0.0,
            ];
        }

        return $map;
    }

    private function alerts(string $companyId, array $settings): array
    {
        $threshold = (float) ($settings['unusual_movement_threshold'] ?? 100);
        $rotationDays = (int) ($settings['no_rotation_days'] ?? 30);
        $rotationDate = date('Y-m-d H:i:s', strtotime('-' . $rotationDays . ' days'));

        $unusualMovements = db_connect()->table('inventory_movements m')
            ->select('m.occurred_at, m.quantity, m.movement_type, p.name AS product_name')
            ->join('inventory_products p', 'p.id = m.product_id')
            ->where('m.company_id', $companyId)
            ->where('m.quantity >=', $threshold)
            ->orderBy('m.occurred_at', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $noRotation = db_connect()->table('inventory_products p')
            ->select('p.name, p.sku, MAX(m.occurred_at) AS last_movement')
            ->join('inventory_movements m', 'm.product_id = p.id', 'left')
            ->where('p.company_id', $companyId)
            ->groupBy('p.id, p.name, p.sku')
            ->having('(MAX(m.occurred_at) IS NULL OR MAX(m.occurred_at) < ' . db_connect()->escape($rotationDate) . ')', null, false)
            ->limit(5)
            ->get()
            ->getResultArray();

        return [
            'critical' => $this->criticalProducts($companyId, 6),
            'out_of_stock' => array_values(array_filter($this->productStockRows($companyId), static fn(array $row): bool => (float) $row['total_stock'] <= 0)),
            'overstock' => array_values(array_filter($this->productStockRows($companyId), static fn(array $row): bool => (bool) ($row['is_overstock'] ?? false))),
            'unusual' => $unusualMovements,
            'no_rotation' => $noRotation,
            'reservations' => $this->activeReservations($companyId, null, 6),
        ];
    }

    private function movementBaseQuery(string $companyId): BaseBuilder
    {
        return db_connect()->table('inventory_movements m')
            ->select('m.*, p.name AS product_name, p.sku, p.category, p.brand, u.name AS user_name, source.name AS source_name, destination.name AS destination_name, sl.name AS source_location_name, dl.name AS destination_location_name')
            ->join('inventory_products p', 'p.id = m.product_id')
            ->join('users u', 'u.id = m.performed_by')
            ->join('inventory_warehouses source', 'source.id = m.source_warehouse_id', 'left')
            ->join('inventory_warehouses destination', 'destination.id = m.destination_warehouse_id', 'left')
            ->join('inventory_locations sl', 'sl.id = m.source_location_id', 'left')
            ->join('inventory_locations dl', 'dl.id = m.destination_location_id', 'left')
            ->where('m.company_id', $companyId);
    }

    private function kardexFilters(): array
    {
        return [
            'start_date' => trim((string) $this->request->getGet('start_date')),
            'end_date' => trim((string) $this->request->getGet('end_date')),
            'product_id' => trim((string) $this->request->getGet('product_id')),
            'source_warehouse_id' => trim((string) $this->request->getGet('source_warehouse_id')),
            'destination_warehouse_id' => trim((string) $this->request->getGet('destination_warehouse_id')),
            'movement_type' => trim((string) $this->request->getGet('movement_type')),
            'source_document' => trim((string) $this->request->getGet('source_document')),
            'reason' => trim((string) $this->request->getGet('reason')),
        ];
    }

    private function applyKardexFilters(BaseBuilder $builder, array $filters): void
    {
        if (! empty($filters['start_date'])) {
            $builder->where('m.occurred_at >=', $filters['start_date'] . ' 00:00:00');
        }

        if (! empty($filters['end_date'])) {
            $builder->where('m.occurred_at <=', $filters['end_date'] . ' 23:59:59');
        }

        if (! empty($filters['product_id'])) {
            $builder->where('m.product_id', $filters['product_id']);
        }

        if (! empty($filters['source_warehouse_id'])) {
            $builder->where('m.source_warehouse_id', $filters['source_warehouse_id']);
        }

        if (! empty($filters['destination_warehouse_id'])) {
            $builder->where('m.destination_warehouse_id', $filters['destination_warehouse_id']);
        }

        if (! empty($filters['movement_type'])) {
            $builder->where('m.movement_type', $filters['movement_type']);
        }

        if (! empty($filters['source_document'])) {
            $builder->like('m.source_document', $filters['source_document']);
        }

        if (! empty($filters['reason'])) {
            $builder->like('m.reason', $filters['reason']);
        }
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

    private function renderPdf(string $view, array $data, string $filename, string $orientation = 'portrait')
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data));
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    private function branchOptions(string $companyId): array
    {
        return (new BranchModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function activeProducts(string $companyId): array
    {
        return (new InventoryProductModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function activeWarehouses(string $companyId): array
    {
        return (new InventoryWarehouseModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function activeLocations(string $companyId): array
    {
        return (new InventoryLocationModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function productWarehouseStock(string $companyId, string $productId): array
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

    private function productComponentOptions(string $companyId, ?string $excludeProductId = null): array
    {
        $builder = (new InventoryProductModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('name', 'ASC');

        if ($excludeProductId) {
            $builder->where('id !=', $excludeProductId);
        }

        return $builder->findAll();
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

    private function validCompanyProduct(string $companyId, string $productId): bool
    {
        return $productId !== '' && (new InventoryProductModel())
            ->where('id', $productId)
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->first() !== null;
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

        $stockLevel = $builder->first();

        return (((float) ($stockLevel['quantity'] ?? 0)) - ((float) ($stockLevel['reserved_quantity'] ?? 0))) >= $quantity;
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

        $stockLevel = (new InventoryStockLevelModel())
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return (((float) ($stockLevel['quantity'] ?? 0)) - ((float) ($stockLevel['reserved_quantity'] ?? 0))) >= $quantity;
    }

    private function applyStockDelta(string $companyId, string $productId, ?string $warehouseId, float $delta, ?string $locationId = null): void
    {
        if ($warehouseId === null) {
            return;
        }

        $stockLevelModel = new InventoryStockLevelModel();
        $product = (new InventoryProductModel())->find($productId);

        $existing = $stockLevelModel
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->first();

        if ($existing) {
            $stockLevelModel->update($existing['id'], [
                'quantity' => ((float) $existing['quantity']) + $delta,
            ]);

            return;
        }

        $stockLevelModel->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'quantity' => $delta,
            'reserved_quantity' => 0,
            'min_stock' => $product['min_stock'] ?? 0,
        ]);
    }

    private function applyReservedDelta(string $companyId, string $productId, ?string $warehouseId, float $delta): void
    {
        if ($warehouseId === null) {
            return;
        }

        $stockLevelModel = new InventoryStockLevelModel();
        $product = (new InventoryProductModel())->find($productId);
        $existing = $stockLevelModel
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($existing) {
            $next = max(0, ((float) $existing['reserved_quantity']) + $delta);
            $stockLevelModel->update($existing['id'], [
                'reserved_quantity' => $next,
            ]);

            return;
        }

        $stockLevelModel->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'reserved_quantity' => max(0, $delta),
            'min_stock' => $product['min_stock'] ?? 0,
        ]);
    }

    private function requestKitItems(string $companyId, string $productId): array
    {
        $componentIds = (array) $this->request->getPost('component_product_id');
        $quantities = (array) $this->request->getPost('component_quantity');
        $rows = [];

        foreach ($componentIds as $index => $componentId) {
            $componentId = trim((string) $componentId);
            $quantity = (float) ($quantities[$index] ?? 0);

            if ($componentId === '' || $quantity <= 0 || $componentId === $productId) {
                continue;
            }

            if (! $this->validCompanyProduct($companyId, $componentId)) {
                continue;
            }

            $rows[] = [
                'component_product_id' => $componentId,
                'quantity' => $quantity,
            ];
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
            $balance = max(0, ((float) $lot['quantity_balance']) + $delta);
            $model->update($lot['id'], [
                'expiration_date' => $expirationDate ?: $lot['expiration_date'],
                'quantity_balance' => $balance,
                'status' => $balance > 0 ? 'active' : 'closed',
            ]);
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

    private function syncProductMinimums(string $companyId, string $productId, float $minimum): void
    {
        (new InventoryStockLevelModel())
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->set(['min_stock' => $minimum])
            ->update();
    }

    private function ownedWarehouse(string $companyId, string $warehouseId): ?array
    {
        $row = (new InventoryWarehouseModel())
            ->where('id', $warehouseId)
            ->where('company_id', $companyId)
            ->first();

        return $row ?: null;
    }

    private function ownedLocation(string $companyId, string $locationId): ?array
    {
        $row = (new InventoryLocationModel())
            ->where('id', $locationId)
            ->where('company_id', $companyId)
            ->first();

        return $row ?: null;
    }

    private function ownedProduct(string $companyId, string $productId): ?array
    {
        $row = (new InventoryProductModel())
            ->where('id', $productId)
            ->where('company_id', $companyId)
            ->first();

        return $row ?: null;
    }

    private function ownedReservation(string $companyId, string $reservationId): ?array
    {
        $row = (new InventoryReservationModel())
            ->where('id', $reservationId)
            ->where('company_id', $companyId)
            ->first();

        return $row ?: null;
    }
}
