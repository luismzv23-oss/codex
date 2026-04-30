<?php

namespace App\Controllers\Api\V1;

use App\Libraries\AccountingService;
use App\Libraries\ArcaService;
use App\Libraries\CashService;
use App\Models\AuditLogModel;
use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\CustomerModel;
use App\Models\DocumentEventModel;
use App\Models\HardwareLogModel;
use App\Models\InventoryMovementModel;
use App\Models\InventoryProductModel;
use App\Models\InventoryReservationModel;
use App\Models\InventorySettingModel;
use App\Models\InventoryStockLevelModel;
use App\Models\InventoryWarehouseModel;
use App\Models\IntegrationLogModel;
use App\Models\SaleItemModel;
use App\Models\SaleModel;
use App\Models\SalePaymentModel;
use App\Models\SaleReturnItemModel;
use App\Models\SaleReturnModel;
use App\Models\SalesAgentModel;
use App\Models\SalesCommissionModel;
use App\Models\SalesDocumentTypeModel;
use App\Models\SalesArcaEventModel;
use App\Models\SalesConditionModel;
use App\Models\SalesPointOfSaleModel;
use App\Models\PosDeviceSettingModel;
use App\Models\SalesPriceListItemModel;
use App\Models\SalesPriceListModel;
use App\Models\SalesPromotionItemModel;
use App\Models\SalesPromotionModel;
use App\Models\SalesReceiptItemModel;
use App\Models\SalesReceiptModel;
use App\Models\SalesReceivableModel;
use App\Models\SalesSettingModel;
use App\Models\SalesZoneModel;
use App\Models\SystemModel;
use App\Models\TaxModel;
use App\Models\UserSystemModel;
use App\Models\VoucherSequenceModel;

class SalesController extends BaseApiController
{
    public function index()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $filters = $this->filters();

        return $this->success([
            'company' => $context['company'],
            'access_level' => $context['access_level'],
            'summary' => $this->salesSummary($context['company']['id'], $filters),
            'sales' => $this->salesRows($context['company']['id'], $filters),
            'document_types' => $this->documentTypeOptions($context['company']['id']),
            'points_of_sale' => $this->pointOfSaleOptions($context['company']['id']),
            'receivable_summary' => $this->receivableSummary($context['company']['id']),
        ]);
    }

    public function show(string $id)
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return $this->fail('Venta no disponible.', 404);
        }

        return $this->success([
            'sale' => $sale,
            'items' => $this->saleItems($id),
            'payments' => $this->salePayments($id),
            'returns' => $this->saleReturns($id),
            'source_sale' => ! empty($sale['source_sale_id']) ? $this->ownedSale($context['company']['id'], (string) $sale['source_sale_id']) : null,
        ]);
    }

    public function customers()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success((new CustomerModel())->where('company_id', $context['company']['id'])->orderBy('name', 'ASC')->findAll());
    }

    public function storeCustomer()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Debes indicar el nombre del cliente.', 422);
        }

        $conditionId = trim((string) ($payload['sales_condition_id'] ?? '')) ?: null;
        $condition = $conditionId ? (new SalesConditionModel())->where('company_id', $context['company']['id'])->find($conditionId) : null;

        $id = (new CustomerModel())->insert([
            'company_id' => $context['company']['id'],
            'branch_id' => trim((string) ($payload['branch_id'] ?? '')) ?: null,
            'name' => $name,
            'billing_name' => trim((string) ($payload['billing_name'] ?? '')),
            'document_type' => trim((string) ($payload['document_type'] ?? '')),
            'document_number' => trim((string) ($payload['document_number'] ?? '')),
            'tax_profile' => trim((string) ($payload['tax_profile'] ?? '')),
            'vat_condition' => trim((string) ($payload['vat_condition'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'address' => trim((string) ($payload['address'] ?? '')),
            'price_list_name' => trim((string) ($payload['price_list_name'] ?? '')),
            'price_list_id' => trim((string) ($payload['price_list_id'] ?? '')) ?: null,
            'credit_limit' => $condition ? (float) ($condition['credit_limit'] ?? 0) : (float) ($payload['credit_limit'] ?? 0),
            'custom_discount_rate' => (float) ($payload['custom_discount_rate'] ?? 0),
            'payment_terms_days' => $condition ? (int) ($condition['payment_terms_days'] ?? 0) : (int) ($payload['payment_terms_days'] ?? 0),
            'sales_agent_id' => trim((string) ($payload['sales_agent_id'] ?? '')) ?: null,
            'sales_zone_id' => trim((string) ($payload['sales_zone_id'] ?? '')) ?: null,
            'sales_condition_id' => $conditionId,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        $customer = (new CustomerModel())->find($id);
        $this->logAudit($context['company']['id'], 'sales', 'customer', (string) $id, 'create', null, $customer);
        return $this->success($customer, 201);
    }

    public function priceLists()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->priceListOptions($context['company']['id']));
    }

    public function storePriceList()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Debes indicar el nombre de la lista.', 422);
        }

        $model = new SalesPriceListModel();
        $isDefault = ! empty($payload['is_default']) ? 1 : 0;
        if ($isDefault === 1) {
            $model->where('company_id', $context['company']['id'])->set(['is_default' => 0])->update();
        }

        $id = $model->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'description' => trim((string) ($payload['description'] ?? '')),
            'is_default' => $isDefault,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        foreach ((array) ($payload['items'] ?? []) as $item) {
            $productId = trim((string) ($item['product_id'] ?? ''));
            $price = (float) ($item['price'] ?? 0);
            if ($productId === '' || $price <= 0) {
                continue;
            }
            (new SalesPriceListItemModel())->insert(['price_list_id' => $id, 'product_id' => $productId, 'price' => $price]);
        }

        return $this->success($model->find($id), 201);
    }

    public function promotions()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->activePromotions($context['company']['id']));
    }

    public function storePromotion()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Debes indicar el nombre de la promocion.', 422);
        }

        $id = (new SalesPromotionModel())->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'description' => trim((string) ($payload['description'] ?? '')),
            'promotion_type' => trim((string) ($payload['promotion_type'] ?? 'percent')) ?: 'percent',
            'scope' => trim((string) ($payload['scope'] ?? 'selected')) ?: 'selected',
            'value' => (float) ($payload['value'] ?? 0),
            'start_date' => trim((string) ($payload['start_date'] ?? '')) ?: null,
            'end_date' => trim((string) ($payload['end_date'] ?? '')) ?: null,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        if (($payload['scope'] ?? 'selected') === 'selected') {
            foreach ((array) ($payload['product_ids'] ?? []) as $productId) {
                $productId = trim((string) $productId);
                if ($productId === '') {
                    continue;
                }
                (new SalesPromotionItemModel())->insert(['promotion_id' => $id, 'product_id' => $productId]);
            }
        }

        return $this->success((new SalesPromotionModel())->find($id), 201);
    }

    public function posCatalog()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success([
            'customers' => $this->customerOptions($context['company']['id']),
            'warehouses' => $this->salesWarehouses($context['company']['id']),
            'products' => $this->salesProductCatalog($context['company']['id']),
            'taxes' => $this->taxOptions($context['company']['id']),
            'price_lists' => $this->priceListOptions($context['company']['id']),
            'promotions' => $this->activePromotions($context['company']['id']),
            'document_types' => $this->documentTypeOptions($context['company']['id'], 'standard'),
            'points_of_sale' => $this->pointOfSaleOptions($context['company']['id'], 'standard'),
        ]);
    }

    public function reports()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->salesReportData($context['company']['id'], $this->filters()));
    }

    public function reportsExport()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $report = $this->salesReportData($context['company']['id'], $this->filters());
        $rows = [[
            'Fecha',
            'Comprobante',
            'Cliente',
            'Estado',
            'Vendedor',
            'Zona',
            'Total',
            'Cobrado',
            'Margen',
        ]];

        foreach (($report['sales'] ?? []) as $sale) {
            $rows[] = [
                (string) (! empty($sale['issue_date']) ? date('d/m/Y H:i', strtotime($sale['issue_date'])) : ''),
                (string) ($sale['sale_number'] ?? ''),
                (string) ($sale['customer_name'] ?? $sale['customer_name_snapshot'] ?? 'Consumidor Final'),
                (string) ($sale['status'] ?? ''),
                (string) ($sale['sales_agent_name'] ?? 'Sin vendedor'),
                (string) ($sale['sales_zone_name'] ?? 'Sin zona'),
                number_format((float) ($sale['total'] ?? 0), 2, '.', ''),
                number_format((float) ($sale['paid_total'] ?? 0), 2, '.', ''),
                number_format((float) ($sale['margin_total'] ?? 0), 2, '.', ''),
            ];
        }

        return $this->csvResponse($rows, 'ventas-reportes-' . date('Ymd-His') . '.csv');
    }

    public function receivables()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success([
            'summary' => $this->receivableSummary($context['company']['id']),
            'receivables' => $this->receivableRows($context['company']['id']),
        ]);
    }

    public function receipts()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->receiptRows($context['company']['id']));
    }

    public function commissions()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success(
            db_connect()->table('sales_commissions sc')
                ->select('sc.*, sa.name AS sales_agent_name, s.sale_number')
                ->join('sales_agents sa', 'sa.id = sc.sales_agent_id', 'left')
                ->join('sales s', 's.id = sc.sale_id', 'left')
                ->where('sc.company_id', $context['company']['id'])
                ->orderBy('sc.created_at', 'DESC')
                ->get()
                ->getResultArray()
        );
    }

    public function storeReceipt()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $companyId = $context['company']['id'];
        $payload = $this->payload();
        $rows = $this->receiptApplicationsPayloadFromApi($companyId, (array) ($payload['items'] ?? []));
        if ($rows === []) {
            return $this->fail('Debes aplicar al menos una cobranza a un comprobante pendiente.', 422);
        }

        $customerIds = array_values(array_unique(array_map(static fn(array $row): string => (string) $row['customer_id'], $rows)));
        if (count($customerIds) !== 1) {
            return $this->fail('El recibo solo puede aplicarse a comprobantes del mismo cliente.', 422);
        }

        $cashSession = (new CashService())->activeSessionForChannel($companyId, 'general');
        if (! $cashSession) {
            return $this->fail('Debes abrir una caja activa para registrar la cobranza.', 422);
        }

        $total = round(array_sum(array_map(static fn(array $row): float => (float) $row['applied_amount'], $rows)), 2);
        $issueDate = trim((string) ($payload['issue_date'] ?? '')) ?: date('Y-m-d H:i:s');
        $paymentMethod = trim((string) ($payload['payment_method'] ?? 'cash')) ?: 'cash';
        $receiptNumber = $this->nextSequenceNumber($companyId, 'RECIBO', 'REC');

        $db = db_connect();
        $db->transStart();

        $receiptId = (new SalesReceiptModel())->insert([
            'company_id' => $companyId,
            'customer_id' => $customerIds[0],
            'cash_register_id' => $cashSession['cash_register_id'],
            'cash_session_id' => $cashSession['id'],
            'receipt_number' => $receiptNumber,
            'issue_date' => $issueDate,
            'currency_code' => $context['company']['currency_code'] ?? 'ARS',
            'payment_method' => $paymentMethod,
            'total_amount' => $total,
            'reference' => trim((string) ($payload['reference'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        $receiptItemModel = new SalesReceiptItemModel();
        $salePaymentModel = new SalePaymentModel();
        $receivableModel = new SalesReceivableModel();

        foreach ($rows as $row) {
            $receiptItemModel->insert([
                'sales_receipt_id' => $receiptId,
                'sales_receivable_id' => $row['receivable_id'],
                'sale_id' => $row['sale_id'],
                'document_number' => $row['document_number'],
                'applied_amount' => $row['applied_amount'],
            ]);

            $salePaymentModel->insert([
                'sale_id' => $row['sale_id'],
                'payment_method' => $paymentMethod,
                'amount' => $row['applied_amount'],
                'reference' => $receiptNumber,
                'status' => 'registered',
                'paid_at' => $issueDate,
                'notes' => 'Cobranza aplicada desde recibo',
            ]);

            $receivable = $receivableModel->find($row['receivable_id']);
            if ($receivable) {
                $paidAmount = min((float) ($receivable['total_amount'] ?? 0), (float) ($receivable['paid_amount'] ?? 0) + (float) $row['applied_amount']);
                $balance = max(0, (float) ($receivable['total_amount'] ?? 0) - $paidAmount);
                $receivableModel->update($row['receivable_id'], [
                    'paid_amount' => $paidAmount,
                    'balance_amount' => $balance,
                    'status' => $balance <= 0 ? 'paid' : 'partial',
                ]);
            }

            $this->refreshSalePaymentStatus($row['sale_id']);
            $this->syncReceivableForSale($row['sale_id']);
        }

        (new CashService())->registerMovement([
            'company_id' => $companyId,
            'cash_register_id' => $cashSession['cash_register_id'],
            'cash_session_id' => $cashSession['id'],
            'movement_type' => 'customer_receipt',
            'payment_method' => $paymentMethod,
            'amount' => $total,
            'reference_type' => 'sales_receipt',
            'reference_id' => $receiptId,
            'reference_number' => $receiptNumber,
            'occurred_at' => $issueDate,
            'notes' => 'Cobranza de cuenta corriente',
            'created_by' => $this->apiUser()['id'],
        ]);

        (new AccountingService())->syncSalesReceipt($companyId, (string) $receiptId, $this->apiUser()['id']);

        $db->transComplete();
        return $db->transStatus() ? $this->success((new SalesReceiptModel())->find($receiptId), 201) : $this->fail('No se pudo registrar el recibo.', 500);
    }

    public function settings()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success([
            'settings' => $this->salesSettings($context['company']['id']),
            'currencies' => $this->companyCurrencyOptions($context['company']['id'], $context['company']['currency_code'] ?? null),
            'document_types' => $this->documentTypeOptions($context['company']['id']),
            'points_of_sale' => $this->pointOfSaleOptions($context['company']['id']),
            'receivable_summary' => $this->receivableSummary($context['company']['id']),
            'device_settings' => (new PosDeviceSettingModel())->where('company_id', $context['company']['id'])->orderBy('channel', 'ASC')->orderBy('device_type', 'ASC')->findAll(),
            'hardware_logs' => (new HardwareLogModel())->where('company_id', $context['company']['id'])->orderBy('created_at', 'DESC')->findAll(20),
            'arca_readiness' => (new ArcaService())->readiness($this->salesSettings($context['company']['id'])),
            'arca_diagnostics' => (new ArcaService())->certificateDiagnostics($this->salesSettings($context['company']['id'])),
            'arca_environments' => (new ArcaService())->environmentDiagnostics($this->salesSettings($context['company']['id'])),
        ]);
    }

    public function devices()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success((new PosDeviceSettingModel())->where('company_id', $context['company']['id'])->orderBy('channel', 'ASC')->orderBy('device_type', 'ASC')->findAll());
    }

    public function storeDevice()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $deviceName = trim((string) ($payload['device_name'] ?? ''));
        $deviceCode = trim((string) ($payload['device_code'] ?? ''));
        if ($deviceName === '' || $deviceCode === '') {
            return $this->fail('Debes indicar nombre y codigo del dispositivo.', 422);
        }

        $id = (new PosDeviceSettingModel())->insert([
            'company_id' => $context['company']['id'],
            'channel' => trim((string) ($payload['channel'] ?? 'standard')) ?: 'standard',
            'device_type' => trim((string) ($payload['device_type'] ?? 'printer')) ?: 'printer',
            'device_name' => $deviceName,
            'device_code' => $deviceCode,
            'settings_json' => json_encode([
                'paper_width' => trim((string) ($payload['paper_width'] ?? '80mm')) ?: '80mm',
                'driver' => trim((string) ($payload['driver'] ?? 'browser')) ?: 'browser',
                'endpoint' => trim((string) ($payload['endpoint'] ?? '')),
            ], JSON_UNESCAPED_UNICODE),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success((new PosDeviceSettingModel())->find($id), 201);
    }

    public function arcaDiagnostics()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $settings = $this->salesSettings($context['company']['id']);
        $service = new ArcaService();
        $diagnostics = $service->certificateDiagnostics($settings);
        $readiness = $service->readiness($settings);

        (new SalesSettingModel())->update($settings['id'], [
            'arca_certificate_expires_at' => $diagnostics['metadata']['valid_to'] ?? ($settings['arca_certificate_expires_at'] ?? null),
            'arca_last_error' => ($diagnostics['bundle_valid'] ?? false) ? null : ($diagnostics['summary'] ?? 'Bundle fiscal invalido'),
            'arca_last_sync_at' => date('Y-m-d H:i:s'),
        ]);

        $this->recordArcaEvent($context['company']['id'], null, 'local_bundle', 'diagnostics', [
            'status' => ($diagnostics['bundle_valid'] ?? false) ? 'ok' : 'error',
            'result_code' => ($diagnostics['bundle_valid'] ?? false) ? 'CERT_OK' : 'CERT_INVALID',
            'message' => $diagnostics['summary'] ?? 'Diagnostico local',
            'environment' => $settings['arca_environment'] ?? 'homologacion',
            'response_payload' => $diagnostics,
        ], ['readiness' => $readiness]);

        $this->logIntegration($context['company']['id'], 'arca', 'certificate_bundle', 'settings', (string) $settings['id'], ($diagnostics['bundle_valid'] ?? false) ? 'ok' : 'error', ['settings_id' => $settings['id']], $diagnostics, $diagnostics['summary'] ?? null);

        return ($diagnostics['bundle_valid'] ?? false) ? $this->success($diagnostics) : $this->fail($diagnostics['summary'] ?? 'Bundle fiscal invalido.', 422, $diagnostics);
    }

    public function arcaStatus()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $settings = $this->salesSettings($context['company']['id']);
        return $this->success([
            'settings' => $settings,
            'services' => (new ArcaService())->statusSummary($settings),
            'readiness' => (new ArcaService())->readiness($settings),
        ]);
    }

    public function arcaEvents()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->arcaEventRows($context['company']['id']));
    }

    public function documentTypes()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->documentTypeOptions($context['company']['id'], trim((string) $this->request->getGet('channel')) ?: null));
    }

    public function pointsOfSale()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->pointOfSaleOptions($context['company']['id'], trim((string) $this->request->getGet('channel')) ?: null));
    }

    public function agents()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->salesAgentOptions($context['company']['id']));
    }

    public function storeAgent()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        if ($name === '' || $code === '') {
            return $this->fail('Debes indicar nombre y codigo del vendedor.', 422);
        }

        $id = (new SalesAgentModel())->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'code' => $code,
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'commission_rate' => (float) ($payload['commission_rate'] ?? 0),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        $agent = (new SalesAgentModel())->find($id);
        $this->logAudit($context['company']['id'], 'sales', 'sales_agent', (string) $id, 'create', null, $agent);
        return $this->success($agent, 201);
    }

    public function zones()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->salesZoneOptions($context['company']['id']));
    }

    public function storeZone()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        if ($name === '' || $code === '') {
            return $this->fail('Debes indicar nombre y codigo de la zona.', 422);
        }

        $id = (new SalesZoneModel())->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'code' => $code,
            'region' => trim((string) ($payload['region'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        $zone = (new SalesZoneModel())->find($id);
        $this->logAudit($context['company']['id'], 'sales', 'sales_zone', (string) $id, 'create', null, $zone);
        return $this->success($zone, 201);
    }

    public function conditions()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->salesConditionOptions($context['company']['id']));
    }

    public function storeCondition()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        if ($name === '' || $code === '') {
            return $this->fail('Debes indicar nombre y codigo de la condicion.', 422);
        }

        $id = (new SalesConditionModel())->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'code' => $code,
            'credit_limit' => (float) ($payload['credit_limit'] ?? 0),
            'payment_terms_days' => (int) ($payload['payment_terms_days'] ?? 0),
            'discount_rate' => (float) ($payload['discount_rate'] ?? 0),
            'requires_authorization' => ! empty($payload['requires_authorization']) ? 1 : 0,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        $condition = (new SalesConditionModel())->find($id);
        $this->logAudit($context['company']['id'], 'sales', 'sales_condition', (string) $id, 'create', null, $condition);
        return $this->success($condition, 201);
    }

    public function audit()
    {
        $context = $this->salesContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $rows = db_connect()->table('audit_logs al')
            ->select('al.*, u.name AS user_name')
            ->join('users u', 'u.id = al.user_id', 'left')
            ->where('al.company_id', $context['company']['id'])
            ->where('al.module', 'sales')
            ->orderBy('al.created_at', 'DESC')
            ->limit((int) ($this->request->getGet('limit') ?: 50))
            ->get()
            ->getResultArray();

        return $this->success($rows);
    }

    public function updateSettings()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $settings = $this->salesSettings($context['company']['id']);
        $payload = $this->payload();
        $arcaService = new ArcaService();
        $currencyCode = trim((string) ($payload['default_currency_code'] ?? ''));
        if ($currencyCode !== '' && ! array_key_exists($currencyCode, $this->companyCurrencyOptions($context['company']['id'], $settings['default_currency_code'] ?? null))) {
            return $this->fail('La moneda por defecto de Ventas debe pertenecer a las monedas activas de la empresa.', 422);
        }

        $validation = $arcaService->validateSettings([
            'arca_enabled' => array_key_exists('arca_enabled', $payload) ? (int) $payload['arca_enabled'] : ($settings['arca_enabled'] ?? 0),
            'arca_cuit' => trim((string) ($payload['arca_cuit'] ?? $settings['arca_cuit'] ?? '')),
            'certificate_path' => trim((string) ($payload['certificate_path'] ?? $settings['certificate_path'] ?? '')),
            'private_key_path' => trim((string) ($payload['private_key_path'] ?? $settings['private_key_path'] ?? '')),
            'token_cache_path' => trim((string) ($payload['token_cache_path'] ?? $settings['token_cache_path'] ?? '')),
        ], $context['company']['id']);
        if (! $validation['valid']) {
            return $this->fail(implode(' ', $validation['errors']), 422, $validation);
        }
        $normalized = $validation['settings'];

        (new SalesSettingModel())->update($settings['id'], [
            'invoice_mode_standard_enabled' => array_key_exists('invoice_mode_standard_enabled', $payload) ? (int) $payload['invoice_mode_standard_enabled'] : $settings['invoice_mode_standard_enabled'],
            'invoice_mode_kiosk_enabled' => array_key_exists('invoice_mode_kiosk_enabled', $payload) ? (int) $payload['invoice_mode_kiosk_enabled'] : $settings['invoice_mode_kiosk_enabled'],
            'default_currency_code' => $currencyCode ?: null,
            'allow_negative_stock_sales' => array_key_exists('allow_negative_stock_sales', $payload) ? (int) $payload['allow_negative_stock_sales'] : $settings['allow_negative_stock_sales'],
            'strict_company_currencies' => array_key_exists('strict_company_currencies', $payload) ? (int) $payload['strict_company_currencies'] : $settings['strict_company_currencies'],
            'arca_enabled' => array_key_exists('arca_enabled', $payload) ? (int) $payload['arca_enabled'] : $settings['arca_enabled'],
            'arca_environment' => trim((string) ($payload['arca_environment'] ?? $settings['arca_environment'] ?? 'homologacion')) ?: 'homologacion',
            'arca_cuit' => trim((string) ($payload['arca_cuit'] ?? $settings['arca_cuit'] ?? '')),
            'arca_iva_condition' => trim((string) ($payload['arca_iva_condition'] ?? $settings['arca_iva_condition'] ?? '')),
            'arca_iibb' => trim((string) ($payload['arca_iibb'] ?? $settings['arca_iibb'] ?? '')),
            'arca_start_activities' => trim((string) ($payload['arca_start_activities'] ?? $settings['arca_start_activities'] ?? '')) ?: null,
            'arca_alias' => trim((string) ($payload['arca_alias'] ?? $settings['arca_alias'] ?? '')) ?: null,
            'arca_auto_authorize' => array_key_exists('arca_auto_authorize', $payload) ? (int) $payload['arca_auto_authorize'] : ($settings['arca_auto_authorize'] ?? 0),
            'arca_certificate_expires_at' => str_replace('T', ' ', trim((string) ($payload['arca_certificate_expires_at'] ?? $settings['arca_certificate_expires_at'] ?? ''))) ?: null,
            'point_of_sale_standard' => max(1, (int) ($payload['point_of_sale_standard'] ?? $settings['point_of_sale_standard'])),
            'point_of_sale_kiosk' => max(1, (int) ($payload['point_of_sale_kiosk'] ?? $settings['point_of_sale_kiosk'])),
            'kiosk_document_label' => trim((string) ($payload['kiosk_document_label'] ?? $settings['kiosk_document_label'] ?? 'Ticket Consumidor Final')),
            'wsaa_enabled' => array_key_exists('wsaa_enabled', $payload) ? (int) $payload['wsaa_enabled'] : $settings['wsaa_enabled'],
            'wsfev1_enabled' => array_key_exists('wsfev1_enabled', $payload) ? (int) $payload['wsfev1_enabled'] : $settings['wsfev1_enabled'],
            'wsmtxca_enabled' => array_key_exists('wsmtxca_enabled', $payload) ? (int) $payload['wsmtxca_enabled'] : $settings['wsmtxca_enabled'],
            'wsfexv1_enabled' => array_key_exists('wsfexv1_enabled', $payload) ? (int) $payload['wsfexv1_enabled'] : $settings['wsfexv1_enabled'],
            'wsbfev1_enabled' => array_key_exists('wsbfev1_enabled', $payload) ? (int) $payload['wsbfev1_enabled'] : $settings['wsbfev1_enabled'],
            'wsct_enabled' => array_key_exists('wsct_enabled', $payload) ? (int) $payload['wsct_enabled'] : $settings['wsct_enabled'],
            'wsseg_enabled' => array_key_exists('wsseg_enabled', $payload) ? (int) $payload['wsseg_enabled'] : $settings['wsseg_enabled'],
            'certificate_path' => $normalized['certificate_path'] ?? '',
            'private_key_path' => $normalized['private_key_path'] ?? '',
            'token_cache_path' => $normalized['token_cache_path'] ?? '',
        ]);

        return $this->success($this->salesSettings($context['company']['id']));
    }

    public function testArcaConnection()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $settings = $this->salesSettings($context['company']['id']);
        $result = (new ArcaService())->testAuthentication($settings);
        $this->recordArcaEvent($context['company']['id'], null, $result['service_slug'] ?? 'wsaa', 'test_auth', $result, ['settings' => $settings]);

        (new SalesSettingModel())->update($settings['id'], [
            'arca_last_wsaa_at' => ($result['status'] ?? '') === 'ok' ? date('Y-m-d H:i:s') : ($settings['arca_last_wsaa_at'] ?? null),
            'arca_last_ticket_expires_at' => $result['ticket_expires_at'] ?? ($settings['arca_last_ticket_expires_at'] ?? null),
            'arca_last_sync_at' => date('Y-m-d H:i:s'),
            'arca_last_error' => ($result['status'] ?? '') === 'ok' ? null : ($result['message'] ?? 'Error ARCA'),
        ]);

        $this->logIntegration($context['company']['id'], 'arca', 'wsaa', 'settings', (string) $settings['id'], ($result['status'] ?? '') === 'ok' ? 'ok' : 'error', ['settings_id' => $settings['id']], $result, $result['message'] ?? null);

        return ($result['status'] ?? '') === 'ok' ? $this->success($result) : $this->fail($result['message'] ?? 'No se pudo validar ARCA.', 422);
    }

    public function store()
    {
        return $this->createSale(false);
    }

    public function storePos()
    {
        return $this->createSale(true, null, 'pos');
    }

    public function storeKiosk()
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $consumer = (new CustomerModel())->where('company_id', $context['company']['id'])->where('name', 'Consumidor Final')->first();
        $payload['customer_id'] = $consumer['id'] ?? null;
        $payload['pos_mode'] = 1;
        return $this->createSale(true, $payload, 'kiosk');
    }

    public function convert(string $id, string $targetCode)
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $sourceSale = $this->ownedSale($context['company']['id'], $id);
        if (! $sourceSale) {
            return $this->fail('Documento origen no disponible.', 404);
        }

        $targetDocument = (new SalesDocumentTypeModel())
            ->where('company_id', $context['company']['id'])
            ->where('code', strtoupper(trim($targetCode)))
            ->where('active', 1)
            ->first();

        if (! $targetDocument) {
            return $this->fail('El comprobante destino no esta disponible.', 404);
        }

        if (! $this->canConvertDocument($sourceSale, $targetDocument)) {
            return $this->fail('La conversion solicitada no es valida para este documento.', 422);
        }

        $newId = $this->createDraftFromSource($context['company']['id'], $sourceSale, $targetDocument);
        return $this->success([
            'sale' => (new SaleModel())->find($newId),
            'items' => $this->saleItems($newId),
            'payments' => $this->salePayments($newId),
        ], 201);
    }

    public function confirm(string $id)
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $result = $this->confirmSaleTransaction($context['company']['id'], $id);
        if ($result !== true) {
            return $this->fail($result, 422);
        }

        $this->processArcaAfterConfirmation($context['company']['id'], $id);
        $this->logAudit($context['company']['id'], 'sales', 'sale', $id, 'confirm', null, (new SaleModel())->find($id));
        $this->logDocumentEvent($context['company']['id'], 'sales', 'sale', $id, 'confirmed', ['sale_id' => $id]);

        return $this->success((new SaleModel())->find($id));
    }

    public function authorizeArca(string $id)
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return $this->fail('Comprobante no disponible.', 404);
        }

        $result = $this->authorizeSaleInArca($context['company']['id'], $sale);
        return in_array(($result['status'] ?? ''), ['Authorizado', 'No Aplica'], true)
            ? $this->success($result)
            : $this->fail($result['message'] ?? 'No se pudo autorizar el comprobante.', 422);
    }

    public function consultArca(string $id)
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return $this->fail('Comprobante no disponible.', 404);
        }

        return $this->success($this->consultSaleInArca($context['company']['id'], $sale));
    }

    public function cancel(string $id)
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return $this->fail('Venta no disponible.', 404);
        }

        if (in_array($sale['status'], ['cancelled', 'returned_total'], true)) {
            return $this->fail('La venta ya no puede cancelarse.', 422);
        }

        $payload = $this->payload();
        $db = db_connect();
        $db->transStart();

        if (($sale['status'] ?? '') === 'confirmed') {
            $documentType = ! empty($sale['document_type_id']) ? (new SalesDocumentTypeModel())->find($sale['document_type_id']) : null;
            $category = (string) ($documentType['category'] ?? 'invoice');
            $items = $this->saleItems($id);

            if ($category === 'order' && ($sale['reservation_status'] ?? 'none') === 'active') {
                $this->releaseReservationsForSale($context['company']['id'], $sale, $items, 'cancelled');
            } elseif (in_array($category, ['delivery_note', 'invoice', 'ticket'], true) && (int) ($documentType['impacts_stock'] ?? 0) === 1) {
                $this->restockDeliveredSale($context['company']['id'], $sale, $items, 'ANULACION VENTA');
            }
        }

        (new SaleModel())->update($id, [
            'status' => 'cancelled',
            'cancelled_by' => $this->apiUser()['id'],
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancellation_reason' => trim((string) ($payload['cancellation_reason'] ?? '')) ?: 'Cancelacion manual',
        ]);
        $db->transComplete();
        $this->syncReceivableForSale($id);
        $this->syncSaleCommission($context['company']['id'], $id);
        (new AccountingService())->syncSale($context['company']['id'], $id, $this->apiUser()['id']);
        if ($db->transStatus()) {
            $this->logAudit($context['company']['id'], 'sales', 'sale', $id, 'cancel', $sale, (new SaleModel())->find($id));
            $this->logDocumentEvent($context['company']['id'], 'sales', 'sale', $id, 'cancelled', ['sale_id' => $id]);
        }

        return $db->transStatus() ? $this->success((new SaleModel())->find($id)) : $this->fail('No se pudo cancelar la venta.', 500);
    }

    public function storeReturn(string $id)
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale || ! in_array($sale['status'], ['confirmed', 'returned_partial'], true)) {
            return $this->fail('La venta no admite devoluciones.', 422);
        }

        $payload = $this->payload();
        $warehouseId = trim((string) ($payload['warehouse_id'] ?? '')) ?: ($sale['warehouse_id'] ?: null);
        if (! $warehouseId) {
            return $this->fail('Debes seleccionar un deposito valido para reingresar stock.', 422);
        }

        $items = $this->parseReturnItems($id, (array) ($payload['items'] ?? []));
        if ($items === []) {
            return $this->fail('Debes indicar al menos una cantidad a devolver.', 422);
        }

        $db = db_connect();
        $db->transStart();
        $returnNumber = $this->nextSequenceNumber($context['company']['id'], 'NC', 'NC');
        $returnId = (new SaleReturnModel())->insert([
            'sale_id' => $id,
            'warehouse_id' => $warehouseId,
            'return_number' => $returnNumber,
            'status' => 'confirmed',
            'credit_note_number' => $returnNumber,
            'total' => 0,
            'reason' => trim((string) ($payload['reason'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        $total = 0.0;
        foreach ($items as $item) {
            $line = (float) $item['unit_price'] * (float) $item['quantity'];
            $total += $line;
            (new SaleReturnItemModel())->insert([
                'sale_return_id' => $returnId,
                'sale_item_id' => $item['sale_item_id'],
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $line,
                'reason' => $item['reason'],
            ]);
            (new SaleItemModel())->update($item['sale_item_id'], ['returned_quantity' => (float) $item['already_returned'] + (float) $item['quantity']]);
            $this->applyStockDelta($context['company']['id'], $item['product_id'], $warehouseId, (float) $item['quantity']);
        }

        (new SaleReturnModel())->update($returnId, ['total' => $total]);
        $this->syncSaleReturnStatus($id);
        $this->syncReceivableForSale($id);
        $this->syncSaleCommission($context['company']['id'], $id);
        (new AccountingService())->syncSaleReturn($context['company']['id'], (string) $returnId, $this->apiUser()['id']);
        $db->transComplete();
        if ($db->transStatus()) {
            $this->logAudit($context['company']['id'], 'sales', 'sale_return', (string) $returnId, 'create', null, (new SaleReturnModel())->find($returnId));
            $this->logDocumentEvent($context['company']['id'], 'sales', 'sale_return', (string) $returnId, 'registered', ['sale_id' => $id]);
        }

        return $db->transStatus() ? $this->success((new SaleReturnModel())->find($returnId), 201) : $this->fail('No se pudo registrar la devolucion.', 500);
    }

    private function createSale(bool $posMode, ?array $overridePayload = null, string $cashChannel = 'general')
    {
        $context = $this->salesContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        if ($overridePayload !== null) {
            $this->request->setGlobal('post', $overridePayload);
        }

        $payload = $this->salePayload($context['company']['id'], $overridePayload ?? []);
        if (isset($payload['error'])) {
            return $this->fail($payload['error'], 422);
        }

        $cashSession = null;
        if ($posMode) {
            $cashSession = $this->resolveCashSession($context['company']['id'], $cashChannel);
            if (! $cashSession) {
                return $this->fail('Debes abrir una caja activa para operar este canal de venta.', 422);
            }
        }

        $id = (new SaleModel())->insert(array_merge($payload['sale'], [
            'company_id' => $context['company']['id'],
            'branch_id' => $this->apiUser()['branch_id'] ?? null,
            'cash_register_id' => $cashSession['cash_register_id'] ?? null,
            'cash_session_id' => $cashSession['id'] ?? null,
            'sale_number' => $this->nextSequenceNumber($context['company']['id'], $payload['documentType']['sequence_key'], $payload['documentType']['default_prefix'] ?: 'DOC'),
            'document_code' => $payload['documentType']['code'],
            'created_by' => $this->apiUser()['id'],
            'pos_mode' => $posMode ? 1 : 0,
        ]), true);

        $this->persistSaleChildren($id, $payload['items'], $payload['payments']);
        $this->logAudit($context['company']['id'], 'sales', 'sale', (string) $id, 'create_draft', null, (new SaleModel())->find($id));
        $this->logDocumentEvent($context['company']['id'], 'sales', 'sale', (string) $id, 'draft_created', ['sale_number' => (new SaleModel())->find($id)['sale_number'] ?? null]);

        if ($posMode) {
            $result = $this->confirmSaleTransaction($context['company']['id'], $id);
            if ($result !== true) {
                return $this->fail($result, 422);
            }
            $this->processArcaAfterConfirmation($context['company']['id'], $id);
            (new HardwareLogModel())->insert([
                'company_id' => $context['company']['id'],
                'channel' => $cashChannel,
                'device_type' => 'printer',
                'event_type' => 'sale_confirmed',
                'status' => 'ok',
                'reference_type' => 'sale',
                'reference_id' => $id,
                'payload_json' => json_encode([
                    'sale_number' => (new SaleModel())->find($id)['sale_number'] ?? null,
                    'cash_session_id' => $cashSession['id'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'message' => 'Venta confirmada en canal de mostrador.',
            ]);
        }

        return $this->success((new SaleModel())->find($id), 201);
    }

    private function salesContext(string $requiredAccess = 'view'): array
    {
        $payload = $this->payload();
        $companyId = trim((string) ($this->request->getGet('company_id') ?: ($payload['company_id'] ?? $this->apiCompanyId())));
        if ($companyId === '') {
            return ['error' => 'Debes seleccionar una empresa para operar Ventas.', 'status' => 422];
        }

        $company = (new CompanyModel())->find($companyId);
        if (! $company) {
            return ['error' => 'La empresa seleccionada no existe.', 'status' => 404];
        }

        $system = (new SystemModel())->where('slug', 'ventas')->first();
        if (! $system || (int) ($system['active'] ?? 0) !== 1) {
            return ['error' => 'El sistema Ventas no esta disponible.', 'status' => 404];
        }

        $accessLevel = 'view';
        if (! $this->apiIsSuperadmin()) {
            $assignment = (new CompanySystemModel())->where('company_id', $companyId)->where('system_id', $system['id'])->where('active', 1)->first();
            if (! $assignment) {
                return ['error' => 'La empresa no tiene Ventas asignado.', 'status' => 403];
            }
            $userAssignment = (new UserSystemModel())->where('company_id', $companyId)->where('user_id', $this->apiUser()['id'] ?? '')->where('system_id', $system['id'])->where('active', 1)->first();
            if (! $userAssignment) {
                return ['error' => 'Tu usuario no tiene acceso activo a Ventas.', 'status' => 403];
            }
            $accessLevel = $userAssignment['access_level'] ?? 'view';
        }

        if ($requiredAccess === 'manage' && ! $this->apiIsSuperadmin() && $accessLevel !== 'manage') {
            return ['error' => 'Tu usuario solo tiene acceso de consulta en Ventas.', 'status' => 403];
        }

        $this->ensureSalesDefaults($companyId);
        return ['company' => $company, 'access_level' => $accessLevel];
    }

    private function ensureSalesDefaults(string $companyId): void
    {
        $sequenceModel = new VoucherSequenceModel();
        foreach ([['document_type' => 'PRESUPUESTO', 'prefix' => 'PRE'], ['document_type' => 'PEDIDO', 'prefix' => 'PED'], ['document_type' => 'REMITO', 'prefix' => 'RTO'], ['document_type' => 'FACTURA_B', 'prefix' => 'FCB'], ['document_type' => 'TICKET', 'prefix' => 'TCK'], ['document_type' => 'NC_B', 'prefix' => 'NCB'], ['document_type' => 'RECIBO', 'prefix' => 'REC']] as $row) {
            if (! $sequenceModel->where('company_id', $companyId)->where('document_type', $row['document_type'])->first()) {
                $sequenceModel->insert(['company_id' => $companyId, 'branch_id' => null, 'document_type' => $row['document_type'], 'prefix' => $row['prefix'], 'current_number' => 1, 'active' => 1]);
            }
        }

        if (! (new SalesPriceListModel())->where('company_id', $companyId)->where('is_default', 1)->first()) {
            (new SalesPriceListModel())->insert(['company_id' => $companyId, 'name' => 'Lista General', 'description' => 'Lista base del sistema de ventas.', 'is_default' => 1, 'active' => 1]);
        }

        $this->salesSettings($companyId);
        $this->ensureSalesDocumentDefaults($companyId);
    }

    private function filters(): array
    {
        return [
            'status' => trim((string) $this->request->getGet('status')),
            'customer_id' => trim((string) $this->request->getGet('customer_id')),
            'date_from' => trim((string) $this->request->getGet('date_from')),
            'date_to' => trim((string) $this->request->getGet('date_to')),
            'warehouse_id' => trim((string) $this->request->getGet('warehouse_id')),
        ];
    }

    private function salesSummary(string $companyId, array $filters): array
    {
        $rows = $this->salesRows($companyId, $filters);
        return [
            'drafts' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'draft')),
            'confirmed' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'confirmed')),
            'returned' => count(array_filter($rows, static fn(array $row): bool => in_array($row['status'], ['returned_partial', 'returned_total'], true))),
            'receivable_balance' => $this->receivableSummary($companyId)['balance'],
            'receivable_pending' => $this->receivableSummary($companyId)['pending'],
            'total_amount' => array_sum(array_map(static fn(array $row): float => (float) ($row['total'] ?? 0), $rows)),
            'margin_total' => array_sum(array_map(static fn(array $row): float => (float) ($row['margin_total'] ?? 0), $rows)),
        ];
    }

    private function salesRows(string $companyId, array $filters): array
    {
        $builder = db_connect()->table('sales s')
            ->select('s.*, c.name AS customer_name, w.name AS warehouse_name, dt.name AS document_type_name, dt.code AS document_type_code, dt.category AS document_category, pos.name AS point_of_sale_name, src.sale_number AS source_sale_number, srcdt.name AS source_document_name, src.document_code AS source_document_code, sa.name AS sales_agent_name, sz.name AS sales_zone_name, sc.name AS sales_condition_name')
            ->join('customers c', 'c.id = s.customer_id', 'left')
            ->join('inventory_warehouses w', 'w.id = s.warehouse_id', 'left')
            ->join('sales_document_types dt', 'dt.id = s.document_type_id', 'left')
            ->join('sales_points_of_sale pos', 'pos.id = s.point_of_sale_id', 'left')
            ->join('sales src', 'src.id = s.source_sale_id', 'left')
            ->join('sales_document_types srcdt', 'srcdt.id = src.document_type_id', 'left')
            ->join('sales_agents sa', 'sa.id = s.sales_agent_id', 'left')
            ->join('sales_zones sz', 'sz.id = s.sales_zone_id', 'left')
            ->join('sales_conditions sc', 'sc.id = s.sales_condition_id', 'left')
            ->where('s.company_id', $companyId)
            ->orderBy('s.issue_date', 'DESC');
        if (! empty($filters['status'])) { $builder->where('s.status', $filters['status']); }
        if (! empty($filters['customer_id'])) { $builder->where('s.customer_id', $filters['customer_id']); }
        if (! empty($filters['warehouse_id'])) { $builder->where('s.warehouse_id', $filters['warehouse_id']); }
        if (! empty($filters['date_from'])) { $builder->where('s.issue_date >=', $filters['date_from'] . ' 00:00:00'); }
        if (! empty($filters['date_to'])) { $builder->where('s.issue_date <=', $filters['date_to'] . ' 23:59:59'); }
        return $builder->get()->getResultArray();
    }

    private function documentTypeOptions(string $companyId, ?string $channel = null): array
    {
        $model = new SalesDocumentTypeModel();
        $builder = $model->where('company_id', $companyId)->where('active', 1)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC');
        if ($channel) {
            $builder->groupStart()->where('channel', $channel)->orWhere('channel', 'both')->groupEnd();
        }
        return $builder->findAll();
    }

    private function pointOfSaleOptions(string $companyId, ?string $channel = null): array
    {
        $model = new SalesPointOfSaleModel();
        $builder = $model->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC');
        if ($channel) {
            $builder->groupStart()->where('channel', $channel)->orWhere('channel', 'both')->groupEnd();
        }
        return $builder->findAll();
    }

    private function defaultDocumentType(string $companyId, string $channel = 'standard'): ?array
    {
        $defaults = ['kiosk' => 'TICKET', 'standard' => 'FACTURA_B'];
        if (isset($defaults[$channel])) {
            $match = (new SalesDocumentTypeModel())->where('company_id', $companyId)->where('code', $defaults[$channel])->where('active', 1)->first();
            if ($match) {
                return $match;
            }
        }
        return $this->documentTypeOptions($companyId, $channel)[0] ?? null;
    }

    private function defaultPointOfSale(string $companyId, string $channel = 'standard'): ?array
    {
        return $this->pointOfSaleOptions($companyId, $channel)[0] ?? null;
    }

    private function salesReportData(string $companyId, array $filters): array
    {
        $rows = $this->salesRows($companyId, $filters);
        $topProducts = db_connect()->table('sale_items si')->select('si.product_name, SUM(si.quantity) AS qty, SUM(si.line_total) AS total')->join('sales s', 's.id = si.sale_id')->where('s.company_id', $companyId)->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])->groupBy('si.product_name')->orderBy('qty', 'DESC')->limit(10)->get()->getResultArray();
        $topCustomers = db_connect()->table('sales s')->select('COALESCE(c.name, "Consumidor Final") AS customer_name, COUNT(s.id) AS orders_count, SUM(s.total) AS total')->join('customers c', 'c.id = s.customer_id', 'left')->where('s.company_id', $companyId)->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])->groupBy('c.name')->orderBy('total', 'DESC')->limit(10)->get()->getResultArray();
        $topAgents = db_connect()->table('sales s')->select('COALESCE(sa.name, "Sin vendedor") AS sales_agent_name, COUNT(s.id) AS orders_count, SUM(s.total) AS total, SUM(s.margin_total) AS margin_total', false)->join('sales_agents sa', 'sa.id = s.sales_agent_id', 'left')->where('s.company_id', $companyId)->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])->groupBy('sa.name')->orderBy('total', 'DESC')->limit(10)->get()->getResultArray();
        $topZones = db_connect()->table('sales s')->select('COALESCE(sz.name, "Sin zona") AS sales_zone_name, COUNT(s.id) AS orders_count, SUM(s.total) AS total', false)->join('sales_zones sz', 'sz.id = s.sales_zone_id', 'left')->where('s.company_id', $companyId)->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])->groupBy('sz.name')->orderBy('total', 'DESC')->limit(10)->get()->getResultArray();
        $channelMix = db_connect()->table('sales s')->select('CASE WHEN s.pos_mode = 1 THEN "Kiosco/POS" ELSE "Venta estandar" END AS channel_name, COUNT(s.id) AS orders_count, SUM(s.total) AS total', false)->where('s.company_id', $companyId)->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])->groupBy('s.pos_mode')->orderBy('total', 'DESC')->get()->getResultArray();
        $dailySeries = db_connect()->table('sales s')->select('DATE(s.issue_date) AS report_date, COUNT(s.id) AS orders_count, SUM(s.total) AS total, SUM(s.margin_total) AS margin_total')->where('s.company_id', $companyId)->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])->groupBy('DATE(s.issue_date)')->orderBy('DATE(s.issue_date)', 'DESC')->limit(30)->get()->getResultArray();
        $auditLogs = db_connect()->table('audit_logs al')->select('al.*, u.name AS user_name')->join('users u', 'u.id = al.user_id', 'left')->where('al.company_id', $companyId)->where('al.module', 'sales')->orderBy('al.created_at', 'DESC')->limit(20)->get()->getResultArray();
        $commissions = db_connect()->table('sales_commissions sc')->select('sc.*, sa.name AS sales_agent_name, s.sale_number')->join('sales_agents sa', 'sa.id = sc.sales_agent_id', 'left')->join('sales s', 's.id = sc.sale_id', 'left')->where('sc.company_id', $companyId)->orderBy('sc.created_at', 'DESC')->limit(20)->get()->getResultArray();
        $topCommissions = db_connect()->table('sales_commissions sc')->select('COALESCE(sa.name, "Sin vendedor") AS sales_agent_name, COUNT(sc.id) AS items_count, SUM(sc.commission_amount) AS commission_total', false)->join('sales_agents sa', 'sa.id = sc.sales_agent_id', 'left')->where('sc.company_id', $companyId)->where('sc.status !=', 'void')->groupBy('sa.name')->orderBy('commission_total', 'DESC')->limit(10)->get()->getResultArray();
        return ['summary' => ['sales_count' => count($rows), 'gross_total' => array_sum(array_map(static fn(array $row): float => (float) ($row['total'] ?? 0), $rows)), 'paid_total' => array_sum(array_map(static fn(array $row): float => (float) ($row['paid_total'] ?? 0), $rows)), 'margin_total' => array_sum(array_map(static fn(array $row): float => (float) ($row['margin_total'] ?? 0), $rows)), 'commission_total' => array_sum(array_map(static fn(array $row): float => (float) ($row['commission_amount'] ?? 0), array_filter($commissions, static fn(array $row): bool => ($row['status'] ?? '') !== 'void')))], 'sales' => $rows, 'top_products' => $topProducts, 'top_customers' => $topCustomers, 'top_agents' => $topAgents, 'top_zones' => $topZones, 'channel_mix' => $channelMix, 'daily_series' => $dailySeries, 'audit_logs' => $auditLogs, 'commissions' => $commissions, 'top_commissions' => $topCommissions];
    }

    private function customerOptions(string $companyId): array { return (new CustomerModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll(); }
    private function salesAgentOptions(string $companyId): array { return (new SalesAgentModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll(); }
    private function salesZoneOptions(string $companyId): array { return (new SalesZoneModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll(); }
    private function salesConditionOptions(string $companyId): array { return (new SalesConditionModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll(); }
    private function salesWarehouses(string $companyId): array { return (new InventoryWarehouseModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll(); }
    private function taxOptions(string $companyId): array { return (new TaxModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll(); }
    private function priceListOptions(string $companyId): array { return (new SalesPriceListModel())->where('company_id', $companyId)->where('active', 1)->orderBy('is_default', 'DESC')->orderBy('name', 'ASC')->findAll(); }
    protected function companyCurrencyOptions(?string $companyId = null, ?string $selectedCode = null): array { $rows = db_connect()->table('currencies')->select('code, name, symbol')->where('company_id', $companyId)->where('active', 1)->orderBy('code', 'ASC')->get()->getResultArray(); $options = []; foreach ($rows as $row) { $options[$row['code']] = trim($row['code'] . ' - ' . $row['name'] . ($row['symbol'] ? ' (' . $row['symbol'] . ')' : '')); } if ($selectedCode && ! isset($options[$selectedCode])) { $options[$selectedCode] = $selectedCode; } return $options; }
    private function salesSettings(string $companyId): array { $model = new SalesSettingModel(); $row = $model->where('company_id', $companyId)->first(); if ($row) { $normalized = (new ArcaService())->sanitizeSettings($row, $companyId); if (($row['token_cache_path'] ?? '') !== ($normalized['token_cache_path'] ?? '')) { $model->update($row['id'], ['token_cache_path' => $normalized['token_cache_path']]); $row['token_cache_path'] = $normalized['token_cache_path']; } return $row; } $company = (new CompanyModel())->find($companyId); $id = $model->insert(['company_id' => $companyId, 'default_currency_code' => $company['currency_code'] ?? null, 'invoice_mode_standard_enabled' => 1, 'invoice_mode_kiosk_enabled' => 1, 'strict_company_currencies' => 1, 'profile' => 'argentina_arca', 'kiosk_document_label' => 'Ticket Consumidor Final', 'arca_auto_authorize' => 0, 'token_cache_path' => WRITEPATH . 'arca' . DIRECTORY_SEPARATOR . $companyId], true); return $model->find($id) ?? []; }

    private function arcaEventRows(string $companyId, int $limit = 20): array
    {
        return db_connect()->table('sales_arca_events sae')
            ->select('sae.*, s.sale_number, dt.name AS document_type_name')
            ->join('sales s', 's.id = sae.sale_id', 'left')
            ->join('sales_document_types dt', 'dt.id = s.document_type_id', 'left')
            ->where('sae.company_id', $companyId)
            ->orderBy('sae.performed_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function processArcaAfterConfirmation(string $companyId, string $saleId): void
    {
        $settings = $this->salesSettings($companyId);
        if ((int) ($settings['arca_auto_authorize'] ?? 0) !== 1) {
            return;
        }

        $sale = $this->ownedSale($companyId, $saleId);
        if (! $sale) {
            return;
        }

        $this->authorizeSaleInArca($companyId, $sale);
    }

    private function authorizeSaleInArca(string $companyId, array $sale): array
    {
        $documentType = ! empty($sale['document_type_id']) ? (new SalesDocumentTypeModel())->find($sale['document_type_id']) : null;
        $pointOfSale = ! empty($sale['point_of_sale_id']) ? (new SalesPointOfSaleModel())->find($sale['point_of_sale_id']) : [];
        $company = (new CompanyModel())->find($companyId) ?? [];
        $settings = $this->salesSettings($companyId);
        $items = $this->saleItems($sale['id']);
        $service = new ArcaService();
        $result = $service->authorizeSale($sale, $documentType ?? [], $company, $settings, $items, $pointOfSale ?: []);
        $requestId = $this->recordArcaEvent($companyId, $sale['id'], $result['service_slug'] ?? 'wsaa', 'authorize', $result, $result['request_payload'] ?? []);

        $update = [
            'arca_status' => $result['status'] ?? 'error',
            'arca_service' => $result['service_slug'] ?? null,
            'arca_operation_mode' => (int) ($settings['arca_auto_authorize'] ?? 0) === 1 ? 'automatic' : 'manual',
            'arca_result_code' => $result['result_code'] ?? null,
            'arca_result_message' => $result['message'] ?? null,
            'arca_last_checked_at' => date('Y-m-d H:i:s'),
            'arca_request_id' => $requestId,
        ];
        if (! empty($result['cae'])) {
            $update['cae'] = $result['cae'];
            $update['cae_due_date'] = $result['cae_due_date'] ?? null;
            $update['arca_authorized_at'] = $result['authorized_at'] ?? date('Y-m-d H:i:s');
        }

        (new SaleModel())->update($sale['id'], $update);
        (new SalesSettingModel())->update($settings['id'], [
            'arca_last_sync_at' => date('Y-m-d H:i:s'),
            'arca_last_error' => in_array(($result['status'] ?? ''), ['Authorizado', 'Ok', 'No Aplica'], true) ? null : ($result['message'] ?? null),
        ]);

        $this->logIntegration($companyId, 'arca', (string) ($result['service_slug'] ?? 'arca'), 'sale', (string) $sale['id'], (string) ($result['status'] ?? 'pending'), $result['request_payload'] ?? null, $result['response_payload'] ?? $result, $result['message'] ?? null);

        return $result;
    }

    private function consultSaleInArca(string $companyId, array $sale): array
    {
        $settings = $this->salesSettings($companyId);
        $result = (new ArcaService())->consultSale($sale, $settings);
        $requestId = $this->recordArcaEvent($companyId, $sale['id'], $result['service_slug'] ?? 'wsaa', 'consult', $result, ['sale_number' => $sale['sale_number'] ?? null]);

        (new SaleModel())->update($sale['id'], [
            'arca_status' => $result['status'] ?? ($sale['arca_status'] ?? 'not_requested'),
            'arca_service' => $result['service_slug'] ?? ($sale['arca_service'] ?? null),
            'arca_result_code' => $result['result_code'] ?? ($sale['arca_result_code'] ?? null),
            'arca_result_message' => $result['message'] ?? ($sale['arca_result_message'] ?? null),
            'arca_last_checked_at' => $result['checked_at'] ?? date('Y-m-d H:i:s'),
            'arca_request_id' => $requestId,
        ]);

        return $result;
    }

    private function recordArcaEvent(string $companyId, ?string $saleId, string $serviceSlug, string $eventType, array $result, array $requestPayload = []): string
    {
        $model = new SalesArcaEventModel();
        $id = $model->insert([
            'company_id' => $companyId,
            'sale_id' => $saleId,
            'service_slug' => $serviceSlug,
            'event_type' => $eventType,
            'environment' => $result['environment'] ?? 'homologacion',
            'request_payload' => $requestPayload !== [] ? json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'response_payload' => json_encode($result['response_payload'] ?? $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => $result['status'] ?? 'pending',
            'result_code' => $result['result_code'] ?? null,
            'message' => $result['message'] ?? null,
            'performed_by' => $this->apiUser()['id'] ?? null,
            'performed_at' => date('Y-m-d H:i:s'),
        ], true);

        return (string) $id;
    }

    private function logAudit(string $companyId, string $module, string $entityType, ?string $entityId, string $action, mixed $before = null, mixed $after = null): void
    {
        (new AuditLogModel())->insert([
            'company_id' => $companyId,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'before_payload' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_payload' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'user_id' => $this->apiUser()['id'] ?? null,
        ]);
    }

    private function logDocumentEvent(string $companyId, string $module, string $documentType, string $documentId, string $eventType, array $payload = []): void
    {
        (new DocumentEventModel())->insert([
            'company_id' => $companyId,
            'module' => $module,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'event_type' => $eventType,
            'payload' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'user_id' => $this->apiUser()['id'] ?? null,
        ]);
    }

    private function logIntegration(string $companyId, string $provider, string $service, ?string $referenceType, ?string $referenceId, string $status, mixed $request = null, mixed $response = null, ?string $message = null): void
    {
        (new IntegrationLogModel())->insert([
            'company_id' => $companyId,
            'provider' => $provider,
            'service' => $service,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => $status,
            'request_payload' => $request === null ? null : json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_payload' => $response === null ? null : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'message' => $message,
            'user_id' => $this->apiUser()['id'] ?? null,
        ]);
    }

    private function activePromotions(string $companyId): array
    {
        $now = date('Y-m-d H:i:s');
        return (new SalesPromotionModel())->where('company_id', $companyId)->where('active', 1)->groupStart()->where('start_date IS NULL')->orWhere('start_date <=', $now)->groupEnd()->groupStart()->where('end_date IS NULL')->orWhere('end_date >=', $now)->groupEnd()->findAll();
    }

    private function promotionMap(string $companyId): array
    {
        $promotions = $this->activePromotions($companyId);
        $items = db_connect()->table('sales_promotion_items spi')->select('spi.product_id, spi.promotion_id')->join('sales_promotions sp', 'sp.id = spi.promotion_id')->where('sp.company_id', $companyId)->where('sp.active', 1)->get()->getResultArray();
        $productMap = [];
        foreach ($items as $row) { $productMap[$row['product_id']][] = $row['promotion_id']; }
        return ['promotions' => $promotions, 'product_map' => $productMap];
    }

    private function salesProductCatalog(string $companyId): array
    {
        $products = (new InventoryProductModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
        $stockRows = db_connect()->table('inventory_stock_levels')->select('product_id, warehouse_id, quantity, reserved_quantity')->where('company_id', $companyId)->get()->getResultArray();
        $stockMap = [];
        foreach ($stockRows as $row) {
            $stockMap[$row['product_id']][$row['warehouse_id']] = ['stock' => (float) $row['quantity'], 'reserved' => (float) ($row['reserved_quantity'] ?? 0), 'available' => ((float) $row['quantity']) - ((float) ($row['reserved_quantity'] ?? 0))];
        }
        return array_map(static function (array $product) use ($stockMap): array { $product['sale_price'] = (float) ($product['sale_price'] ?? 0); $product['stocks'] = $stockMap[$product['id']] ?? []; return $product; }, $products);
    }

    private function salePayload(string $companyId, array $overrides = []): array
    {
        $payload = array_replace_recursive($this->payload(), $overrides);
        $channel = (string) ($payload['pos_mode'] ?? '') === '1' ? 'kiosk' : 'standard';
        $documentContext = $this->resolveDocumentContext($companyId, $channel, trim((string) ($payload['document_type_id'] ?? '')), trim((string) ($payload['point_of_sale_id'] ?? '')));
        if ($documentContext['error'] !== null) { return ['error' => $documentContext['error']]; }
        $priceListId = trim((string) ($payload['price_list_id'] ?? '')) ?: null;
        $items = $this->parseSaleItems($companyId, (array) ($payload['items'] ?? []), $priceListId);
        if ($items === []) { return ['error' => 'Debes agregar al menos un producto.']; }
        $payments = $this->parsePayments((array) ($payload['payments'] ?? []));
        $priceList = $priceListId ? (new SalesPriceListModel())->find($priceListId) : null;
        $totals = $this->calculateTotals($items, (float) ($payload['global_discount_total'] ?? 0), $payments);
        $customerId = trim((string) ($payload['customer_id'] ?? '')) ?: null;
        $customer = null;
        if ($customerId) {
            $customer = (new CustomerModel())->where('company_id', $companyId)->where('id', $customerId)->where('active', 1)->first();
            if (! $customer) {
                return ['error' => 'Debes seleccionar un cliente valido.'];
            }
        }
        if ((int) ($documentContext['documentType']['requires_customer'] ?? 0) === 1 && ! $customerId) {
            return ['error' => 'El comprobante seleccionado requiere un cliente.'];
        }

        $warehouseId = trim((string) ($payload['warehouse_id'] ?? '')) ?: null;
        if (! $warehouseId || ! (new InventoryWarehouseModel())->where('company_id', $companyId)->where('id', $warehouseId)->where('active', 1)->first()) {
            return ['error' => 'Debes seleccionar un deposito valido.'];
        }

        $currencyCode = trim((string) ($payload['currency_code'] ?? '')) ?: 'ARS';
        $settings = $this->salesSettings($companyId);
        if (! array_key_exists($currencyCode, $this->companyCurrencyOptions($companyId, $settings['default_currency_code'] ?? null))) {
            return ['error' => 'La moneda seleccionada debe pertenecer a las monedas activas de la empresa.'];
        }

        $issueDate = trim((string) ($payload['issue_date'] ?? '')) ?: date('Y-m-d H:i:s');
        $dueDate = trim((string) ($payload['due_date'] ?? ''));
        if ($dueDate === '' && $customer) {
            $paymentTermsDays = (int) ($customer['payment_terms_days'] ?? 0);
            $dueDate = $paymentTermsDays > 0 ? date('Y-m-d H:i:s', strtotime($issueDate . ' +' . $paymentTermsDays . ' days')) : null;
        } elseif ($dueDate !== '') {
            $dueDate = str_contains($dueDate, 'T') ? str_replace('T', ' ', $dueDate) . ':00' : $dueDate;
        } else {
            $dueDate = null;
        }

        return [
            'sale' => [
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'document_type_id' => $documentContext['documentType']['id'],
                'point_of_sale_id' => $documentContext['pointOfSale']['id'] ?? null,
                'source_sale_id' => trim((string) ($payload['source_sale_id'] ?? '')) ?: null,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'status' => 'draft',
                'reservation_status' => 'none',
                'payment_status' => $totals['payment_status'],
                'currency_code' => $currencyCode,
                'fiscal_profile' => $customer['tax_profile'] ?? ($documentContext['documentType']['channel'] === 'kiosk' ? 'consumidor_final' : 'cliente'),
                'customer_name_snapshot' => $customer['billing_name'] ?? $customer['name'] ?? 'Consumidor Final',
                'customer_document_snapshot' => trim(($customer['document_type'] ?? '') . ' ' . ($customer['document_number'] ?? '')),
                'customer_tax_profile' => $customer['vat_condition'] ?? $customer['tax_profile'] ?? 'Consumidor Final',
                'sales_agent_id' => trim((string) ($payload['sales_agent_id'] ?? ($customer['sales_agent_id'] ?? ''))) ?: null,
                'sales_zone_id' => trim((string) ($payload['sales_zone_id'] ?? ($customer['sales_zone_id'] ?? ''))) ?: null,
                'sales_condition_id' => trim((string) ($payload['sales_condition_id'] ?? ($customer['sales_condition_id'] ?? ''))) ?: null,
                'price_list_name' => $priceList['name'] ?? trim((string) ($payload['price_list_name'] ?? '')),
                'price_list_id' => $priceListId,
                'promotion_snapshot' => null,
                'subtotal' => $totals['subtotal'],
                'item_discount_total' => $totals['item_discount_total'],
                'global_discount_total' => $totals['global_discount_total'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'paid_total' => $totals['paid_total'],
                'margin_total' => round(array_sum(array_map(static fn(array $item): float => ((float) ($item['line_total'] ?? 0)) - (((float) ($item['unit_cost'] ?? 0)) * ((float) ($item['quantity'] ?? 0))), $items)), 2),
                'notes' => trim((string) ($payload['notes'] ?? '')),
            ],
            'items' => $items,
            'payments' => $payments,
            'documentType' => $documentContext['documentType'],
            'pointOfSale' => $documentContext['pointOfSale'],
        ];
    }

    private function resolveDocumentContext(string $companyId, string $channel, string $documentTypeId, string $pointOfSaleId): array
    {
        $documentType = $documentTypeId !== '' ? (new SalesDocumentTypeModel())->where('company_id', $companyId)->where('id', $documentTypeId)->where('active', 1)->first() : $this->defaultDocumentType($companyId, $channel);
        if (! $documentType) {
            return ['error' => 'Debes seleccionar un tipo de comprobante valido.', 'documentType' => null, 'pointOfSale' => null];
        }
        $pointOfSale = $pointOfSaleId !== '' ? (new SalesPointOfSaleModel())->where('company_id', $companyId)->where('id', $pointOfSaleId)->where('active', 1)->first() : $this->defaultPointOfSale($companyId, $channel);
        if (! $pointOfSale) {
            return ['error' => 'Debes seleccionar un punto de venta valido.', 'documentType' => null, 'pointOfSale' => null];
        }
        return ['error' => null, 'documentType' => $documentType, 'pointOfSale' => $pointOfSale];
    }

    private function parseSaleItems(string $companyId, array $items, ?string $priceListId): array
    {
        $taxMap = [];
        foreach ($this->taxOptions($companyId) as $tax) { $taxMap[$tax['id']] = (float) $tax['rate']; }
        $promotions = $this->promotionMap($companyId);
        $rows = [];
        foreach ($items as $item) {
            $productId = trim((string) ($item['product_id'] ?? ''));
            if (array_key_exists('enabled', $item) && (string) ($item['enabled'] ?? '') !== '1') { continue; }
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($productId === '' || $quantity <= 0) { continue; }
            $product = (new InventoryProductModel())->where('company_id', $companyId)->where('id', $productId)->where('active', 1)->first();
            if (! $product) { continue; }
            $resolved = $this->resolveProductPrice($productId, $priceListId, $promotions, (float) ($product['sale_price'] ?? 0));
            $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== '' ? (float) $item['unit_price'] : $resolved['unit_price'];
            $discountRate = (float) ($item['discount_rate'] ?? 0);
            if ($discountRate <= 0 && $resolved['promotion_discount_rate'] > 0) { $discountRate = $resolved['promotion_discount_rate']; }
            $discountAmount = round(($quantity * $unitPrice) * ($discountRate / 100), 2);
            $taxId = trim((string) ($item['tax_id'] ?? '')) ?: null;
            $taxRate = $taxId && isset($taxMap[$taxId]) ? $taxMap[$taxId] : 0.0;
            $subtotal = round(($quantity * $unitPrice) - $discountAmount, 2);
            $taxTotal = round($subtotal * ($taxRate / 100), 2);
            $rows[] = ['line_number' => count($rows) + 1, 'product_id' => $productId, 'tax_id' => $taxId, 'sku' => $product['sku'], 'product_name' => $product['name'], 'product_type' => $product['product_type'] ?? 'simple', 'unit' => $product['unit'] ?? 'unidad', 'quantity' => $quantity, 'returned_quantity' => 0, 'available_stock_snapshot' => (float) ($item['available_stock_snapshot'] ?? 0), 'unit_price' => $unitPrice, 'unit_cost' => (float) ($product['cost_price'] ?? 0), 'discount_rate' => $discountRate, 'discount_amount' => $discountAmount, 'tax_rate' => $taxRate, 'subtotal' => $subtotal, 'tax_total' => $taxTotal, 'line_total' => round($subtotal + $taxTotal, 2)];
        }
        return $rows;
    }

    private function parsePayments(array $payments): array
    {
        $rows = [];
        foreach ($payments as $payment) {
            $method = trim((string) ($payment['payment_method'] ?? ''));
            $amount = (float) ($payment['amount'] ?? 0);
            if ($method === '' || $amount <= 0) { continue; }
            $rows[] = ['payment_method' => $method, 'amount' => $amount, 'reference' => trim((string) ($payment['reference'] ?? '')), 'status' => 'registered', 'paid_at' => trim((string) ($payment['paid_at'] ?? '')) ?: null, 'notes' => trim((string) ($payment['notes'] ?? ''))];
        }
        return $rows;
    }

    private function calculateTotals(array $items, float $globalDiscount, array $payments): array
    {
        $subtotal = array_sum(array_map(static fn(array $row): float => (float) $row['subtotal'], $items));
        $discount = array_sum(array_map(static fn(array $row): float => (float) $row['discount_amount'], $items));
        $tax = array_sum(array_map(static fn(array $row): float => (float) $row['tax_total'], $items));
        $total = max(0, round($subtotal + $tax - $globalDiscount, 2));
        $paid = round(array_sum(array_map(static fn(array $row): float => (float) $row['amount'], $payments)), 2);
        return ['subtotal' => round($subtotal, 2), 'item_discount_total' => round($discount, 2), 'global_discount_total' => round($globalDiscount, 2), 'tax_total' => round($tax, 2), 'total' => $total, 'paid_total' => $paid, 'payment_status' => $paid <= 0 ? 'pending' : ($paid < $total ? 'partial' : 'paid')];
    }

    private function persistSaleChildren(string $saleId, array $items, array $payments): void
    {
        foreach ($items as $item) { (new SaleItemModel())->insert(array_merge($item, ['sale_id' => $saleId])); }
        foreach ($payments as $payment) { (new SalePaymentModel())->insert(array_merge($payment, ['sale_id' => $saleId])); }
    }

    private function saleItems(string $saleId): array { return (new SaleItemModel())->where('sale_id', $saleId)->orderBy('line_number', 'ASC')->findAll(); }
    private function salePayments(string $saleId): array { return (new SalePaymentModel())->where('sale_id', $saleId)->findAll(); }
    private function saleReturns(string $saleId): array { return (new SaleReturnModel())->where('sale_id', $saleId)->findAll(); }
    private function ownedSale(string $companyId, string $saleId): ?array { return (new SaleModel())->where('company_id', $companyId)->where('id', $saleId)->first() ?: null; }

    private function resolveProductPrice(string $productId, ?string $priceListId, array $promotionMap, float $basePrice): array
    {
        $price = $basePrice;
        if ($priceListId) {
            $listPrice = (new SalesPriceListItemModel())->where('price_list_id', $priceListId)->where('product_id', $productId)->first();
            if ($listPrice) { $price = (float) $listPrice['price']; }
        }
        $discountRate = 0.0;
        foreach (($promotionMap['promotions'] ?? []) as $promotion) {
            if (($promotion['scope'] ?? 'selected') === 'all' || in_array($promotion['id'], $promotionMap['product_map'][$productId] ?? [], true)) {
                if (($promotion['promotion_type'] ?? 'percent') === 'percent') { $discountRate = max($discountRate, (float) ($promotion['value'] ?? 0)); }
                if (($promotion['promotion_type'] ?? '') === 'fixed') { $price = max(0, $price - (float) ($promotion['value'] ?? 0)); }
            }
        }
        return ['unit_price' => $price, 'promotion_discount_rate' => $discountRate];
    }

    private function inventorySettings(string $companyId): array { return (new InventorySettingModel())->where('company_id', $companyId)->first() ?? []; }
    private function canReserve(string $companyId, string $productId, ?string $warehouseId, float $quantity): bool { if ($warehouseId === null) { return false; } $row = (new InventoryStockLevelModel())->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->first(); return (((float) ($row['quantity'] ?? 0)) - ((float) ($row['reserved_quantity'] ?? 0))) >= $quantity; }
    private function canWithdraw(string $companyId, string $productId, ?string $warehouseId, float $quantity, bool $allowNegative, ?string $ignoreSaleId = null): bool { if ($allowNegative || $warehouseId === null) { return true; } $row = (new InventoryStockLevelModel())->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->first(); $available = (((float) ($row['quantity'] ?? 0)) - ((float) ($row['reserved_quantity'] ?? 0))); if ($ignoreSaleId) { $available += (float) ((new InventoryReservationModel())->selectSum('quantity', 'qty')->where('company_id', $companyId)->where('warehouse_id', $warehouseId)->where('product_id', $productId)->where('sale_id', $ignoreSaleId)->where('status', 'active')->first()['qty'] ?? 0); } return $available >= $quantity; }
    private function applyStockDelta(string $companyId, string $productId, ?string $warehouseId, float $delta): void { if ($warehouseId === null) { return; } $model = new InventoryStockLevelModel(); $product = (new InventoryProductModel())->find($productId); $row = $model->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->first(); if ($row) { $model->update($row['id'], ['quantity' => ((float) $row['quantity']) + $delta]); return; } $model->insert(['company_id' => $companyId, 'product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => $delta, 'reserved_quantity' => 0, 'min_stock' => $product['min_stock'] ?? 0]); }
    private function applyReservedDelta(string $companyId, string $productId, ?string $warehouseId, float $delta): void { if ($warehouseId === null) { return; } $model = new InventoryStockLevelModel(); $product = (new InventoryProductModel())->find($productId); $row = $model->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->first(); if ($row) { $model->update($row['id'], ['reserved_quantity' => max(0, ((float) $row['reserved_quantity']) + $delta)]); return; } $model->insert(['company_id' => $companyId, 'product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => 0, 'reserved_quantity' => max(0, $delta), 'min_stock' => $product['min_stock'] ?? 0]); }
    private function registerInventoryMovement(array $payload): void { (new InventoryMovementModel())->insert(['company_id' => $payload['company_id'], 'product_id' => $payload['product_id'], 'movement_type' => $payload['movement_type'], 'quantity' => $payload['quantity'], 'unit_cost' => $payload['unit_cost'] ?? null, 'total_cost' => $payload['total_cost'] ?? null, 'source_warehouse_id' => $payload['source_warehouse_id'] ?? null, 'destination_warehouse_id' => $payload['destination_warehouse_id'] ?? null, 'performed_by' => $payload['performed_by'], 'occurred_at' => $payload['occurred_at'] ?? date('Y-m-d H:i:s'), 'reason' => $payload['reason'] ?? null, 'source_document' => $payload['source_document'] ?? null, 'notes' => $payload['notes'] ?? null]); }

    private function confirmSaleTransaction(string $companyId, string $saleId)
    {
        $sale = $this->ownedSale($companyId, $saleId);
        if (! $sale) { return 'Venta no disponible.'; }
        $items = $this->saleItems($saleId);
        if ($items === []) { return 'La venta debe tener productos.'; }
        $warehouseId = $sale['warehouse_id'] ?: null;
        if (! $warehouseId) { return 'La venta debe tener deposito origen.'; }
        $documentType = ! empty($sale['document_type_id']) ? (new SalesDocumentTypeModel())->find($sale['document_type_id']) : null;
        if (! $documentType) { return 'El comprobante seleccionado ya no esta disponible.'; }
        $allowNegative = (int) (($this->inventorySettings($companyId)['allow_negative_stock'] ?? 0)) === 1;
        $db = db_connect();
        $db->transStart();
        $category = (string) ($documentType['category'] ?? 'invoice');
        if ($category === 'order') {
            $result = $this->reserveStockForSale($companyId, $sale, $items);
            if ($result !== true) { $db->transRollback(); return $result; }
        } elseif (in_array($category, ['delivery_note', 'invoice', 'ticket'], true) && (int) ($documentType['impacts_stock'] ?? 0) === 1) {
            $result = $this->deliverSaleStock($companyId, $sale, $items, $allowNegative, $category === 'delivery_note' ? 'REMITO' : 'VENTA');
            if ($result !== true) { $db->transRollback(); return $result; }
        }
        $update = ['status' => 'confirmed', 'confirmed_by' => $this->apiUser()['id'], 'confirmed_at' => date('Y-m-d H:i:s')];
        if ($category === 'delivery_note') { $update['delivered_by'] = $this->apiUser()['id']; $update['delivered_at'] = date('Y-m-d H:i:s'); }
        (new SaleModel())->update($saleId, $update);
        $this->syncReceivableForSale($saleId);
        $this->syncCashMovementsForSale($companyId, $saleId);
        $this->syncSaleCommission($companyId, $saleId);
        (new AccountingService())->syncSale($companyId, $saleId, $this->apiUser()['id']);
        $db->transComplete();
        return $db->transStatus() ? true : 'No se pudo confirmar la venta.';
    }

    private function reserveStockForSale(string $companyId, array $sale, array $items)
    {
        $warehouseId = $sale['warehouse_id'] ?: null;
        if (! $warehouseId) { return 'La venta debe tener deposito origen.'; }
        foreach ($items as $item) {
            $this->lockStockLevel($companyId, (string) $item['product_id'], $warehouseId);
            if (! $this->canReserve($companyId, (string) $item['product_id'], $warehouseId, (float) $item['quantity'])) { return 'Stock insuficiente para reservar el pedido.'; }
        }
        $reservationModel = new InventoryReservationModel();
        foreach ($items as $item) {
            $reservationModel->insert(['company_id' => $companyId, 'product_id' => (string) $item['product_id'], 'warehouse_id' => $warehouseId, 'sale_id' => $sale['id'], 'quantity' => (float) $item['quantity'], 'reference' => $sale['sale_number'], 'notes' => 'Reserva generada desde pedido', 'status' => 'active', 'reserved_by' => $this->apiUser()['id'], 'reserved_at' => date('Y-m-d H:i:s')]);
            $this->applyReservedDelta($companyId, (string) $item['product_id'], $warehouseId, (float) $item['quantity']);
        }
        (new SaleModel())->update($sale['id'], ['reservation_status' => 'active', 'reserved_at' => date('Y-m-d H:i:s'), 'reservation_released_at' => null]);
        return true;
    }

    private function releaseReservationsForSale(string $companyId, array $sale, array $items, string $finalStatus = 'released'): void
    {
        $reservationModel = new InventoryReservationModel();
        $warehouseId = $sale['warehouse_id'] ?: null;
        foreach ($items as $item) {
            $reservations = $reservationModel->where('company_id', $companyId)->where('sale_id', $sale['id'])->where('product_id', (string) $item['product_id'])->where('status', 'active')->findAll();
            foreach ($reservations as $reservation) {
                $reservationModel->update($reservation['id'], ['status' => $finalStatus, 'released_by' => $this->apiUser()['id'], 'released_at' => date('Y-m-d H:i:s')]);
                $this->applyReservedDelta($companyId, (string) $item['product_id'], $warehouseId, ((float) $reservation['quantity']) * -1);
            }
        }
        (new SaleModel())->update($sale['id'], ['reservation_status' => $finalStatus === 'cancelled' ? 'cancelled' : 'released', 'reservation_released_at' => date('Y-m-d H:i:s')]);
    }

    private function deliverSaleStock(string $companyId, array $sale, array $items, bool $allowNegative, string $reason)
    {
        $warehouseId = $sale['warehouse_id'] ?: null;
        if (! $warehouseId) { return 'La venta debe tener deposito origen.'; }
        $sourceSale = ! empty($sale['source_sale_id']) ? $this->ownedSale($companyId, (string) $sale['source_sale_id']) : null;
        $sourceDocumentType = (! empty($sourceSale['document_type_id'])) ? (new SalesDocumentTypeModel())->find($sourceSale['document_type_id']) : null;
        $sourceCategory = (string) ($sourceDocumentType['category'] ?? '');
        if ($sourceCategory === 'delivery_note' && ($sourceSale['status'] ?? '') === 'confirmed') { return true; }
        foreach ($items as $item) {
            $this->lockStockLevel($companyId, (string) $item['product_id'], $warehouseId);
            if (! $this->canWithdraw($companyId, (string) $item['product_id'], $warehouseId, (float) $item['quantity'], $allowNegative, $sale['id'])) { return 'Stock insuficiente para confirmar el documento.'; }
        }
        if ($sourceCategory === 'order' && ($sourceSale['reservation_status'] ?? 'none') === 'active') {
            $this->releaseReservationsForSale($companyId, $sourceSale, $this->saleItems($sourceSale['id']), 'consumed');
        }
        foreach ($items as $item) {
            $this->applyStockDelta($companyId, (string) $item['product_id'], $warehouseId, ((float) $item['quantity']) * -1);
            $this->registerInventoryMovement(['company_id' => $companyId, 'product_id' => (string) $item['product_id'], 'movement_type' => 'egreso', 'quantity' => (float) $item['quantity'], 'unit_cost' => (float) ($item['unit_cost'] ?? 0), 'total_cost' => ((float) ($item['unit_cost'] ?? 0)) * ((float) $item['quantity']), 'source_warehouse_id' => $warehouseId, 'performed_by' => $this->apiUser()['id'], 'reason' => $reason, 'source_document' => $sale['sale_number'], 'notes' => $reason === 'REMITO' ? 'Salida por remito' : 'Confirmacion de venta']);
        }
        return true;
    }

    private function restockDeliveredSale(string $companyId, array $sale, array $items, string $reason): void
    {
        $sourceSale = ! empty($sale['source_sale_id']) ? $this->ownedSale($companyId, (string) $sale['source_sale_id']) : null;
        $sourceDocumentType = (! empty($sourceSale['document_type_id'])) ? (new SalesDocumentTypeModel())->find($sourceSale['document_type_id']) : null;
        if ((string) ($sourceDocumentType['category'] ?? '') === 'delivery_note' && ($sourceSale['status'] ?? '') === 'confirmed') { return; }
        foreach ($items as $item) {
            $this->applyStockDelta($companyId, (string) $item['product_id'], $sale['warehouse_id'], (float) $item['quantity']);
            $this->registerInventoryMovement(['company_id' => $companyId, 'product_id' => (string) $item['product_id'], 'movement_type' => 'ingreso', 'quantity' => (float) $item['quantity'], 'unit_cost' => (float) ($item['unit_cost'] ?? 0), 'total_cost' => ((float) ($item['unit_cost'] ?? 0)) * ((float) $item['quantity']), 'destination_warehouse_id' => $sale['warehouse_id'], 'performed_by' => $this->apiUser()['id'], 'reason' => $reason, 'source_document' => $sale['sale_number'], 'notes' => 'Reversion por cancelacion de documento']);
        }
    }

    private function parseReturnItems(string $saleId, array $requested): array
    {
        $saleItems = [];
        foreach ($this->saleItems($saleId) as $item) { $saleItems[$item['id']] = $item; }
        $rows = [];
        foreach ($requested as $data) {
            $saleItemId = trim((string) ($data['sale_item_id'] ?? ''));
            $quantity = (float) ($data['quantity'] ?? 0);
            if ($saleItemId === '' || $quantity <= 0 || ! isset($saleItems[$saleItemId])) { continue; }
            $item = $saleItems[$saleItemId];
            $available = (float) $item['quantity'] - (float) ($item['returned_quantity'] ?? 0);
            if ($quantity > $available) { continue; }
            $rows[] = ['sale_item_id' => $saleItemId, 'product_id' => $item['product_id'], 'quantity' => $quantity, 'unit_price' => (float) $item['unit_price'], 'already_returned' => (float) ($item['returned_quantity'] ?? 0), 'reason' => trim((string) ($data['reason'] ?? ''))];
        }
        return $rows;
    }

    private function syncSaleReturnStatus(string $saleId): void
    {
        $items = $this->saleItems($saleId);
        $total = array_sum(array_map(static fn(array $item): float => (float) $item['quantity'], $items));
        $returned = array_sum(array_map(static fn(array $item): float => (float) ($item['returned_quantity'] ?? 0), $items));
        $status = 'confirmed';
        if ($returned > 0 && $returned < $total) { $status = 'returned_partial'; }
        if ($total > 0 && $returned >= $total) { $status = 'returned_total'; }
        (new SaleModel())->update($saleId, ['status' => $status]);
    }

    private function receivableSummary(string $companyId): array
    {
        $rows = (new SalesReceivableModel())->where('company_id', $companyId)->findAll();
        return [
            'pending' => count(array_filter($rows, static fn(array $row): bool => in_array(($row['status'] ?? ''), ['pending', 'partial'], true))),
            'balance' => round(array_sum(array_map(static fn(array $row): float => (float) ($row['balance_amount'] ?? 0), $rows)), 2),
        ];
    }

    private function receivableRows(string $companyId): array
    {
        return db_connect()->table('sales_receivables sr')
            ->select('sr.*, c.name AS customer_name, s.payment_status, s.status AS sale_status')
            ->join('customers c', 'c.id = sr.customer_id', 'left')
            ->join('sales s', 's.id = sr.sale_id', 'left')
            ->where('sr.company_id', $companyId)
            ->whereIn('sr.status', ['pending', 'partial'])
            ->orderBy('sr.due_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function receiptRows(string $companyId): array
    {
        return db_connect()->table('sales_receipts sr')
            ->select('sr.*, c.name AS customer_name')
            ->join('customers c', 'c.id = sr.customer_id')
            ->where('sr.company_id', $companyId)
            ->orderBy('sr.issue_date', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function receiptApplicationsPayloadFromApi(string $companyId, array $items): array
    {
        $rows = [];
        $model = new SalesReceivableModel();

        foreach ($items as $item) {
            $receivableId = trim((string) ($item['sales_receivable_id'] ?? ''));
            $appliedAmount = (float) ($item['applied_amount'] ?? 0);
            $receivable = $receivableId !== '' ? $model->where('company_id', $companyId)->find($receivableId) : null;
            if (! $receivable || $appliedAmount <= 0) {
                continue;
            }

            $available = (float) ($receivable['balance_amount'] ?? 0);
            if ($appliedAmount > $available) {
                continue;
            }

            $rows[] = [
                'receivable_id' => $receivableId,
                'sale_id' => (string) $receivable['sale_id'],
                'customer_id' => (string) ($receivable['customer_id'] ?? ''),
                'document_number' => (string) ($receivable['document_number'] ?? ''),
                'applied_amount' => round($appliedAmount, 2),
            ];
        }

        return $rows;
    }

    private function syncReceivableForSale(string $saleId): void
    {
        $sale = (new SaleModel())->find($saleId);
        if (! $sale) {
            return;
        }

        $model = new SalesReceivableModel();
        $receivable = $model->where('sale_id', $saleId)->first();
        $documentType = ! empty($sale['document_type_id']) ? (new SalesDocumentTypeModel())->find($sale['document_type_id']) : null;
        if ((int) ($documentType['impacts_receivable'] ?? 0) !== 1) {
            if ($receivable) {
                $model->delete($receivable['id']);
            }
            return;
        }

        $total = (float) ($sale['total'] ?? 0);
        $paid = (float) ($sale['paid_total'] ?? 0);
        $balance = max(0, round($total - $paid, 2));
        $status = ($sale['status'] ?? '') === 'cancelled' || ($sale['status'] ?? '') === 'returned_total'
            ? 'cancelled'
            : ($balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'));

        $payload = [
            'company_id' => $sale['company_id'],
            'sale_id' => $saleId,
            'customer_id' => $sale['customer_id'] ?: null,
            'document_type_id' => $sale['document_type_id'] ?: null,
            'document_number' => $sale['sale_number'],
            'issue_date' => $sale['issue_date'],
            'due_date' => $sale['due_date'] ?: $sale['issue_date'],
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_amount' => $balance,
            'status' => $status,
        ];

        if ($receivable) {
            $model->update($receivable['id'], $payload);
            return;
        }

        if (($sale['status'] ?? '') === 'confirmed' || $paid > 0 || $balance > 0) {
            $model->insert($payload);
        }
    }

    private function resolveCashSession(string $companyId, string $channel): ?array
    {
        $service = new CashService();
        $service->ensureDefaults($companyId, $this->apiUser()['branch_id'] ?? null);

        $session = $service->activeSessionForChannel($companyId, $channel);

        // Auto-open kiosk cash session if none is active
        if (! $session && $channel === 'kiosk') {
            $session = $service->autoOpenKioskSession(
                $companyId,
                $this->apiUser()['id'],
                $this->apiUser()['branch_id'] ?? null
            );
        }

        return $session;
    }

    private function syncCashMovementsForSale(string $companyId, string $saleId): void
    {
        $sale = (new SaleModel())->find($saleId);
        if (! $sale || empty($sale['cash_session_id']) || empty($sale['cash_register_id'])) {
            return;
        }

        $service = new CashService();
        foreach ($this->salePayments($saleId) as $payment) {
            $service->registerMovement([
                'company_id' => $companyId,
                'cash_register_id' => $sale['cash_register_id'],
                'cash_session_id' => $sale['cash_session_id'],
                'movement_type' => 'sale_income',
                'payment_method' => $payment['payment_method'] ?? null,
                'amount' => (float) ($payment['amount'] ?? 0),
                'reference_type' => 'sale_payment',
                'reference_id' => $payment['id'] ?? null,
                'reference_number' => $sale['sale_number'] ?? null,
                'occurred_at' => $payment['paid_at'] ?: date('Y-m-d H:i:s'),
                'notes' => 'Cobro generado desde venta',
                'created_by' => $this->apiUser()['id'],
            ]);
        }
    }

    private function syncSaleCommission(string $companyId, string $saleId): void
    {
        $sale = (new SaleModel())->find($saleId);
        if (! $sale) {
            return;
        }

        $model = new SalesCommissionModel();
        $existing = $model->where('sale_id', $saleId)->first();

        if (empty($sale['sales_agent_id'])) {
            if ($existing) {
                $model->delete($existing['id']);
            }
            return;
        }

        $agent = (new SalesAgentModel())->where('company_id', $companyId)->find($sale['sales_agent_id']);
        if (! $agent) {
            if ($existing) {
                $model->delete($existing['id']);
            }
            return;
        }

        $baseAmount = max(0, (float) ($sale['margin_total'] ?? $sale['total'] ?? 0));
        $rate = (float) ($agent['commission_rate'] ?? 0);
        $status = in_array(($sale['status'] ?? ''), ['cancelled', 'returned_total'], true) ? 'void' : 'pending';
        $payload = [
            'company_id' => $companyId,
            'sale_id' => $saleId,
            'sales_agent_id' => $agent['id'],
            'base_amount' => $baseAmount,
            'rate' => $rate,
            'commission_amount' => round($baseAmount * ($rate / 100), 2),
            'status' => $status,
            'notes' => 'Comision sincronizada desde venta',
            'liquidated_at' => $status === 'liquidated' ? date('Y-m-d H:i:s') : null,
        ];

        if ($existing) {
            $model->update($existing['id'], $payload);
            return;
        }

        if (in_array(($sale['status'] ?? ''), ['confirmed', 'returned_partial', 'returned_total', 'cancelled'], true)) {
            $model->insert($payload);
        }
    }

    private function lockStockLevel(string $companyId, string $productId, string $warehouseId): void
    {
        db_connect()->query(
            'SELECT id FROM inventory_stock_levels WHERE company_id = ? AND product_id = ? AND warehouse_id = ? FOR UPDATE',
            [$companyId, $productId, $warehouseId]
        );
    }

    private function csvResponse(array $rows, string $filename)
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';');
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }

    private function ensureSalesDocumentDefaults(string $companyId): void
    {
        $documentTypeModel = new SalesDocumentTypeModel();
        $pointOfSaleModel = new SalesPointOfSaleModel();
        $defaultWarehouse = (new InventoryWarehouseModel())->where('company_id', $companyId)->where('active', 1)->orderBy('is_default', 'DESC')->orderBy('name', 'ASC')->first();

        $definitions = [
            ['code' => 'PRESUPUESTO', 'name' => 'Presupuesto', 'category' => 'quote', 'letter' => null, 'sequence_key' => 'PRESUPUESTO', 'default_prefix' => 'PRE', 'channel' => 'standard', 'impacts_stock' => 0, 'impacts_receivable' => 0, 'requires_customer' => 0, 'sort_order' => 1],
            ['code' => 'PEDIDO', 'name' => 'Pedido', 'category' => 'order', 'letter' => null, 'sequence_key' => 'PEDIDO', 'default_prefix' => 'PED', 'channel' => 'standard', 'impacts_stock' => 0, 'impacts_receivable' => 0, 'requires_customer' => 1, 'sort_order' => 2],
            ['code' => 'REMITO', 'name' => 'Remito', 'category' => 'delivery_note', 'letter' => null, 'sequence_key' => 'REMITO', 'default_prefix' => 'RTO', 'channel' => 'standard', 'impacts_stock' => 1, 'impacts_receivable' => 0, 'requires_customer' => 1, 'sort_order' => 3],
            ['code' => 'FACTURA_A', 'name' => 'Factura A', 'category' => 'invoice', 'letter' => 'A', 'sequence_key' => 'FACTURA_A', 'default_prefix' => 'FCA', 'channel' => 'standard', 'impacts_stock' => 1, 'impacts_receivable' => 1, 'requires_customer' => 1, 'sort_order' => 4],
            ['code' => 'FACTURA_B', 'name' => 'Factura B', 'category' => 'invoice', 'letter' => 'B', 'sequence_key' => 'FACTURA_B', 'default_prefix' => 'FCB', 'channel' => 'standard', 'impacts_stock' => 1, 'impacts_receivable' => 1, 'requires_customer' => 0, 'sort_order' => 5],
            ['code' => 'FACTURA_C', 'name' => 'Factura C', 'category' => 'invoice', 'letter' => 'C', 'sequence_key' => 'FACTURA_C', 'default_prefix' => 'FCC', 'channel' => 'standard', 'impacts_stock' => 1, 'impacts_receivable' => 1, 'requires_customer' => 0, 'sort_order' => 6],
            ['code' => 'FACTURA_M', 'name' => 'Factura M', 'category' => 'invoice', 'letter' => 'M', 'sequence_key' => 'FACTURA_M', 'default_prefix' => 'FCM', 'channel' => 'standard', 'impacts_stock' => 1, 'impacts_receivable' => 1, 'requires_customer' => 1, 'sort_order' => 7],
            ['code' => 'TICKET', 'name' => 'Ticket Consumidor Final', 'category' => 'ticket', 'letter' => null, 'sequence_key' => 'TICKET', 'default_prefix' => 'TCK', 'channel' => 'kiosk', 'impacts_stock' => 1, 'impacts_receivable' => 0, 'requires_customer' => 0, 'sort_order' => 8],
            ['code' => 'NC_A', 'name' => 'Nota de Credito A', 'category' => 'credit_note', 'letter' => 'A', 'sequence_key' => 'NC_A', 'default_prefix' => 'NCA', 'channel' => 'standard', 'impacts_stock' => 0, 'impacts_receivable' => 1, 'requires_customer' => 1, 'sort_order' => 9],
            ['code' => 'NC_B', 'name' => 'Nota de Credito B', 'category' => 'credit_note', 'letter' => 'B', 'sequence_key' => 'NC_B', 'default_prefix' => 'NCB', 'channel' => 'standard', 'impacts_stock' => 0, 'impacts_receivable' => 1, 'requires_customer' => 1, 'sort_order' => 10],
            ['code' => 'NC_C', 'name' => 'Nota de Credito C', 'category' => 'credit_note', 'letter' => 'C', 'sequence_key' => 'NC_C', 'default_prefix' => 'NCC', 'channel' => 'standard', 'impacts_stock' => 0, 'impacts_receivable' => 1, 'requires_customer' => 1, 'sort_order' => 11],
            ['code' => 'ND_A', 'name' => 'Nota de Debito A', 'category' => 'debit_note', 'letter' => 'A', 'sequence_key' => 'ND_A', 'default_prefix' => 'NDA', 'channel' => 'standard', 'impacts_stock' => 0, 'impacts_receivable' => 1, 'requires_customer' => 1, 'sort_order' => 12],
            ['code' => 'ND_B', 'name' => 'Nota de Debito B', 'category' => 'debit_note', 'letter' => 'B', 'sequence_key' => 'ND_B', 'default_prefix' => 'NDB', 'channel' => 'standard', 'impacts_stock' => 0, 'impacts_receivable' => 1, 'requires_customer' => 1, 'sort_order' => 13],
            ['code' => 'ND_C', 'name' => 'Nota de Debito C', 'category' => 'debit_note', 'letter' => 'C', 'sequence_key' => 'ND_C', 'default_prefix' => 'NDC', 'channel' => 'standard', 'impacts_stock' => 0, 'impacts_receivable' => 1, 'requires_customer' => 1, 'sort_order' => 14],
        ];

        $documentTypeIds = [];
        foreach ($definitions as $definition) {
            $existing = $documentTypeModel->where('company_id', $companyId)->where('code', $definition['code'])->first();
            if ($existing) {
                $documentTypeIds[$definition['code']] = $existing['id'];
                continue;
            }
            $documentTypeIds[$definition['code']] = $documentTypeModel->insert(array_merge($definition, ['company_id' => $companyId, 'active' => 1]), true);
        }

        foreach ([['code' => 'PV-STD', 'name' => 'Punto de Venta Principal', 'channel' => 'standard', 'document_code' => 'FACTURA_B'], ['code' => 'PV-KIOSCO', 'name' => 'Punto de Venta Kiosco', 'channel' => 'kiosk', 'document_code' => 'TICKET']] as $definition) {
            if ($pointOfSaleModel->where('company_id', $companyId)->where('code', $definition['code'])->first()) {
                continue;
            }
            $pointOfSaleModel->insert(['company_id' => $companyId, 'branch_id' => null, 'warehouse_id' => $defaultWarehouse['id'] ?? null, 'document_type_id' => $documentTypeIds[$definition['document_code']] ?? null, 'name' => $definition['name'], 'code' => $definition['code'], 'channel' => $definition['channel'], 'active' => 1]);
        }
    }

    private function canConvertDocument(array $sourceSale, array $targetDocument): bool
    {
        if (in_array($sourceSale['status'] ?? '', ['cancelled', 'returned_total'], true)) {
            return false;
        }
        $sourceDocument = ! empty($sourceSale['document_type_id']) ? (new SalesDocumentTypeModel())->find($sourceSale['document_type_id']) : null;
        $sourceCategory = (string) ($sourceDocument['category'] ?? '');
        $targetCategory = (string) ($targetDocument['category'] ?? '');
        return in_array($targetCategory, match ($sourceCategory) {
            'quote' => ['order', 'invoice'],
            'order' => ['delivery_note', 'invoice'],
            'delivery_note' => ['invoice'],
            default => [],
        }, true);
    }

    private function createDraftFromSource(string $companyId, array $sourceSale, array $targetDocument): string
    {
        $pointOfSale = $this->defaultPointOfSale($companyId, $targetDocument['channel'] ?? 'standard');
        $saleNumber = $this->nextSequenceNumber($companyId, $targetDocument['sequence_key'], $targetDocument['default_prefix'] ?: 'DOC');
        $sourceItems = $this->saleItems($sourceSale['id']);
        $sourceDocument = ! empty($sourceSale['document_type_id']) ? (new SalesDocumentTypeModel())->find($sourceSale['document_type_id']) : null;

        $saleId = (new SaleModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $sourceSale['branch_id'] ?? ($this->apiUser()['branch_id'] ?? null),
            'customer_id' => $sourceSale['customer_id'] ?: null,
            'warehouse_id' => $sourceSale['warehouse_id'] ?: null,
            'document_type_id' => $targetDocument['id'],
            'point_of_sale_id' => $pointOfSale['id'] ?? null,
            'source_sale_id' => $sourceSale['id'],
            'sale_number' => $saleNumber,
            'document_code' => $targetDocument['code'],
            'issue_date' => date('Y-m-d H:i:s'),
            'due_date' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'status' => 'draft',
            'reservation_status' => 'none',
            'payment_status' => 'pending',
            'currency_code' => $sourceSale['currency_code'] ?? 'ARS',
            'fiscal_profile' => $sourceSale['fiscal_profile'] ?? null,
            'customer_name_snapshot' => $sourceSale['customer_name_snapshot'] ?? null,
            'customer_document_snapshot' => $sourceSale['customer_document_snapshot'] ?? null,
            'customer_tax_profile' => $sourceSale['customer_tax_profile'] ?? null,
            'sales_agent_id' => $sourceSale['sales_agent_id'] ?? null,
            'sales_zone_id' => $sourceSale['sales_zone_id'] ?? null,
            'sales_condition_id' => $sourceSale['sales_condition_id'] ?? null,
            'price_list_name' => $sourceSale['price_list_name'] ?? null,
            'price_list_id' => $sourceSale['price_list_id'] ?? null,
            'promotion_snapshot' => $sourceSale['promotion_snapshot'] ?? null,
            'pos_mode' => ($targetDocument['channel'] ?? 'standard') === 'kiosk' ? 1 : 0,
            'subtotal' => (float) ($sourceSale['subtotal'] ?? 0),
            'item_discount_total' => (float) ($sourceSale['item_discount_total'] ?? 0),
            'global_discount_total' => (float) ($sourceSale['global_discount_total'] ?? 0),
            'tax_total' => (float) ($sourceSale['tax_total'] ?? 0),
            'total' => (float) ($sourceSale['total'] ?? 0),
            'margin_total' => (float) ($sourceSale['margin_total'] ?? 0),
            'paid_total' => 0,
            'notes' => trim((string) ($sourceSale['notes'] ?? '')) . "\nOrigen: " . trim(($sourceDocument['name'] ?? 'Documento') . ' ' . ($sourceSale['sale_number'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        $items = array_map(static function (array $item): array {
            unset($item['id'], $item['created_at'], $item['updated_at']);
            $item['returned_quantity'] = 0;
            return $item;
        }, $sourceItems);

        $this->persistSaleChildren($saleId, $items, []);

        return $saleId;
    }

    private function nextSequenceNumber(string $companyId, string $documentType, string $defaultPrefix): string
    {
        $model = new VoucherSequenceModel();
        $sequence = $model->where('company_id', $companyId)->where('document_type', $documentType)->first();
        if (! $sequence) {
            $id = $model->insert(['company_id' => $companyId, 'branch_id' => null, 'document_type' => $documentType, 'prefix' => $defaultPrefix, 'current_number' => 1, 'active' => 1], true);
            $sequence = $model->find($id);
        }
        $number = (int) ($sequence['current_number'] ?? 1);
        $formatted = strtoupper(trim((string) ($sequence['prefix'] ?? $defaultPrefix))) . '-' . str_pad((string) $number, 8, '0', STR_PAD_LEFT);
        $model->update($sequence['id'], ['current_number' => $number + 1]);
        return $formatted;
    }
}
