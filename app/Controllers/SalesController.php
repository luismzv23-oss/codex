<?php

namespace App\Controllers;

use App\Libraries\AccountingService;
use App\Libraries\ArcaService;
use App\Libraries\EventBus;
use App\Libraries\CashService;
use App\Models\CashCheckModel;
use App\Models\CashPaymentGatewayModel;
use App\Models\BranchModel;
use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\CustomerModel;
use App\Models\AuditLogModel;
use App\Models\DocumentEventModel;
use App\Models\HardwareLogModel;
use App\Models\IntegrationLogModel;
use App\Models\InventoryMovementModel;
use App\Models\InventoryProductModel;
use App\Models\InventoryReservationModel;
use App\Models\InventorySettingModel;
use App\Models\InventoryStockLevelModel;
use App\Models\InventoryWarehouseModel;
use App\Models\SaleItemModel;
use App\Models\SaleModel;
use App\Models\SalePaymentModel;
use App\Models\SaleReturnItemModel;
use App\Models\SaleReturnModel;
use App\Models\SalesAgentModel;
use App\Models\SalesAuthorizationModel;
use App\Models\SalesCreditFlagModel;
use App\Models\SalesConditionModel;
use App\Models\SalesCommissionModel;
use App\Models\SalesDiscountPolicyModel;
use App\Models\SalesDocumentTypeModel;
use App\Models\SalesArcaEventModel;
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
use CodeIgniter\HTTP\RedirectResponse;
use Dompdf\Dompdf;
use Dompdf\Options;

class SalesController extends BaseController
{
    public function index()
    {
        $context = $this->salesContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $filters = $this->salesFilters();

        return view('sales/index', [
            'pageTitle' => 'Ventas',
            'user' => $this->currentUser(),
            'context' => $context,
            'companies' => $this->salesCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'customers' => $this->customerOptions($context['company']['id']),
            'summary' => $this->salesSummary($context['company']['id'], $filters),
            'filters' => $filters,
            'sales' => $this->salesRows($context['company']['id'], $filters),
            'priceLists' => $this->priceListOptions($context['company']['id']),
            'promotions' => $this->activePromotions($context['company']['id']),
            'receivableSummary' => $this->receivableSummary($context['company']['id']),
        ]);
    }

    public function pos()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/sale', [
            'pageTitle' => 'POS Ventas',
            'context' => $context,
            'sale' => null,
            'saleItems' => [],
            'salePayments' => [],
            'customers' => $this->customerOptions($context['company']['id']),
            'warehouses' => $this->salesWarehouses($context['company']['id']),
            'products' => $this->salesProductCatalog($context['company']['id']),
            'taxes' => $this->taxOptions($context['company']['id']),
            'priceLists' => $this->priceListOptions($context['company']['id']),
            'promotions' => $this->activePromotions($context['company']['id']),
            'documentTypes' => $this->documentTypeOptions($context['company']['id'], 'standard'),
            'pointsOfSale' => $this->pointOfSaleOptions($context['company']['id'], 'standard'),
            'currencyOptions' => $this->companyCurrencyOptions($context['company']['id'], $this->salesSettings($context['company']['id'])['default_currency_code'] ?? ($context['company']['currency_code'] ?? null)),
            'salesSettings' => $this->salesSettings($context['company']['id']),
            'currencyCode' => $context['company']['currency_code'] ?? 'ARS',
            'formAction' => site_url('ventas/pos'),
            'companyId' => $context['company']['id'],
            'isPopup' => false,
        ]);
    }

    public function storePos()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $cashSession = $this->resolveCashSession($companyId, 'pos');
        if (! $cashSession) {
            return redirect()->back()->withInput()->with('error', 'Debes abrir una caja activa para operar POS.');
        }
        $payload = $this->salePayload($companyId);

        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $documentType = $payload['documentType'];
        $saleNumber = $this->nextSequenceNumber($companyId, $documentType['sequence_key'], $documentType['default_prefix'] ?: 'VTA');
        $saleId = (new SaleModel())->insert(array_merge($payload['sale'], [
            'company_id' => $companyId,
            'branch_id' => $this->currentUser()['branch_id'] ?? null,
            'cash_register_id' => $cashSession['cash_register_id'],
            'cash_session_id' => $cashSession['id'],
            'sale_number' => $saleNumber,
            'document_code' => $documentType['code'],
            'created_by' => $this->currentUser()['id'],
            'pos_mode' => 1,
        ]), true);

        $this->persistSaleChildren($saleId, $payload['items'], $payload['payments']);
        $result = $this->confirmSaleTransaction($companyId, $saleId);

        if ($result !== true) {
            return redirect()->to($this->salesRoute('ventas/pos', $companyId))->with('error', $result);
        }

        $this->processArcaAfterConfirmation($companyId, $saleId);
        $this->recordHardwareEvent($companyId, 'pos', 'printer', 'sale_confirmed', 'ok', 'sale', $saleId, [
            'sale_number' => $saleNumber,
            'cash_session_id' => $cashSession['id'],
        ], 'Venta POS confirmada y enviada a mostrador.');

        return redirect()->to($this->salesRoute('ventas', $companyId))->with('message', 'Venta POS confirmada correctamente.');
    }

    public function reports()
    {
        $context = $this->salesContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $filters = $this->reportFilters();
        $report = $this->salesReportData($context['company']['id'], $filters);

        return view('sales/reports', [
            'pageTitle' => 'Reportes de ventas',
            'context' => $context,
            'companies' => $this->salesCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function reportsCsv()
    {
        $context = $this->salesContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $report = $this->salesReportData($context['company']['id'], $this->reportFilters());
        $filename = 'ventas-reportes-' . date('Ymd-His') . '.csv';
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

        $rows[] = [];
        $rows[] = ['Resumen', 'Valor'];
        $rows[] = ['Ventas', (string) ($report['summary']['sales_count'] ?? 0)];
        $rows[] = ['Facturado', number_format((float) ($report['summary']['gross_total'] ?? 0), 2, '.', '')];
        $rows[] = ['Cobrado', number_format((float) ($report['summary']['paid_total'] ?? 0), 2, '.', '')];
        $rows[] = ['Margen', number_format((float) ($report['summary']['margin_total'] ?? 0), 2, '.', '')];
        $rows[] = ['Comisiones', number_format((float) ($report['summary']['commission_total'] ?? 0), 2, '.', '')];

        return $this->csvResponse($rows, $filename);
    }

    public function receivables()
    {
        $context = $this->salesContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];

        return view('sales/receivables', [
            'pageTitle' => 'Cobranzas',
            'context' => $context,
            'companies' => $this->salesCompanies(),
            'selectedCompanyId' => $companyId,
            'receivableSummary' => $this->receivableSummary($companyId),
            'receivables' => $this->receivableRows($companyId),
            'receipts' => $this->receiptRows($companyId),
        ]);
    }

    public function createReceiptForm()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/receipt', [
            'pageTitle' => 'Nuevo recibo',
            'companyId' => $context['company']['id'],
            'receivables' => $this->receivableRows($context['company']['id']),
            'gateways' => (new CashPaymentGatewayModel())->where('company_id', $context['company']['id'])->where('active', 1)->orderBy('name', 'ASC')->findAll(),
            'checks' => (new CashCheckModel())->where('company_id', $context['company']['id'])->orderBy('created_at', 'DESC')->findAll(),
            'currencyCode' => $context['company']['currency_code'] ?? 'ARS',
            'formAction' => site_url('ventas/cobranzas'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeReceipt()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $rows = $this->receiptApplicationsPayload($companyId);
        if ($rows === []) {
            return redirect()->back()->withInput()->with('error', 'Debes aplicar al menos una cobranza a un comprobante pendiente.');
        }

        $customerIds = array_values(array_unique(array_map(static fn(array $row): string => (string) $row['customer_id'], $rows)));
        if (count($customerIds) !== 1) {
            return redirect()->back()->withInput()->with('error', 'El recibo solo puede aplicarse a comprobantes del mismo cliente.');
        }

        $cashSession = (new CashService())->activeSessionForChannel($companyId, 'general');
        if (! $cashSession) {
            return redirect()->back()->withInput()->with('error', 'Debes abrir una caja activa para registrar la cobranza.');
        }

        $total = round(array_sum(array_map(static fn(array $row): float => (float) $row['applied_amount'], $rows)), 2);
        $issueDate = trim((string) $this->request->getPost('issue_date')) ?: date('Y-m-d H:i:s');
        $paymentMethod = trim((string) $this->request->getPost('payment_method')) ?: 'cash';
        $gatewayId = trim((string) $this->request->getPost('gateway_id')) ?: null;
        $cashCheckId = trim((string) $this->request->getPost('cash_check_id')) ?: null;
        $externalReference = trim((string) $this->request->getPost('external_reference')) ?: null;
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
            'gateway_id' => $gatewayId,
            'cash_check_id' => $cashCheckId,
            'total_amount' => $total,
            'reference' => trim((string) $this->request->getPost('reference')),
            'external_reference' => $externalReference,
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
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
            'gateway_id' => $gatewayId,
            'cash_check_id' => $cashCheckId,
            'amount' => $total,
            'reference_type' => 'sales_receipt',
            'reference_id' => $receiptId,
            'reference_number' => $receiptNumber,
            'external_reference' => $externalReference,
            'occurred_at' => $issueDate,
            'notes' => 'Cobranza de cuenta corriente',
            'created_by' => $this->currentUser()['id'],
        ]);

        (new AccountingService())->syncSalesReceipt($companyId, (string) $receiptId, $this->currentUser()['id']);
        EventBus::emit('sale.payment_received', ['company_id' => $companyId, 'payment' => ['amount' => $total], 'receipt_id' => $receiptId]);

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar el recibo.');
        }

        $this->logAudit($companyId, 'sales', 'receipt', $receiptId, 'create', null, (new SalesReceiptModel())->find($receiptId));

        return $this->popupOrRedirect($this->salesRoute('ventas/cobranzas', $companyId), 'Recibo registrado correctamente.');
    }

    public function reportsPdf()
    {
        $context = $this->salesContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $filters = $this->reportFilters();
        $report = $this->salesReportData($context['company']['id'], $filters);

        return $this->renderPdf('sales/pdf/reports', [
            'company' => $context['company'],
            'filters' => $filters,
            'report' => $report,
            'generatedAt' => date('d/m/Y H:i'),
        ], 'reporte-ventas.pdf');
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

    public function configuration()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $settings = $this->salesSettings($context['company']['id']);
        $arcaService = new ArcaService();

        return view('sales/configuration', [
            'pageTitle' => 'Configuracion de Ventas',
            'context' => $context,
            'companies' => $this->salesCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'settings' => $settings,
            'currencies' => (new \App\Models\CurrencyModel())->where('company_id', $context['company']['id'])->where('active', 1)->orderBy('code', 'ASC')->findAll(),
            'arcaServices' => $arcaService->statusSummary($settings),
            'arcaReadiness' => $arcaService->readiness($settings),
            'arcaDiagnostics' => $arcaService->certificateDiagnostics($settings),
            'arcaEnvironments' => $arcaService->environmentDiagnostics($settings),
            'arcaEvents' => $this->arcaEventRows($context['company']['id']),
            'documentTypes' => $this->documentTypeOptions($context['company']['id']),
            'pointsOfSale' => $this->pointOfSaleOptions($context['company']['id']),
            'receivableSummary' => $this->receivableSummary($context['company']['id']),
            'deviceSettings' => (new PosDeviceSettingModel())->where('company_id', $context['company']['id'])->orderBy('channel', 'ASC')->orderBy('device_type', 'ASC')->findAll(),
            'hardwareLogs' => (new HardwareLogModel())->where('company_id', $context['company']['id'])->orderBy('created_at', 'DESC')->findAll(20),
        ]);
    }

    public function editSettingsForm()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/settings', [
            'pageTitle' => 'Configuracion de Ventas',
            'settings' => $this->salesSettings($context['company']['id']),
            'currencyOptions' => $this->companyCurrencyOptions($context['company']['id'], $context['company']['currency_code'] ?? null),
            'companyId' => $context['company']['id'],
            'formAction' => site_url('ventas/configuracion'),
            'isPopup' => $this->isPopupRequest(),
            'arcaReadiness' => (new ArcaService())->readiness($this->salesSettings($context['company']['id'])),
            'arcaDiagnostics' => (new ArcaService())->certificateDiagnostics($this->salesSettings($context['company']['id'])),
        ]);
    }

    public function createDeviceForm()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/device', [
            'pageTitle' => 'Dispositivo de mostrador',
            'companyId' => $context['company']['id'],
            'formAction' => site_url('ventas/dispositivos'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeDevice()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $channel = trim((string) $this->request->getPost('channel')) ?: 'standard';
        $deviceType = trim((string) $this->request->getPost('device_type')) ?: 'printer';
        $deviceName = trim((string) $this->request->getPost('device_name'));
        $deviceCode = trim((string) $this->request->getPost('device_code'));
        if ($deviceName === '' || $deviceCode === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar nombre y codigo del dispositivo.');
        }

        (new PosDeviceSettingModel())->insert([
            'company_id' => $companyId,
            'channel' => $channel,
            'device_type' => $deviceType,
            'device_name' => $deviceName,
            'device_code' => $deviceCode,
            'settings_json' => json_encode([
                'paper_width' => trim((string) $this->request->getPost('paper_width')) ?: '80mm',
                'driver' => trim((string) $this->request->getPost('driver')) ?: 'browser',
                'endpoint' => trim((string) $this->request->getPost('endpoint')),
            ], JSON_UNESCAPED_UNICODE),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        return $this->popupOrRedirect($this->salesRoute('ventas/configuracion', $companyId), 'Dispositivo registrado correctamente.');
    }

    public function diagnoseArca()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
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

        return redirect()->to($this->salesRoute('ventas/configuracion', $context['company']['id']))
            ->with(($diagnostics['bundle_valid'] ?? false) ? 'message' : 'error', $diagnostics['summary'] ?? 'Diagnostico ARCA ejecutado.');
    }

    public function updateSettings()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $settings = $this->salesSettings($context['company']['id']);
        $arcaService = new ArcaService();
        $currencyCode = trim((string) $this->request->getPost('default_currency_code'));
        if ($currencyCode !== '' && ! $this->isAllowedCurrencyCode($currencyCode, $context['company']['id'], $settings['default_currency_code'] ?? null)) {
            return redirect()->back()->withInput()->with('error', 'La moneda por defecto de Ventas debe pertenecer a las monedas activas de la empresa.');
        }

        $settingsPayload = [
            'arca_enabled' => $this->request->getPost('arca_enabled') === '1' ? 1 : 0,
            'arca_cuit' => trim((string) $this->request->getPost('arca_cuit')),
            'certificate_path' => trim((string) $this->request->getPost('certificate_path')),
            'private_key_path' => trim((string) $this->request->getPost('private_key_path')),
            'token_cache_path' => trim((string) $this->request->getPost('token_cache_path')),
        ];
        $validation = $arcaService->validateSettings($settingsPayload, $context['company']['id']);
        if (! $validation['valid']) {
            return redirect()->back()->withInput()->with('error', implode(' ', $validation['errors']));
        }
        $normalized = $validation['settings'];

        (new SalesSettingModel())->update($settings['id'], [
            'invoice_mode_standard_enabled' => $this->request->getPost('invoice_mode_standard_enabled') === '0' ? 0 : 1,
            'invoice_mode_kiosk_enabled' => $this->request->getPost('invoice_mode_kiosk_enabled') === '1' ? 1 : 0,
            'default_currency_code' => $currencyCode ?: null,
            'allow_negative_stock_sales' => $this->request->getPost('allow_negative_stock_sales') === '1' ? 1 : 0,
            'strict_company_currencies' => $this->request->getPost('strict_company_currencies') === '0' ? 0 : 1,
            'arca_enabled' => $this->request->getPost('arca_enabled') === '1' ? 1 : 0,
            'arca_environment' => trim((string) $this->request->getPost('arca_environment')) ?: 'homologacion',
            'arca_cuit' => trim((string) $this->request->getPost('arca_cuit')),
            'arca_iva_condition' => trim((string) $this->request->getPost('arca_iva_condition')),
            'arca_iibb' => trim((string) $this->request->getPost('arca_iibb')),
            'arca_start_activities' => trim((string) $this->request->getPost('arca_start_activities')) ?: null,
            'arca_alias' => trim((string) $this->request->getPost('arca_alias')) ?: null,
            'arca_auto_authorize' => $this->request->getPost('arca_auto_authorize') === '1' ? 1 : 0,
            'arca_certificate_expires_at' => str_replace('T', ' ', trim((string) $this->request->getPost('arca_certificate_expires_at'))) ?: null,
            'point_of_sale_standard' => max(1, (int) $this->request->getPost('point_of_sale_standard')),
            'point_of_sale_kiosk' => max(1, (int) $this->request->getPost('point_of_sale_kiosk')),
            'kiosk_document_label' => trim((string) $this->request->getPost('kiosk_document_label')) ?: 'Ticket Consumidor Final',
            'wsaa_enabled' => $this->request->getPost('wsaa_enabled') === '0' ? 0 : 1,
            'wsfev1_enabled' => $this->request->getPost('wsfev1_enabled') === '0' ? 0 : 1,
            'wsmtxca_enabled' => $this->request->getPost('wsmtxca_enabled') === '1' ? 1 : 0,
            'wsfexv1_enabled' => $this->request->getPost('wsfexv1_enabled') === '1' ? 1 : 0,
            'wsbfev1_enabled' => $this->request->getPost('wsbfev1_enabled') === '1' ? 1 : 0,
            'wsct_enabled' => $this->request->getPost('wsct_enabled') === '1' ? 1 : 0,
            'wsseg_enabled' => $this->request->getPost('wsseg_enabled') === '1' ? 1 : 0,
            'certificate_path' => $normalized['certificate_path'] ?? '',
            'private_key_path' => $normalized['private_key_path'] ?? '',
            'token_cache_path' => $normalized['token_cache_path'] ?? '',
        ]);

        return $this->popupOrRedirect($this->salesRoute('ventas/configuracion', $context['company']['id']), 'Configuracion de Ventas actualizada.');
    }

    public function createAgentForm()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/agent', [
            'pageTitle' => 'Vendedor',
            'formAction' => site_url('ventas/vendedores'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeAgent()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar el nombre del vendedor.');
        }

        $id = (new SalesAgentModel())->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'email' => trim((string) $this->request->getPost('email')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'commission_rate' => (float) $this->request->getPost('commission_rate'),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ], true);

        $this->logAudit($context['company']['id'], 'sales', 'sales_agent', $id, 'create', null, (new SalesAgentModel())->find($id));
        return $this->popupOrRedirect($this->salesRoute('ventas', $context['company']['id']), 'Vendedor registrado correctamente.');
    }

    public function createZoneForm()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/zone', [
            'pageTitle' => 'Zona comercial',
            'formAction' => site_url('ventas/zonas'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeZone()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar el nombre de la zona.');
        }

        $id = (new SalesZoneModel())->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'description' => trim((string) $this->request->getPost('description')),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ], true);

        $this->logAudit($context['company']['id'], 'sales', 'sales_zone', $id, 'create', null, (new SalesZoneModel())->find($id));
        return $this->popupOrRedirect($this->salesRoute('ventas', $context['company']['id']), 'Zona comercial registrada correctamente.');
    }

    public function createConditionForm()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/condition', [
            'pageTitle' => 'Condicion comercial',
            'formAction' => site_url('ventas/condiciones'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeCondition()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $name = trim((string) $this->request->getPost('name'));
        $code = trim((string) $this->request->getPost('code'));
        if ($name === '' || $code === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar nombre y codigo de la condicion.');
        }

        $id = (new SalesConditionModel())->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'code' => strtoupper($code),
            'payment_terms_days' => max(0, (int) $this->request->getPost('payment_terms_days')),
            'credit_limit' => (float) $this->request->getPost('credit_limit'),
            'requires_invoice' => $this->request->getPost('requires_invoice') === '1' ? 1 : 0,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ], true);

        $this->logAudit($context['company']['id'], 'sales', 'sales_condition', $id, 'create', null, (new SalesConditionModel())->find($id));
        return $this->popupOrRedirect($this->salesRoute('ventas', $context['company']['id']), 'Condicion comercial registrada correctamente.');
    }

    public function testArcaConnection()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $settings = $this->salesSettings($context['company']['id']);
        $result = (new ArcaService())->testAuthentication($settings);
        $this->recordArcaEvent($context['company']['id'], null, $result['service_slug'] ?? 'wsaa', 'test_auth', $result, ['settings' => $settings]);

        if (($result['status'] ?? '') === 'ok') {
            (new SalesSettingModel())->update($settings['id'], [
                'arca_last_wsaa_at' => date('Y-m-d H:i:s'),
                'arca_last_ticket_expires_at' => $result['ticket_expires_at'] ?? null,
                'arca_last_sync_at' => date('Y-m-d H:i:s'),
                'arca_last_error' => null,
            ]);
            $this->logIntegration($context['company']['id'], 'arca', 'wsaa', 'settings', $settings['id'], 'ok', ['settings_id' => $settings['id']], $result, $result['message'] ?? null);

            return redirect()->to($this->salesRoute('ventas/configuracion', $context['company']['id']))->with('message', $result['message']);
        }

        (new SalesSettingModel())->update($settings['id'], [
            'arca_last_sync_at' => date('Y-m-d H:i:s'),
            'arca_last_error' => $result['message'] ?? 'Error de autenticacion ARCA',
        ]);
        $this->logIntegration($context['company']['id'], 'arca', 'wsaa', 'settings', $settings['id'], 'error', ['settings_id' => $settings['id']], $result, $result['message'] ?? null);

        return redirect()->to($this->salesRoute('ventas/configuracion', $context['company']['id']))->with('error', $result['message']);
    }

    public function authorizeArca(string $id)
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Comprobante no disponible.');
        }

        $result = $this->authorizeSaleInArca($context['company']['id'], $sale);
        $flash = in_array($result['status'] ?? '', ['Authorizado', 'No Aplica'], true) ? 'message' : 'error';

        return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with($flash, $result['message'] ?? 'Operacion fiscal procesada.');
    }

    public function consultArca(string $id)
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Comprobante no disponible.');
        }

        $result = $this->consultSaleInArca($context['company']['id'], $sale);
        return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('message', $result['message'] ?? 'Estado fiscal consultado.');
    }

    public function kiosk()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/kiosk', [
            'pageTitle' => 'Ticket Kiosco',
            'products' => $this->salesProductCatalog($context['company']['id']),
            'warehouses' => $this->salesWarehouses($context['company']['id']),
            'currencyOptions' => $this->companyCurrencyOptions($context['company']['id'], $this->salesSettings($context['company']['id'])['default_currency_code'] ?? ($context['company']['currency_code'] ?? null)),
            'companyId' => $context['company']['id'],
            'formAction' => site_url('ventas/kiosco'),
            'isPopup' => $this->isPopupRequest(),
            'settings' => $this->salesSettings($context['company']['id']),
            'documentReference' => $this->previewDocumentReference($context['company']['id'], 'kiosk'),
            'documentType' => $this->defaultDocumentType($context['company']['id'], 'kiosk'),
            'pointOfSale' => $this->defaultPointOfSale($context['company']['id'], 'kiosk'),
        ]);
    }

    public function storeKiosk()
    {
        $context = $this->salesContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $cashSession = $this->resolveCashSession($companyId, 'kiosk');
        if (! $cashSession) {
            return redirect()->back()->withInput()->with('error', 'Debes abrir una caja activa para operar Kiosco.');
        }
        $consumer = $this->ensureConsumerFinalCustomer($companyId);
        $kioskDocumentType = $this->defaultDocumentType($companyId, 'kiosk');
        $documentReference = $this->nextSequenceNumber($companyId, $kioskDocumentType['sequence_key'], $kioskDocumentType['default_prefix'] ?: 'TCK');

        $payload = $this->salePayload($companyId, [
            'customer_id' => $consumer['id'] ?? null,
            'pos_mode' => '1',
            'price_list_name' => 'KIOSCO',
            'document_type_id' => $kioskDocumentType['id'] ?? null,
            'point_of_sale_id' => $this->defaultPointOfSale($companyId, 'kiosk')['id'] ?? null,
            'payments' => [
                0 => [
                    'reference' => $documentReference,
                ],
            ],
        ]);

        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $saleId = (new SaleModel())->insert(array_merge($payload['sale'], [
            'company_id' => $companyId,
            'branch_id' => $this->currentUser()['branch_id'] ?? null,
            'cash_register_id' => $cashSession['cash_register_id'],
            'cash_session_id' => $cashSession['id'],
            'sale_number' => $documentReference,
            'document_code' => $payload['documentType']['code'] ?? 'TICKET',
            'created_by' => $this->currentUser()['id'],
            'pos_mode' => 1,
        ]), true);

        $this->persistSaleChildren($saleId, $payload['items'], $payload['payments']);
        $result = $this->confirmSaleTransaction($companyId, $saleId);

        if ($result !== true) {
            return redirect()->to($this->salesRoute('ventas/kiosco', $companyId))->with('error', $result);
        }

        $this->processArcaAfterConfirmation($companyId, $saleId);
        $this->recordHardwareEvent($companyId, 'kiosk', 'printer', 'sale_confirmed', 'ok', 'sale', $saleId, [
            'sale_number' => $documentReference,
            'cash_session_id' => $cashSession['id'],
        ], 'Venta kiosco confirmada y lista para impresion.');

        return redirect()->to($this->salesRoute('ventas', $companyId))->with('message', 'Factura kiosco registrada correctamente.');
    }

    public function createPriceListForm()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/price_list', [
            'pageTitle' => 'Lista de precios',
            'formAction' => site_url('ventas/listas-precio'),
            'products' => $this->salesProductCatalog($context['company']['id']),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storePriceList()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar el nombre de la lista.');
        }

        $model = new SalesPriceListModel();
        $isDefault = $this->request->getPost('is_default') === '1' ? 1 : 0;
        if ($isDefault === 1) {
            $model->where('company_id', $context['company']['id'])->set(['is_default' => 0])->update();
        }

        $priceListId = $model->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'description' => trim((string) $this->request->getPost('description')),
            'is_default' => $isDefault,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ], true);

        $itemModel = new SalesPriceListItemModel();
        foreach ((array) $this->request->getPost('items') as $item) {
            $productId = trim((string) ($item['product_id'] ?? ''));
            $price = (float) ($item['price'] ?? 0);
            if ($productId === '' || $price <= 0) {
                continue;
            }
            $itemModel->insert([
                'price_list_id' => $priceListId,
                'product_id' => $productId,
                'price' => $price,
            ]);
        }

        return $this->popupOrRedirect($this->salesRoute('ventas', $context['company']['id']), 'Lista de precios registrada correctamente.');
    }

    public function createPromotionForm()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/promotion', [
            'pageTitle' => 'Promocion',
            'formAction' => site_url('ventas/promociones'),
            'products' => $this->salesProductCatalog($context['company']['id']),
            'gateways' => (new CashPaymentGatewayModel())->where('company_id', $context['company']['id'])->where('active', 1)->orderBy('name', 'ASC')->findAll(),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storePromotion()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar el nombre de la promocion.');
        }

        $promotionId = (new SalesPromotionModel())->insert([
            'company_id' => $context['company']['id'],
            'name' => $name,
            'description' => trim((string) $this->request->getPost('description')),
            'promotion_type' => trim((string) $this->request->getPost('promotion_type')) ?: 'percent',
            'scope' => trim((string) $this->request->getPost('scope')) ?: 'selected',
            'value' => (float) $this->request->getPost('value'),
            'trigger_quantity' => (float) $this->request->getPost('trigger_quantity'),
            'bonus_quantity' => (float) $this->request->getPost('bonus_quantity'),
            'bonus_product_id' => trim((string) $this->request->getPost('bonus_product_id')) ?: null,
            'payment_method' => trim((string) $this->request->getPost('payment_method')) ?: null,
            'bundle_price' => (float) $this->request->getPost('bundle_price'),
            'start_date' => trim((string) $this->request->getPost('start_date')) ?: null,
            'end_date' => trim((string) $this->request->getPost('end_date')) ?: null,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ], true);

        if (($this->request->getPost('scope') ?? 'selected') === 'selected') {
            $itemModel = new SalesPromotionItemModel();
            foreach ((array) $this->request->getPost('product_ids') as $productId) {
                $productId = trim((string) $productId);
                if ($productId === '') {
                    continue;
                }
                $itemModel->insert([
                    'promotion_id' => $promotionId,
                    'product_id' => $productId,
                ]);
            }
        }

        return $this->popupOrRedirect($this->salesRoute('ventas', $context['company']['id']), 'Promocion registrada correctamente.');
    }

    public function createCustomerForm()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('sales/forms/customer', [
            'pageTitle' => 'Cliente',
            'customer' => null,
            'formAction' => site_url('ventas/clientes'),
            'companyId' => $context['company']['id'],
            'branches' => $this->branchOptions($context['company']['id']),
            'priceLists' => $this->priceListOptions($context['company']['id']),
            'agents' => $this->salesAgentOptions($context['company']['id']),
            'zones' => $this->salesZoneOptions($context['company']['id']),
            'conditions' => $this->salesConditionOptions($context['company']['id']),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeCustomer()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar el nombre del cliente.');
        }

        $priceListId = trim((string) $this->request->getPost('price_list_id')) ?: null;
        $priceList = $priceListId ? (new SalesPriceListModel())->find($priceListId) : null;
        $conditionId = trim((string) $this->request->getPost('sales_condition_id')) ?: null;
        $condition = $conditionId ? (new SalesConditionModel())->where('company_id', $context['company']['id'])->find($conditionId) : null;

        $customerId = (new CustomerModel())->insert([
            'company_id' => $context['company']['id'],
            'branch_id' => trim((string) $this->request->getPost('branch_id')) ?: null,
            'name' => $name,
            'billing_name' => trim((string) $this->request->getPost('billing_name')) ?: $name,
            'document_number' => trim((string) $this->request->getPost('document_number')),
            'document_type' => trim((string) $this->request->getPost('document_type')) ?: 'DNI',
            'tax_profile' => trim((string) $this->request->getPost('tax_profile')) ?: 'consumidor_final',
            'vat_condition' => trim((string) $this->request->getPost('vat_condition')),
            'email' => trim((string) $this->request->getPost('email')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'address' => trim((string) $this->request->getPost('address')),
            'price_list_name' => $priceList['name'] ?? trim((string) $this->request->getPost('price_list_name')),
            'price_list_id' => $priceListId,
            'sales_agent_id' => trim((string) $this->request->getPost('sales_agent_id')) ?: null,
            'sales_zone_id' => trim((string) $this->request->getPost('sales_zone_id')) ?: null,
            'sales_condition_id' => $conditionId,
            'credit_limit' => $condition ? (float) ($condition['credit_limit'] ?? 0) : (float) $this->request->getPost('credit_limit'),
            'custom_discount_rate' => (float) $this->request->getPost('custom_discount_rate'),
            'payment_terms_days' => $condition ? max(0, (int) ($condition['payment_terms_days'] ?? 0)) : max(0, (int) $this->request->getPost('payment_terms_days')),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ], true);

        $this->logAudit($context['company']['id'], 'sales', 'customer', $customerId, 'create', null, (new CustomerModel())->find($customerId));

        return $this->popupOrRedirect($this->salesRoute('ventas', $context['company']['id']), 'Cliente registrado correctamente.');
    }

    public function create()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sourceSale = null;
        $sourceSaleId = trim((string) $this->request->getGet('source_sale_id'));
        if ($sourceSaleId !== '') {
            $sourceSale = $this->ownedSale($context['company']['id'], $sourceSaleId);
        }

        return view('sales/forms/sale', [
            'pageTitle' => 'Nueva venta',
            'context' => $context,
            'companies' => $this->salesCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'sale' => null,
            'saleItems' => [],
            'salePayments' => [],
            'customers' => $this->customerOptions($context['company']['id']),
            'warehouses' => $this->salesWarehouses($context['company']['id']),
            'products' => $this->salesProductCatalog($context['company']['id']),
            'taxes' => $this->taxOptions($context['company']['id']),
            'priceLists' => $this->priceListOptions($context['company']['id']),
            'promotions' => $this->activePromotions($context['company']['id']),
            'agents' => $this->salesAgentOptions($context['company']['id']),
            'zones' => $this->salesZoneOptions($context['company']['id']),
            'conditions' => $this->salesConditionOptions($context['company']['id']),
            'documentTypes' => $this->documentTypeOptions($context['company']['id'], 'standard'),
            'pointsOfSale' => $this->pointOfSaleOptions($context['company']['id'], 'standard'),
            'currencyOptions' => $this->companyCurrencyOptions($context['company']['id'], $this->salesSettings($context['company']['id'])['default_currency_code'] ?? ($context['company']['currency_code'] ?? null)),
            'salesSettings' => $this->salesSettings($context['company']['id']),
            'currencyCode' => $context['company']['currency_code'] ?? 'ARS',
            'formAction' => site_url('ventas'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
            'sourceSale' => $sourceSale,
        ]);
    }

    public function store()
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $payload = $this->salePayload($companyId);

        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $documentType = $payload['documentType'];
        $saleNumber = $this->nextSequenceNumber($companyId, $documentType['sequence_key'], $documentType['default_prefix'] ?: 'VTA');
        $saleId = (new SaleModel())->insert(array_merge($payload['sale'], [
            'company_id' => $companyId,
            'branch_id' => $this->currentUser()['branch_id'] ?? null,
            'sale_number' => $saleNumber,
            'document_code' => $documentType['code'],
            'created_by' => $this->currentUser()['id'],
        ]), true);

        $this->persistSaleChildren($saleId, $payload['items'], $payload['payments']);
        $this->logAudit($companyId, 'sales', 'sale', $saleId, 'create_draft', null, (new SaleModel())->find($saleId));
        $this->logDocumentEvent($companyId, 'sales', 'sale', $saleId, 'draft_created', ['sale_number' => $saleNumber]);

        return $this->popupOrRedirect($this->salesRoute('ventas', $companyId), 'Venta guardada como borrador.');
    }

    public function edit(string $id)
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Venta no disponible.');
        }

        if (($sale['status'] ?? 'draft') !== 'draft') {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Solo los borradores pueden editarse.');
        }

        return view('sales/forms/sale', [
            'pageTitle' => 'Editar venta',
            'context' => $context,
            'companies' => $this->salesCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'sale' => $sale,
            'saleItems' => $this->saleItems($id),
            'salePayments' => $this->salePayments($id),
            'customers' => $this->customerOptions($context['company']['id']),
            'warehouses' => $this->salesWarehouses($context['company']['id']),
            'products' => $this->salesProductCatalog($context['company']['id']),
            'taxes' => $this->taxOptions($context['company']['id']),
            'priceLists' => $this->priceListOptions($context['company']['id']),
            'promotions' => $this->activePromotions($context['company']['id']),
            'agents' => $this->salesAgentOptions($context['company']['id']),
            'zones' => $this->salesZoneOptions($context['company']['id']),
            'conditions' => $this->salesConditionOptions($context['company']['id']),
            'documentTypes' => $this->documentTypeOptions($context['company']['id'], ((int) ($sale['pos_mode'] ?? 0) === 1) ? 'kiosk' : 'standard'),
            'pointsOfSale' => $this->pointOfSaleOptions($context['company']['id'], ((int) ($sale['pos_mode'] ?? 0) === 1) ? 'kiosk' : 'standard'),
            'currencyOptions' => $this->companyCurrencyOptions($context['company']['id'], $sale['currency_code'] ?? ($context['company']['currency_code'] ?? null)),
            'salesSettings' => $this->salesSettings($context['company']['id']),
            'currencyCode' => $sale['currency_code'] ?? ($context['company']['currency_code'] ?? 'ARS'),
            'formAction' => site_url('ventas/' . $id . '/actualizar'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
            'sourceSale' => ! empty($sale['source_sale_id']) ? $this->ownedSale($context['company']['id'], (string) $sale['source_sale_id']) : null,
        ]);
    }

    public function convert(string $id, string $targetCode)
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sourceSale = $this->ownedSale($context['company']['id'], $id);
        if (! $sourceSale) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Documento origen no disponible.');
        }

        $targetDocument = (new SalesDocumentTypeModel())
            ->where('company_id', $context['company']['id'])
            ->where('code', strtoupper(trim($targetCode)))
            ->where('active', 1)
            ->first();

        if (! $targetDocument) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'El comprobante destino no esta disponible.');
        }

        if (! $this->canConvertDocument($sourceSale, $targetDocument)) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'La conversion solicitada no es valida para este documento.');
        }

        $targetSaleId = $this->createDraftFromSource($context['company']['id'], $sourceSale, $targetDocument);

        return redirect()->to($this->salesRoute('ventas/' . $targetSaleId . '/editar', $context['company']['id']))->with('message', 'Documento generado a partir del origen seleccionado.');
    }

    public function update(string $id)
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Venta no disponible.');
        }

        if (($sale['status'] ?? 'draft') !== 'draft') {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Solo los borradores pueden editarse.');
        }

        $payload = $this->salePayload($context['company']['id']);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        (new SaleModel())->update($id, $payload['sale']);
        $this->replaceSaleChildren($id, $payload['items'], $payload['payments']);
        $this->logAudit($context['company']['id'], 'sales', 'sale', $id, 'update_draft', $sale, (new SaleModel())->find($id));
        $this->logDocumentEvent($context['company']['id'], 'sales', 'sale', $id, 'draft_updated', ['sale_number' => $sale['sale_number'] ?? null]);

        return $this->popupOrRedirect($this->salesRoute('ventas', $context['company']['id']), 'Venta actualizada correctamente.');
    }

    public function confirm(string $id)
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Venta no disponible.');
        }

        if (($sale['status'] ?? '') !== 'draft') {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Solo los borradores pueden confirmarse.');
        }

        $result = $this->confirmSaleTransaction($context['company']['id'], $id, $sale);
        if ($result !== true) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', $result);
        }

        $this->processArcaAfterConfirmation($context['company']['id'], $id);
        $this->logAudit($context['company']['id'], 'sales', 'sale', $id, 'confirm', $sale, (new SaleModel())->find($id));
        $this->logDocumentEvent($context['company']['id'], 'sales', 'sale', $id, 'confirmed', ['sale_number' => $sale['sale_number'] ?? null]);

        return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('message', 'Venta confirmada correctamente.');
    }

    public function cancel(string $id)
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Venta no disponible.');
        }

        if (in_array($sale['status'], ['cancelled', 'returned_total'], true)) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'La venta ya no puede cancelarse.');
        }

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
            'cancelled_by' => $this->currentUser()['id'],
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancellation_reason' => trim((string) $this->request->getPost('cancellation_reason')) ?: 'Cancelacion manual',
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'No se pudo cancelar la venta.');
        }

        $this->syncReceivableForSale($id);
        $this->syncSaleCommission($context['company']['id'], $id);
        (new AccountingService())->syncSale($context['company']['id'], $id, $this->currentUser()['id']);
        EventBus::emit('sale.cancelled', ['company_id' => $context['company']['id'], 'sale' => (new SaleModel())->find($id)]);
        $this->logAudit($context['company']['id'], 'sales', 'sale', $id, 'cancel', $sale, (new SaleModel())->find($id));
        $this->logDocumentEvent($context['company']['id'], 'sales', 'sale', $id, 'cancelled', ['sale_number' => $sale['sale_number'] ?? null]);

        return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('message', 'Venta cancelada correctamente.');
    }

    public function createReturnForm(string $id)
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale || ! in_array($sale['status'], ['confirmed', 'returned_partial'], true)) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'La venta no admite devoluciones.');
        }

        return view('sales/forms/return', [
            'pageTitle' => 'Registrar devolucion',
            'sale' => $sale,
            'saleItems' => $this->saleItems($id),
            'warehouses' => $this->salesWarehouses($context['company']['id']),
            'formAction' => site_url('ventas/' . $id . '/devolucion'),
            'companyId' => $context['company']['id'],
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeReturn(string $id)
    {
        $context = $this->salesContext('manage');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale || ! in_array($sale['status'], ['confirmed', 'returned_partial'], true)) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'La venta no admite devoluciones.');
        }

        $warehouseId = trim((string) $this->request->getPost('warehouse_id')) ?: ($sale['warehouse_id'] ?: null);
        if (! $warehouseId || ! $this->ownedWarehouse($context['company']['id'], $warehouseId)) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar un deposito valido para reingresar stock.');
        }

        $items = $this->parseReturnItems($id);
        if ($items === []) {
            return redirect()->back()->withInput()->with('error', 'Debes indicar al menos una cantidad a devolver.');
        }

        $returnNumber = $this->nextSequenceNumber($context['company']['id'], 'NC', 'NC');
        $db = db_connect();
        $db->transStart();

        $returnTotal = 0.0;
        $returnId = (new SaleReturnModel())->insert([
            'sale_id' => $id,
            'warehouse_id' => $warehouseId,
            'return_number' => $returnNumber,
            'status' => 'confirmed',
            'credit_note_number' => $returnNumber,
            'total' => 0,
            'reason' => trim((string) $this->request->getPost('reason')),
            'created_by' => $this->currentUser()['id'],
        ], true);

        foreach ($items as $item) {
            $lineTotal = (float) $item['unit_price'] * (float) $item['quantity'];
            $returnTotal += $lineTotal;
            (new SaleReturnItemModel())->insert([
                'sale_return_id' => $returnId,
                'sale_item_id' => $item['sale_item_id'],
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $lineTotal,
                'reason' => $item['reason'],
            ]);
            (new SaleItemModel())->update($item['sale_item_id'], [
                'returned_quantity' => (float) $item['already_returned'] + (float) $item['quantity'],
            ]);
            $this->applyStockDelta($context['company']['id'], $item['product_id'], $warehouseId, (float) $item['quantity']);
            $this->registerInventoryMovement([
                'company_id' => $context['company']['id'],
                'product_id' => $item['product_id'],
                'movement_type' => 'ingreso',
                'quantity' => (float) $item['quantity'],
                'unit_cost' => (float) $item['unit_cost'],
                'total_cost' => ((float) $item['unit_cost']) * ((float) $item['quantity']),
                'source_warehouse_id' => null,
                'destination_warehouse_id' => $warehouseId,
                'performed_by' => $this->currentUser()['id'],
                'occurred_at' => date('Y-m-d H:i:s'),
                'reason' => 'DEVOLUCION VENTA',
                'source_document' => $sale['sale_number'],
                'notes' => 'Devolucion asociada a venta',
            ]);
        }

        (new SaleReturnModel())->update($returnId, ['total' => $returnTotal]);
        $this->syncSaleReturnStatus($id);
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'No se pudo registrar la devolucion.');
        }

        $this->refreshSalePaymentStatus($id);
        $this->syncReceivableForSale($id);
        $this->syncSaleCommission($context['company']['id'], $id);
        (new AccountingService())->syncSaleReturn($context['company']['id'], (string) $returnId, $this->currentUser()['id']);
        $this->logAudit($context['company']['id'], 'sales', 'sale_return', $returnId, 'create', null, (new SaleReturnModel())->find($returnId));
        $this->logDocumentEvent($context['company']['id'], 'sales', 'sale_return', $returnId, 'registered', ['sale_id' => $id]);

        return $this->popupOrRedirect($this->salesRoute('ventas', $context['company']['id']), 'Devolucion registrada correctamente.');
    }

    public function pdf(string $id)
    {
        $context = $this->salesContext('view');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sale = $this->ownedSale($context['company']['id'], $id);
        if (! $sale) {
            return redirect()->to($this->salesRoute('ventas', $context['company']['id']))->with('error', 'Venta no disponible.');
        }

        return $this->renderPdf('sales/pdf/sale', [
            'company' => $context['company'],
            'sale' => $sale,
            'customer' => $sale['customer_id'] ? (new CustomerModel())->find($sale['customer_id']) : null,
            'items' => $this->saleItems($id),
            'payments' => $this->salePayments($id),
            'returns' => $this->saleReturns($id),
            'generatedAt' => date('d/m/Y H:i'),
        ], 'venta-' . $sale['sale_number'] . '.pdf');
    }

    private function salesContext(string $requiredAccess = 'view')
    {
        $companyId = $this->resolveSalesCompanyId();
        if (! $companyId) {
            return redirect()->to('/sistemas')->with('error', 'Debes seleccionar una empresa para operar Ventas.');
        }

        $company = (new CompanyModel())->find($companyId);
        if (! $company) {
            return redirect()->to('/sistemas')->with('error', 'La empresa seleccionada no existe.');
        }

        $system = (new SystemModel())->where('slug', 'ventas')->first();
        if (! $system || (int) ($system['active'] ?? 0) !== 1) {
            return redirect()->to('/sistemas')->with('error', 'El sistema Ventas no esta disponible.');
        }

        $accessLevel = 'view';
        $companyAssignment = (new CompanySystemModel())
            ->where('company_id', $companyId)
            ->where('system_id', $system['id'])
            ->where('active', 1)
            ->first();

        if (! $this->isSuperadmin()) {
            if (! $companyAssignment) {
                return redirect()->to('/sistemas')->with('error', 'La empresa no tiene Ventas asignado.');
            }

            $userAssignment = (new UserSystemModel())
                ->where('company_id', $companyId)
                ->where('user_id', $this->currentUser()['id'] ?? '')
                ->where('system_id', $system['id'])
                ->where('active', 1)
                ->first();

            if (! $userAssignment) {
                return redirect()->to('/sistemas')->with('error', 'Tu usuario no tiene acceso activo a Ventas.');
            }

            $accessLevel = $userAssignment['access_level'] ?? 'view';
        }

        if ($requiredAccess === 'manage' && ! $this->isSuperadmin() && $accessLevel !== 'manage') {
            return redirect()->to($this->salesRoute('ventas', $companyId))->with('error', 'Tu usuario solo tiene acceso de consulta en Ventas.');
        }

        $this->ensureSalesDefaults($companyId);

        return [
            'company' => $company,
            'system' => $system,
            'access_level' => $accessLevel,
            'canManage' => $this->isSuperadmin() || $accessLevel === 'manage',
        ];
    }

    private function resolveSalesCompanyId(): ?string
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

    private function salesCompanies(): array
    {
        if (! $this->isSuperadmin()) {
            return [];
        }

        return (new CompanyModel())->orderBy('name', 'ASC')->findAll();
    }

    private function salesRoute(string $path, ?string $companyId): string
    {
        if (! $this->isSuperadmin() || ! $companyId) {
            return site_url($path);
        }

        return site_url($path . '?company_id=' . $companyId);
    }

    private function ensureSalesDefaults(string $companyId): void
    {
        $branch = (new BranchModel())->where('company_id', $companyId)->where('code', 'MAIN')->first();
        $sequenceModel = new VoucherSequenceModel();

        foreach ([['document_type' => 'VENTA', 'prefix' => 'VTA'], ['document_type' => 'FACTURA', 'prefix' => 'FAC'], ['document_type' => 'TICKET', 'prefix' => 'TCK'], ['document_type' => 'NC', 'prefix' => 'NC'], ['document_type' => 'RECIBO', 'prefix' => 'REC']] as $row) {
            if (! $sequenceModel->where('company_id', $companyId)->where('document_type', $row['document_type'])->first()) {
                $sequenceModel->insert([
                    'company_id' => $companyId,
                    'branch_id' => $branch['id'] ?? null,
                    'document_type' => $row['document_type'],
                    'prefix' => $row['prefix'],
                    'current_number' => 1,
                    'active' => 1,
                ]);
            }
        }

        $customerModel = new CustomerModel();
        if (! $customerModel->where('company_id', $companyId)->where('name', 'Consumidor Final')->first()) {
            $customerModel->insert([
                'company_id' => $companyId,
                'branch_id' => $branch['id'] ?? null,
                'name' => 'Consumidor Final',
                'billing_name' => 'Consumidor Final',
                'document_number' => 'CF',
                'document_type' => 'DNI',
                'tax_profile' => 'consumidor_final',
                'vat_condition' => 'Consumidor Final',
                'price_list_name' => 'Lista General',
                'credit_limit' => 0,
                'custom_discount_rate' => 0,
                'payment_terms_days' => 0,
                'active' => 1,
            ]);
        }

        $priceListModel = new SalesPriceListModel();
        if (! $priceListModel->where('company_id', $companyId)->where('is_default', 1)->first()) {
            $priceListModel->insert([
                'company_id' => $companyId,
                'name' => 'Lista General',
                'description' => 'Lista base del sistema de ventas.',
                'is_default' => 1,
                'active' => 1,
            ]);
        }

        $this->salesSettings($companyId);
        $this->ensureSalesDocumentDefaults($companyId, $branch['id'] ?? null);
    }

    private function ensureSalesDocumentDefaults(string $companyId, ?string $branchId): void
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

                $documentTypeIds[$definition['code']] = $documentTypeModel->insert(array_merge($definition, [
                    'company_id' => $companyId,
                    'active' => 1,
                ]), true);
        }

        $pointDefinitions = [
            ['code' => 'PV-STD', 'name' => 'Punto de Venta Principal', 'channel' => 'standard', 'document_code' => 'FACTURA_B'],
            ['code' => 'PV-KIOSCO', 'name' => 'Punto de Venta Kiosco', 'channel' => 'kiosk', 'document_code' => 'TICKET'],
        ];

        foreach ($pointDefinitions as $definition) {
            if ($pointOfSaleModel->where('company_id', $companyId)->where('code', $definition['code'])->first()) {
                continue;
            }

            $pointOfSaleModel->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $defaultWarehouse['id'] ?? null,
                'document_type_id' => $documentTypeIds[$definition['document_code']] ?? null,
                'name' => $definition['name'],
                'code' => $definition['code'],
                'channel' => $definition['channel'],
                'active' => 1,
            ]);
        }
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
        $defaults = [
            'kiosk' => 'TICKET',
            'standard' => 'FACTURA_B',
        ];

        if (isset($defaults[$channel])) {
            $match = (new SalesDocumentTypeModel())
                ->where('company_id', $companyId)
                ->where('code', $defaults[$channel])
                ->where('active', 1)
                ->first();
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

    private function previewDocumentReference(string $companyId, string $channel = 'standard'): string
    {
        $documentType = $this->defaultDocumentType($companyId, $channel);
        if (! $documentType) {
            return '';
        }

        return $this->previewSequenceNumber($companyId, $documentType['sequence_key'], $documentType['default_prefix'] ?: 'DOC');
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
            'branch_id' => $sourceSale['branch_id'] ?? ($this->currentUser()['branch_id'] ?? null),
            'customer_id' => $sourceSale['customer_id'] ?: null,
            'warehouse_id' => $sourceSale['warehouse_id'] ?: null,
            'document_type_id' => $targetDocument['id'],
            'point_of_sale_id' => $pointOfSale['id'] ?? null,
            'sales_agent_id' => $sourceSale['sales_agent_id'] ?? null,
            'sales_zone_id' => $sourceSale['sales_zone_id'] ?? null,
            'sales_condition_id' => $sourceSale['sales_condition_id'] ?? null,
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
            'price_list_name' => $sourceSale['price_list_name'] ?? null,
            'price_list_id' => $sourceSale['price_list_id'] ?? null,
            'promotion_snapshot' => $sourceSale['promotion_snapshot'] ?? null,
            'pos_mode' => ($targetDocument['channel'] ?? 'standard') === 'kiosk' ? 1 : 0,
            'subtotal' => (float) ($sourceSale['subtotal'] ?? 0),
            'item_discount_total' => (float) ($sourceSale['item_discount_total'] ?? 0),
            'global_discount_total' => (float) ($sourceSale['global_discount_total'] ?? 0),
            'tax_total' => (float) ($sourceSale['tax_total'] ?? 0),
            'margin_total' => (float) ($sourceSale['margin_total'] ?? 0),
            'total' => (float) ($sourceSale['total'] ?? 0),
            'paid_total' => 0,
            'notes' => trim((string) ($sourceSale['notes'] ?? '')) . "\nOrigen: " . trim(($sourceDocument['name'] ?? 'Documento') . ' ' . ($sourceSale['sale_number'] ?? '')),
            'created_by' => $this->currentUser()['id'],
        ], true);

        $items = array_map(static function (array $item): array {
            unset($item['id'], $item['created_at'], $item['updated_at']);
            $item['returned_quantity'] = 0;
            return $item;
        }, $sourceItems);

        $this->persistSaleChildren($saleId, $items, []);

        return $saleId;
    }

    private function salesFilters(): array
    {
        return [
            'status' => trim((string) $this->request->getGet('status')),
            'customer_id' => trim((string) $this->request->getGet('customer_id')),
            'date_from' => trim((string) $this->request->getGet('date_from')),
            'date_to' => trim((string) $this->request->getGet('date_to')),
        ];
    }

    private function reportFilters(): array
    {
        return [
            'date_from' => trim((string) $this->request->getGet('date_from')) ?: date('Y-m-01'),
            'date_to' => trim((string) $this->request->getGet('date_to')) ?: date('Y-m-d'),
            'customer_id' => trim((string) $this->request->getGet('customer_id')),
            'warehouse_id' => trim((string) $this->request->getGet('warehouse_id')),
        ];
    }

    private function salesSummary(string $companyId, array $filters): array
    {
        $rows = $this->salesRows($companyId, $filters);
        $receivables = $this->receivableSummary($companyId);

        return [
            'drafts' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'draft')),
            'confirmed' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'confirmed')),
            'cancelled' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'cancelled')),
            'returned' => count(array_filter($rows, static fn(array $row): bool => in_array($row['status'], ['returned_partial', 'returned_total'], true))),
            'standard' => count(array_filter($rows, static fn(array $row): bool => (int) ($row['pos_mode'] ?? 0) === 0)),
            'kiosk' => count(array_filter($rows, static fn(array $row): bool => (int) ($row['pos_mode'] ?? 0) === 1)),
            'total_amount' => array_sum(array_map(static fn(array $row): float => (float) ($row['total'] ?? 0), $rows)),
            'receivable_balance' => $receivables['balance'],
            'receivable_pending' => $receivables['pending'],
        ];
    }

    private function salesRows(string $companyId, array $filters): array
    {
        $builder = db_connect()->table('sales s')
            ->select('s.*, c.name AS customer_name, dt.name AS document_type_name, dt.code AS document_type_code, dt.category AS document_category, pos.name AS point_of_sale_name, w.name AS warehouse_name, u.name AS created_by_name, src.sale_number AS source_sale_number, src.document_code AS source_document_code, srcdt.name AS source_document_name')
            ->join('customers c', 'c.id = s.customer_id', 'left')
            ->join('sales_document_types dt', 'dt.id = s.document_type_id', 'left')
            ->join('sales_points_of_sale pos', 'pos.id = s.point_of_sale_id', 'left')
            ->join('inventory_warehouses w', 'w.id = s.warehouse_id', 'left')
            ->join('users u', 'u.id = s.created_by', 'left')
            ->join('sales src', 'src.id = s.source_sale_id', 'left')
            ->join('sales_document_types srcdt', 'srcdt.id = src.document_type_id', 'left')
            ->where('s.company_id', $companyId)
            ->orderBy('s.issue_date', 'DESC');

        if (! empty($filters['status'])) {
            $builder->where('s.status', $filters['status']);
        }
        if (! empty($filters['customer_id'])) {
            $builder->where('s.customer_id', $filters['customer_id']);
        }
        if (! empty($filters['date_from'])) {
            $builder->where('s.issue_date >=', $filters['date_from'] . ' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $builder->where('s.issue_date <=', $filters['date_to'] . ' 23:59:59');
        }

        return $builder
            ->join('sales_agents sa', 'sa.id = s.sales_agent_id', 'left')
            ->join('sales_zones sz', 'sz.id = s.sales_zone_id', 'left')
            ->join('sales_conditions sc', 'sc.id = s.sales_condition_id', 'left')
            ->select('sa.name AS sales_agent_name, sz.name AS sales_zone_name, sc.name AS sales_condition_name')
            ->get()
            ->getResultArray();
    }

    private function salesReportData(string $companyId, array $filters): array
    {
        $base = db_connect()->table('sales s')
            ->where('s.company_id', $companyId)
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total']);

        if (! empty($filters['date_from'])) {
            $base->where('s.issue_date >=', $filters['date_from'] . ' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $base->where('s.issue_date <=', $filters['date_to'] . ' 23:59:59');
        }
        if (! empty($filters['customer_id'])) {
            $base->where('s.customer_id', $filters['customer_id']);
        }
        if (! empty($filters['warehouse_id'])) {
            $base->where('s.warehouse_id', $filters['warehouse_id']);
        }

        $sales = $base->get()->getResultArray();
        $summary = [
            'sales_count' => count($sales),
            'gross_total' => array_sum(array_map(static fn(array $row): float => (float) ($row['total'] ?? 0), $sales)),
            'paid_total' => array_sum(array_map(static fn(array $row): float => (float) ($row['paid_total'] ?? 0), $sales)),
            'margin_total' => array_sum(array_map(static fn(array $row): float => (float) ($row['margin_total'] ?? 0), $sales)),
            'average_ticket' => count($sales) > 0 ? array_sum(array_map(static fn(array $row): float => (float) ($row['total'] ?? 0), $sales)) / count($sales) : 0,
            'receivable_balance' => $this->receivableSummary($companyId)['balance'],
            'commission_total' => 0.0,
        ];

        $topProducts = db_connect()->table('sale_items si')
            ->select('si.product_id, si.product_name, SUM(si.quantity) AS qty, SUM(si.line_total) AS total')
            ->join('sales s', 's.id = si.sale_id')
            ->where('s.company_id', $companyId)
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
            ->groupBy('si.product_id, si.product_name')
            ->orderBy('qty', 'DESC')
            ->limit(10);

        if (! empty($filters['date_from'])) {
            $topProducts->where('s.issue_date >=', $filters['date_from'] . ' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $topProducts->where('s.issue_date <=', $filters['date_to'] . ' 23:59:59');
        }

        $topCustomers = db_connect()->table('sales s')
            ->select('COALESCE(c.name, "Consumidor Final") AS customer_name, COUNT(s.id) AS orders_count, SUM(s.total) AS total')
            ->join('customers c', 'c.id = s.customer_id', 'left')
            ->where('s.company_id', $companyId)
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
            ->groupBy('c.name')
            ->orderBy('total', 'DESC')
            ->limit(10);

        if (! empty($filters['date_from'])) {
            $topCustomers->where('s.issue_date >=', $filters['date_from'] . ' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $topCustomers->where('s.issue_date <=', $filters['date_to'] . ' 23:59:59');
        }

        $movements = db_connect()->table('inventory_movements im')
            ->select('im.source_document, im.reason, im.occurred_at, p.name AS product_name, im.quantity')
            ->join('inventory_products p', 'p.id = im.product_id')
            ->where('im.company_id', $companyId)
            ->whereIn('im.reason', ['VENTA', 'DEVOLUCION VENTA', 'ANULACION VENTA'])
            ->orderBy('im.occurred_at', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $topAgents = db_connect()->table('sales s')
            ->select('COALESCE(sa.name, "Sin vendedor") AS sales_agent_name, COUNT(s.id) AS orders_count, SUM(s.total) AS total, SUM(s.margin_total) AS margin_total', false)
            ->join('sales_agents sa', 'sa.id = s.sales_agent_id', 'left')
            ->where('s.company_id', $companyId)
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
            ->groupBy('sa.name')
            ->orderBy('total', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $topZones = db_connect()->table('sales s')
            ->select('COALESCE(sz.name, "Sin zona") AS sales_zone_name, COUNT(s.id) AS orders_count, SUM(s.total) AS total', false)
            ->join('sales_zones sz', 'sz.id = s.sales_zone_id', 'left')
            ->where('s.company_id', $companyId)
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
            ->groupBy('sz.name')
            ->orderBy('total', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $channelMix = db_connect()->table('sales s')
            ->select('CASE WHEN s.pos_mode = 1 THEN "Kiosco" ELSE "Estandar" END AS channel_name, COUNT(s.id) AS orders_count, SUM(s.total) AS total', false)
            ->where('s.company_id', $companyId)
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
            ->groupBy('s.pos_mode')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResultArray();

        $dailySeries = db_connect()->table('sales s')
            ->select('DATE(s.issue_date) AS report_date, COUNT(s.id) AS orders_count, SUM(s.total) AS total, SUM(s.margin_total) AS margin_total', false)
            ->where('s.company_id', $companyId)
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
            ->groupBy('DATE(s.issue_date)', false)
            ->orderBy('DATE(s.issue_date)', 'ASC', false)
            ->get()
            ->getResultArray();

        $auditRows = db_connect()->table('audit_logs al')
            ->select('al.*, u.name AS user_name')
            ->join('users u', 'u.id = al.user_id', 'left')
            ->where('al.company_id', $companyId)
            ->where('al.module', 'sales')
            ->orderBy('al.created_at', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $commissions = db_connect()->table('sales_commissions sc')
            ->select('sc.*, sa.name AS sales_agent_name, s.sale_number')
            ->join('sales_agents sa', 'sa.id = sc.sales_agent_id', 'left')
            ->join('sales s', 's.id = sc.sale_id', 'left')
            ->where('sc.company_id', $companyId)
            ->orderBy('sc.created_at', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $topCommissions = db_connect()->table('sales_commissions sc')
            ->select('COALESCE(sa.name, "Sin vendedor") AS sales_agent_name, COUNT(sc.id) AS items_count, SUM(sc.commission_amount) AS commission_total', false)
            ->join('sales_agents sa', 'sa.id = sc.sales_agent_id', 'left')
            ->where('sc.company_id', $companyId)
            ->where('sc.status !=', 'void')
            ->groupBy('sa.name')
            ->orderBy('commission_total', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $summary['commission_total'] = array_sum(array_map(static fn(array $row): float => (float) ($row['commission_amount'] ?? 0), array_filter($commissions, static fn(array $row): bool => ($row['status'] ?? '') !== 'void')));

        return [
            'summary' => $summary,
            'sales' => $sales,
            'top_products' => $topProducts->get()->getResultArray(),
            'top_customers' => $topCustomers->get()->getResultArray(),
            'top_agents' => $topAgents,
            'top_zones' => $topZones,
            'channel_mix' => $channelMix,
            'daily_series' => $dailySeries,
            'audit_logs' => $auditRows,
            'inventory_movements' => $movements,
            'commissions' => $commissions,
            'top_commissions' => $topCommissions,
        ];
    }

    private function receivableSummary(string $companyId): array
    {
        $row = db_connect()->table('sales_receivables')
            ->select('COUNT(id) AS pending, COALESCE(SUM(balance_amount), 0) AS balance', false)
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'partial'])
            ->get()
            ->getRowArray();

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'balance' => (float) ($row['balance'] ?? 0),
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

    private function receiptApplicationsPayload(string $companyId): array
    {
        $receivableIds = (array) $this->request->getPost('items_receivable_id');
        $amounts = (array) $this->request->getPost('items_applied_amount');
        $rows = [];
        $model = new SalesReceivableModel();

        foreach ($receivableIds as $index => $receivableId) {
            $receivableId = trim((string) $receivableId);
            $appliedAmount = (float) ($amounts[$index] ?? 0);
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

    private function customerOptions(string $companyId): array
    {
        return (new CustomerModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function salesAgentOptions(string $companyId): array
    {
        return (new SalesAgentModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function salesZoneOptions(string $companyId): array
    {
        return (new SalesZoneModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function salesConditionOptions(string $companyId): array
    {
        return (new SalesConditionModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function priceListOptions(string $companyId): array
    {
        return (new SalesPriceListModel())->where('company_id', $companyId)->where('active', 1)->orderBy('is_default', 'DESC')->orderBy('name', 'ASC')->findAll();
    }

    private function activePromotions(string $companyId): array
    {
        $now = date('Y-m-d H:i:s');
        return (new SalesPromotionModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->groupStart()->where('start_date IS NULL')->orWhere('start_date <=', $now)->groupEnd()
            ->groupStart()->where('end_date IS NULL')->orWhere('end_date >=', $now)->groupEnd()
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function promotionMap(string $companyId): array
    {
        $promotions = $this->activePromotions($companyId);
        $itemRows = db_connect()->table('sales_promotion_items spi')
            ->select('spi.product_id, spi.promotion_id')
            ->join('sales_promotions sp', 'sp.id = spi.promotion_id')
            ->where('sp.company_id', $companyId)
            ->where('sp.active', 1)
            ->get()
            ->getResultArray();

        $productMap = [];
        foreach ($itemRows as $row) {
            $productMap[$row['product_id']][] = $row['promotion_id'];
        }

        return ['promotions' => $promotions, 'product_map' => $productMap];
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
            'user_id' => $this->currentUser()['id'] ?? null,
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
            'user_id' => $this->currentUser()['id'] ?? null,
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
            'user_id' => $this->currentUser()['id'] ?? null,
        ]);
    }

    private function resolveProductPrice(string $companyId, string $productId, ?string $priceListId, array $promotionMap): array
    {
        $product = (new InventoryProductModel())->find($productId);
        $price = (float) ($product['sale_price'] ?? 0);

        if ($priceListId) {
            $listPrice = (new SalesPriceListItemModel())
                ->where('price_list_id', $priceListId)
                ->where('product_id', $productId)
                ->first();
            if ($listPrice) {
                $price = (float) $listPrice['price'];
            }
        }

        $discountRate = 0.0;
        $promotions = $promotionMap['promotions'] ?? [];
        $productPromotions = $promotionMap['product_map'][$productId] ?? [];
        foreach ($promotions as $promotion) {
            if (($promotion['scope'] ?? 'selected') === 'all' || in_array($promotion['id'], $productPromotions, true)) {
                if (($promotion['promotion_type'] ?? 'percent') === 'percent') {
                    $discountRate = max($discountRate, (float) ($promotion['value'] ?? 0));
                } elseif (($promotion['promotion_type'] ?? '') === 'fixed') {
                    $price = max(0, $price - (float) ($promotion['value'] ?? 0));
                }
            }
        }

        return ['unit_price' => $price, 'promotion_discount_rate' => $discountRate];
    }

    private function promotionSnapshot(string $companyId, array $items): array
    {
        $map = $this->promotionMap($companyId);
        $snapshots = [];
        foreach ($items as $item) {
            $productId = $item['product_id'];
            foreach (($map['promotions'] ?? []) as $promotion) {
                if (($promotion['scope'] ?? 'selected') === 'all' || in_array($promotion['id'], $map['product_map'][$productId] ?? [], true)) {
                    $snapshots[] = [
                        'product_id' => $productId,
                        'promotion_id' => $promotion['id'],
                        'promotion_name' => $promotion['name'],
                        'promotion_type' => $promotion['promotion_type'],
                        'value' => (float) ($promotion['value'] ?? 0),
                    ];
                }
            }
        }
        return $snapshots;
    }

    private function branchOptions(string $companyId): array
    {
        return (new BranchModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function salesWarehouses(string $companyId): array
    {
        return (new InventoryWarehouseModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function taxOptions(string $companyId): array
    {
        return (new TaxModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function salesProductCatalog(string $companyId): array
    {
        $products = (new InventoryProductModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        $stockRows = db_connect()->table('inventory_stock_levels s')
            ->select('s.product_id, s.warehouse_id, s.quantity, s.reserved_quantity')
            ->join('inventory_warehouses w', 'w.id = s.warehouse_id')
            ->where('s.company_id', $companyId)
            ->where('w.active', 1)
            ->get()
            ->getResultArray();

        $stockMap = [];
        foreach ($stockRows as $row) {
            $stockMap[$row['product_id']][$row['warehouse_id']] = [
                'stock' => (float) $row['quantity'],
                'reserved' => (float) ($row['reserved_quantity'] ?? 0),
                'available' => ((float) $row['quantity']) - ((float) ($row['reserved_quantity'] ?? 0)),
            ];
        }

        return array_map(static function (array $product) use ($stockMap): array {
            $product['sale_price'] = (float) ($product['sale_price'] ?? 0);
            $product['cost_price'] = (float) ($product['cost_price'] ?? 0);
            $product['stocks'] = $stockMap[$product['id']] ?? [];
            return $product;
        }, $products);
    }

    private function salePayload(string $companyId, array $overrides = [])
    {
        $input = array_replace_recursive((array) $this->request->getPost(), $overrides);
        $customerId = trim((string) ($input['customer_id'] ?? '')) ?: null;
        $warehouseId = trim((string) ($input['warehouse_id'] ?? '')) ?: null;
        $sourceSaleId = trim((string) ($input['source_sale_id'] ?? '')) ?: null;
        $channel = (string) ($input['pos_mode'] ?? '') === '1' ? 'kiosk' : 'standard';
        $documentContext = $this->resolveDocumentContext($companyId, $channel, trim((string) ($input['document_type_id'] ?? '')), trim((string) ($input['point_of_sale_id'] ?? '')));
        if ($documentContext['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $documentContext['error']);
        }
        if ($sourceSaleId && ! $this->ownedSale($companyId, $sourceSaleId)) {
            return redirect()->back()->withInput()->with('error', 'El documento origen ya no esta disponible.');
        }
        $currencyCode = trim((string) ($input['currency_code'] ?? '')) ?: 'ARS';
        $salesSettings = $this->salesSettings($companyId);
        if (! $this->isAllowedCurrencyCode($currencyCode, $companyId, $salesSettings['default_currency_code'] ?? null)) {
            return redirect()->back()->withInput()->with('error', 'La moneda seleccionada debe pertenecer a las monedas activas de la empresa.');
        }
        $items = $this->parseSaleItems($companyId, $input);
        $payments = $this->parseSalePayments($input);

        if ($items === []) {
            return redirect()->back()->withInput()->with('error', 'Debes agregar al menos un producto.');
        }

        if (! $warehouseId || ! $this->ownedWarehouse($companyId, $warehouseId)) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar un deposito valido.');
        }

        $customer = null;
        if ($customerId && ! $customer = (new CustomerModel())->where('company_id', $companyId)->where('id', $customerId)->where('active', 1)->first()) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar un cliente valido.');
        }
        if ((int) ($documentContext['documentType']['requires_customer'] ?? 0) === 1 && ! $customerId) {
            return redirect()->back()->withInput()->with('error', 'El comprobante seleccionado requiere un cliente.');
        }
        $dueDate = trim((string) ($input['due_date'] ?? ''));
        if ($dueDate === '' && $customer) {
            $paymentTermsDays = (int) ($customer['payment_terms_days'] ?? 0);
            $dueDate = $paymentTermsDays > 0 ? date('Y-m-d H:i:s', strtotime(($input['issue_date'] ?? date('Y-m-d H:i:s')) . ' +' . $paymentTermsDays . ' days')) : null;
        } elseif ($dueDate !== '') {
            $dueDate = str_contains($dueDate, 'T') ? str_replace('T', ' ', $dueDate) . ':00' : $dueDate;
        } else {
            $dueDate = null;
        }

        $priceListId = trim((string) ($input['price_list_id'] ?? '')) ?: null;
        $priceList = $priceListId ? (new SalesPriceListModel())->find($priceListId) : null;
        $salesAgentId = trim((string) ($input['sales_agent_id'] ?? ($customer['sales_agent_id'] ?? ''))) ?: null;
        $salesZoneId = trim((string) ($input['sales_zone_id'] ?? ($customer['sales_zone_id'] ?? ''))) ?: null;
        $salesConditionId = trim((string) ($input['sales_condition_id'] ?? ($customer['sales_condition_id'] ?? ''))) ?: null;
        $promotionSnapshot = $this->promotionSnapshot($companyId, $items);
        $paymentMethodDiscount = $this->paymentMethodDiscount($companyId, $payments);
        $totals = $this->calculateSaleTotals($items, (float) ($input['global_discount_total'] ?? 0) + $paymentMethodDiscount, $payments);
        $marginTotal = round(array_sum(array_map(static fn(array $row): float => ((float) ($row['line_total'] ?? 0)) - (((float) ($row['unit_cost'] ?? 0)) * ((float) ($row['quantity'] ?? 0))), $items)), 2);
        $creditSnapshot = $this->customerCreditSnapshot($companyId, $customerId, $totals['total']);

        return [
            'sale' => [
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'document_type_id' => $documentContext['documentType']['id'],
                'point_of_sale_id' => $documentContext['pointOfSale']['id'] ?? null,
                'sales_agent_id' => $salesAgentId,
                'sales_zone_id' => $salesZoneId,
                'sales_condition_id' => $salesConditionId,
                'source_sale_id' => $sourceSaleId,
                'issue_date' => trim((string) ($input['issue_date'] ?? '')) ?: date('Y-m-d H:i:s'),
                'due_date' => $dueDate,
                'status' => 'draft',
                'reservation_status' => 'none',
                'payment_status' => $totals['payment_status'],
                'currency_code' => $currencyCode,
                'fiscal_profile' => $customer['tax_profile'] ?? ($documentContext['documentType']['channel'] === 'kiosk' ? 'consumidor_final' : 'cliente'),
                'customer_name_snapshot' => $customer['billing_name'] ?? $customer['name'] ?? 'Consumidor Final',
                'customer_document_snapshot' => trim(($customer['document_type'] ?? '') . ' ' . ($customer['document_number'] ?? '')),
                'customer_tax_profile' => $customer['vat_condition'] ?? $customer['tax_profile'] ?? 'Consumidor Final',
                'price_list_name' => $priceList['name'] ?? trim((string) ($input['price_list_name'] ?? '')),
                'price_list_id' => $priceListId,
                'promotion_snapshot' => $promotionSnapshot === [] ? null : json_encode($promotionSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'pos_mode' => (string) ($input['pos_mode'] ?? '') === '1' ? 1 : 0,
                'subtotal' => $totals['subtotal'],
                'item_discount_total' => $totals['item_discount_total'],
                'global_discount_total' => $totals['global_discount_total'],
                'tax_total' => $totals['tax_total'],
                'margin_total' => $marginTotal,
                'total' => $totals['total'],
                'paid_total' => $totals['paid_total'],
                'credit_score_snapshot' => $creditSnapshot['score'],
                'authorization_status' => $creditSnapshot['requires_authorization'] ? 'pending' : 'not_required',
                'authorization_reason' => $creditSnapshot['reason'],
                'external_transaction_reference' => $this->firstExternalReference($payments),
                'notes' => trim((string) ($input['notes'] ?? '')),
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

    private function parseSaleItems(string $companyId, ?array $input = null): array
    {
        $input ??= (array) $this->request->getPost();
        $items = (array) ($input['items'] ?? []);
        $priceListId = trim((string) ($input['price_list_id'] ?? '')) ?: null;
        $promotions = $this->promotionMap($companyId);
        $taxMap = [];
        foreach ($this->taxOptions($companyId) as $tax) {
            $taxMap[$tax['id']] = (float) $tax['rate'];
        }

        $rows = [];
        foreach ($items as $item) {
            $productId = trim((string) ($item['product_id'] ?? ''));
            if (array_key_exists('enabled', $item) && (string) ($item['enabled'] ?? '') !== '1') {
                continue;
            }
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($productId === '' || $quantity <= 0) {
                continue;
            }

            $product = (new InventoryProductModel())->where('company_id', $companyId)->where('id', $productId)->where('active', 1)->first();
            if (! $product) {
                continue;
            }

            $resolvedPrice = $this->resolveProductPrice($companyId, $productId, $priceListId, $promotions);
            $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== '' ? (float) $item['unit_price'] : $resolvedPrice['unit_price'];
            $discountRate = (float) ($item['discount_rate'] ?? 0);
            if ($discountRate <= 0 && $resolvedPrice['promotion_discount_rate'] > 0) {
                $discountRate = $resolvedPrice['promotion_discount_rate'];
            }
            $discountAmount = round(($quantity * $unitPrice) * ($discountRate / 100), 2);
            $taxId = trim((string) ($item['tax_id'] ?? '')) ?: null;
            $taxRate = $taxId && isset($taxMap[$taxId]) ? $taxMap[$taxId] : 0.0;
            $lineSubtotal = round(($quantity * $unitPrice) - $discountAmount, 2);
            $taxTotal = round($lineSubtotal * ($taxRate / 100), 2);

            $rows[] = [
                'line_number' => count($rows) + 1,
                'product_id' => $productId,
                'tax_id' => $taxId,
                'sku' => $product['sku'],
                'product_name' => $product['name'],
                'product_type' => $product['product_type'] ?? 'simple',
                'unit' => $product['unit'] ?? 'unidad',
                'quantity' => $quantity,
                'returned_quantity' => 0,
                'available_stock_snapshot' => (float) ($item['available_stock_snapshot'] ?? 0),
                'unit_price' => $unitPrice,
                'unit_cost' => (float) ($product['cost_price'] ?? 0),
                'discount_rate' => $discountRate,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'subtotal' => $lineSubtotal,
                'tax_total' => $taxTotal,
                'line_total' => round($lineSubtotal + $taxTotal, 2),
            ];
        }

        return $rows;
    }

    private function parseSalePayments(?array $input = null): array
    {
        $input ??= (array) $this->request->getPost();
        $payments = (array) ($input['payments'] ?? []);
        $rows = [];

        foreach ($payments as $payment) {
            $method = trim((string) ($payment['payment_method'] ?? ''));
            $amount = (float) ($payment['amount'] ?? 0);
            if ($method === '' || $amount <= 0) {
                continue;
            }

            $rows[] = [
                'payment_method' => $method,
                'gateway_id' => trim((string) ($payment['gateway_id'] ?? '')) ?: null,
                'cash_check_id' => trim((string) ($payment['cash_check_id'] ?? '')) ?: null,
                'amount' => $amount,
                'reference' => trim((string) ($payment['reference'] ?? '')),
                'external_reference' => trim((string) ($payment['external_reference'] ?? '')),
                'status' => 'registered',
                'paid_at' => trim((string) ($payment['paid_at'] ?? '')) ?: null,
                'notes' => trim((string) ($payment['notes'] ?? '')),
            ];
        }

        return $rows;
    }

    private function paymentMethodDiscount(string $companyId, array $payments): float
    {
        $paymentMethods = array_values(array_filter(array_unique(array_map(
            static fn(array $row): string => (string) ($row['payment_method'] ?? ''),
            $payments
        ))));

        if ($paymentMethods === []) {
            return 0.0;
        }

        $policy = (new SalesDiscountPolicyModel())
            ->where('company_id', $companyId)
            ->where('policy_type', 'payment_method_discount')
            ->where('active', 1)
            ->whereIn('payment_method', $paymentMethods)
            ->orderBy('discount_rate', 'DESC')
            ->first();

        return $policy ? (float) ($policy['fixed_discount'] ?? 0) : 0.0;
    }

    private function customerCreditSnapshot(string $companyId, ?string $customerId, float $documentTotal): array
    {
        if (! $customerId) {
            return ['score' => 100.0, 'requires_authorization' => false, 'reason' => null];
        }

        $customer = (new CustomerModel())->where('company_id', $companyId)->where('id', $customerId)->first();
        if (! $customer) {
            return ['score' => 0.0, 'requires_authorization' => false, 'reason' => null];
        }

        $receivable = (new SalesReceivableModel())
            ->selectSum('balance_amount', 'balance')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->first();
        $balance = (float) ($receivable['balance'] ?? 0);
        $creditLimit = (float) ($customer['credit_limit'] ?? 0);
        $overdueCount = (new SalesReceivableModel())
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('status', 'overdue')
            ->countAllResults();

        $score = 100.0;
        if ($creditLimit > 0) {
            $score -= min(70, (($balance + $documentTotal) / $creditLimit) * 70);
        }
        $score -= min(30, $overdueCount * 10);
        $score = max(0, round($score, 2));

        $flagModel = new SalesCreditFlagModel();
        $flagModel->where('company_id', $companyId)->where('customer_id', $customerId)->delete();

        $reason = null;
        $requiresAuthorization = false;
        if ($creditLimit > 0 && ($balance + $documentTotal) > $creditLimit) {
            $flagModel->insert([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'flag_type' => 'credit_limit',
                'score_value' => $score,
                'status' => 'active',
                'notes' => 'Cliente excede limite de credito.',
            ]);
            $reason = 'El cliente excede su limite de credito.';
            $requiresAuthorization = true;
        } elseif ($overdueCount > 0) {
            $flagModel->insert([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'flag_type' => 'overdue',
                'score_value' => $score,
                'status' => 'active',
                'notes' => 'Cliente con comprobantes vencidos.',
            ]);
            $reason = 'El cliente tiene deuda vencida.';
            $requiresAuthorization = true;
        }

        return ['score' => $score, 'requires_authorization' => $requiresAuthorization, 'reason' => $reason];
    }

    private function firstExternalReference(array $payments): ?string
    {
        foreach ($payments as $payment) {
            $reference = trim((string) ($payment['external_reference'] ?? ''));
            if ($reference !== '') {
                return $reference;
            }
        }

        return null;
    }

    private function evaluateAuthorizationRequirement(string $companyId, array $sale, array $items)
    {
        if (($sale['authorization_status'] ?? 'not_required') !== 'pending') {
            return true;
        }

        $reason = trim((string) ($sale['authorization_reason'] ?? '')) ?: 'Operacion con autorizacion requerida.';
        $saleId = (string) ($sale['id'] ?? '');
        if ($saleId !== '' && ! (new SalesAuthorizationModel())->where('sale_id', $saleId)->where('status', 'pending')->first()) {
            (new SalesAuthorizationModel())->insert([
                'company_id' => $companyId,
                'sale_id' => $saleId,
                'authorization_type' => 'commercial_validation',
                'reason' => $reason,
                'status' => 'pending',
                'requested_by' => $this->currentUser()['id'],
            ]);
        }

        return $reason . ' Se genero una solicitud de autorizacion.';
    }

    private function calculateSaleTotals(array $items, float $globalDiscount, array $payments): array
    {
        $subtotal = array_sum(array_map(static fn(array $item): float => (float) $item['subtotal'], $items));
        $itemDiscountTotal = array_sum(array_map(static fn(array $item): float => (float) $item['discount_amount'], $items));
        $taxTotal = array_sum(array_map(static fn(array $item): float => (float) $item['tax_total'], $items));
        $total = max(0, round($subtotal + $taxTotal - $globalDiscount, 2));
        $paidTotal = round(array_sum(array_map(static fn(array $payment): float => (float) $payment['amount'], $payments)), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'item_discount_total' => round($itemDiscountTotal, 2),
            'global_discount_total' => round($globalDiscount, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => $total,
            'paid_total' => $paidTotal,
            'payment_status' => $paidTotal <= 0 ? 'pending' : ($paidTotal < $total ? 'partial' : 'paid'),
        ];
    }

    private function persistSaleChildren(string $saleId, array $items, array $payments): void
    {
        foreach ($items as $item) {
            (new SaleItemModel())->insert(array_merge($item, ['sale_id' => $saleId]));
        }

        foreach ($payments as $payment) {
            (new SalePaymentModel())->insert(array_merge($payment, ['sale_id' => $saleId]));
        }
    }

    private function replaceSaleChildren(string $saleId, array $items, array $payments): void
    {
        (new SaleItemModel())->where('sale_id', $saleId)->delete();
        (new SalePaymentModel())->where('sale_id', $saleId)->delete();
        $this->persistSaleChildren($saleId, $items, $payments);
    }

    private function saleItems(string $saleId): array
    {
        return (new SaleItemModel())->where('sale_id', $saleId)->orderBy('line_number', 'ASC')->findAll();
    }

    private function salePayments(string $saleId): array
    {
        return (new SalePaymentModel())->where('sale_id', $saleId)->findAll();
    }

    private function saleReturns(string $saleId): array
    {
        return (new SaleReturnModel())->where('sale_id', $saleId)->findAll();
    }

    private function ownedSale(string $companyId, string $saleId): ?array
    {
        $row = (new SaleModel())->where('company_id', $companyId)->where('id', $saleId)->first();
        return $row ?: null;
    }

    private function ownedWarehouse(string $companyId, string $warehouseId): ?array
    {
        $row = (new InventoryWarehouseModel())->where('company_id', $companyId)->where('id', $warehouseId)->first();
        return $row ?: null;
    }

    private function parseReturnItems(string $saleId): array
    {
        $requested = (array) $this->request->getPost('return_items');
        $saleItems = [];
        foreach ($this->saleItems($saleId) as $item) {
            $saleItems[$item['id']] = $item;
        }

        $rows = [];
        foreach ($requested as $saleItemId => $data) {
            $quantity = (float) ($data['quantity'] ?? 0);
            if ($quantity <= 0 || ! isset($saleItems[$saleItemId])) {
                continue;
            }

            $item = $saleItems[$saleItemId];
            $availableToReturn = (float) $item['quantity'] - (float) ($item['returned_quantity'] ?? 0);
            if ($quantity > $availableToReturn) {
                continue;
            }

            $rows[] = [
                'sale_item_id' => $saleItemId,
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => (float) $item['unit_price'],
                'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                'already_returned' => (float) ($item['returned_quantity'] ?? 0),
                'reason' => trim((string) ($data['reason'] ?? '')),
            ];
        }

        return $rows;
    }

    private function syncSaleReturnStatus(string $saleId): void
    {
        $items = $this->saleItems($saleId);
        $totalQty = array_sum(array_map(static fn(array $item): float => (float) $item['quantity'], $items));
        $returnedQty = array_sum(array_map(static fn(array $item): float => (float) ($item['returned_quantity'] ?? 0), $items));

        $status = 'confirmed';
        if ($returnedQty > 0 && $returnedQty < $totalQty) {
            $status = 'returned_partial';
        }
        if ($totalQty > 0 && $returnedQty >= $totalQty) {
            $status = 'returned_total';
        }

        (new SaleModel())->update($saleId, ['status' => $status]);
    }

    private function refreshSalePaymentStatus(string $saleId): void
    {
        $sale = (new SaleModel())->find($saleId);
        if (! $sale) {
            return;
        }

        $paidTotal = round(array_sum(array_map(static fn(array $row): float => (float) ($row['amount'] ?? 0), $this->salePayments($saleId))), 2);
        $paymentStatus = $paidTotal <= 0 ? 'pending' : ($paidTotal < (float) $sale['total'] ? 'partial' : 'paid');

        (new SaleModel())->update($saleId, [
            'paid_total' => $paidTotal,
            'payment_status' => $paymentStatus,
        ]);
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

    private function resolveCashSession(string $companyId, string $channel): ?array
    {
        $service = new CashService();
        $service->ensureDefaults($companyId, $this->currentUser()['branch_id'] ?? null);
        return $service->activeSessionForChannel($companyId, $channel);
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
                'gateway_id' => $payment['gateway_id'] ?? null,
                'cash_check_id' => $payment['cash_check_id'] ?? null,
                'amount' => (float) ($payment['amount'] ?? 0),
                'reference_type' => 'sale_payment',
                'reference_id' => $payment['id'] ?? null,
                'reference_number' => $sale['sale_number'] ?? null,
                'external_reference' => $payment['external_reference'] ?? ($sale['external_transaction_reference'] ?? null),
                'occurred_at' => $payment['paid_at'] ?: date('Y-m-d H:i:s'),
                'notes' => 'Cobro generado desde venta',
                'created_by' => $this->currentUser()['id'],
            ]);
        }
    }

    private function confirmSaleTransaction(string $companyId, string $saleId, ?array $sale = null)
    {
        $sale ??= $this->ownedSale($companyId, $saleId);
        if (! $sale) {
            return 'Venta no disponible.';
        }

        $items = $this->saleItems($saleId);
        if ($items === []) {
            return 'La venta debe tener productos.';
        }

        $warehouseId = $sale['warehouse_id'] ?: null;
        if (! $warehouseId) {
            return 'La venta debe tener deposito origen.';
        }

        $documentType = ! empty($sale['document_type_id']) ? (new SalesDocumentTypeModel())->find($sale['document_type_id']) : null;
        if (! $documentType) {
            return 'El comprobante seleccionado ya no esta disponible.';
        }

        $authorizationCheck = $this->evaluateAuthorizationRequirement($companyId, $sale, $items);
        if ($authorizationCheck !== true) {
            return $authorizationCheck;
        }

        $allowNegative = (int) (($this->inventorySettings($companyId)['allow_negative_stock'] ?? 0)) === 1;
        $db = db_connect();
        $db->transStart();

        $category = (string) ($documentType['category'] ?? 'invoice');
        if ($category === 'order') {
            $result = $this->reserveStockForSale($companyId, $sale, $items);
            if ($result !== true) {
                $db->transRollback();
                return $result;
            }
        } elseif (in_array($category, ['delivery_note', 'invoice', 'ticket'], true) && (int) ($documentType['impacts_stock'] ?? 0) === 1) {
            $result = $this->deliverSaleStock($companyId, $sale, $items, $allowNegative, $category === 'delivery_note' ? 'REMITO' : 'VENTA');
            if ($result !== true) {
                $db->transRollback();
                return $result;
            }
        }

        $updateData = [
            'status' => 'confirmed',
            'confirmed_by' => $this->currentUser()['id'],
            'confirmed_at' => date('Y-m-d H:i:s'),
        ];
        if ($category === 'delivery_note') {
            $updateData['delivered_by'] = $this->currentUser()['id'];
            $updateData['delivered_at'] = date('Y-m-d H:i:s');
        }

        (new SaleModel())->update($saleId, $updateData);

        $this->refreshSalePaymentStatus($saleId);
        $this->syncReceivableForSale($saleId);
        $this->syncCashMovementsForSale($companyId, $saleId);
        $this->syncSaleCommission($companyId, $saleId);
        (new AccountingService())->syncSale($companyId, $saleId, $this->currentUser()['id']);
        EventBus::emit('sale.confirmed', ['company_id' => $companyId, 'sale' => (new SaleModel())->find($saleId), 'items' => (new SaleItemModel())->where('sale_id', $saleId)->findAll()]);
        $db->transComplete();

        return $db->transStatus() ? true : 'No se pudo confirmar la venta.';
    }

    private function reserveStockForSale(string $companyId, array $sale, array $items)
    {
        $warehouseId = $sale['warehouse_id'] ?: null;
        if (! $warehouseId) {
            return 'La venta debe tener deposito origen.';
        }

        foreach ($items as $item) {
            $this->lockStockLevel($companyId, (string) $item['product_id'], $warehouseId);
            if (! $this->canReserve($companyId, (string) $item['product_id'], $warehouseId, (float) $item['quantity'])) {
                return 'Stock insuficiente para reservar el pedido.';
            }
        }

        $reservationModel = new InventoryReservationModel();
        foreach ($items as $item) {
            $reservationModel->insert([
                'company_id' => $companyId,
                'product_id' => (string) $item['product_id'],
                'warehouse_id' => $warehouseId,
                'sale_id' => $sale['id'],
                'quantity' => (float) $item['quantity'],
                'reference' => $sale['sale_number'],
                'notes' => 'Reserva generada desde pedido',
                'status' => 'active',
                'reserved_by' => $this->currentUser()['id'],
                'reserved_at' => date('Y-m-d H:i:s'),
            ]);
            $this->applyReservedDelta($companyId, (string) $item['product_id'], $warehouseId, (float) $item['quantity']);
        }

        (new SaleModel())->update($sale['id'], [
            'reservation_status' => 'active',
            'reserved_at' => date('Y-m-d H:i:s'),
            'reservation_released_at' => null,
        ]);

        return true;
    }

    private function deliverSaleStock(string $companyId, array $sale, array $items, bool $allowNegative, string $reason)
    {
        $warehouseId = $sale['warehouse_id'] ?: null;
        if (! $warehouseId) {
            return 'La venta debe tener deposito origen.';
        }

        $sourceSale = ! empty($sale['source_sale_id']) ? $this->ownedSale($companyId, (string) $sale['source_sale_id']) : null;
        $sourceDocumentType = (! empty($sourceSale['document_type_id'])) ? (new SalesDocumentTypeModel())->find($sourceSale['document_type_id']) : null;
        $sourceCategory = (string) ($sourceDocumentType['category'] ?? '');

        if ($sourceCategory === 'delivery_note' && ($sourceSale['status'] ?? '') === 'confirmed') {
            return true;
        }

        foreach ($items as $item) {
            $this->lockStockLevel($companyId, (string) $item['product_id'], $warehouseId);
            if (! $this->canWithdraw($companyId, (string) $item['product_id'], $warehouseId, (float) $item['quantity'], $allowNegative, $sale['id'])) {
                return 'Stock insuficiente para confirmar el documento.';
            }
        }

        if ($sourceCategory === 'order' && ($sourceSale['reservation_status'] ?? 'none') === 'active') {
            $this->releaseReservationsForSale($companyId, $sourceSale, $this->saleItems($sourceSale['id']), 'consumed');
        }

        foreach ($items as $item) {
            $this->applyStockDelta($companyId, (string) $item['product_id'], $warehouseId, ((float) $item['quantity']) * -1);
            $this->registerInventoryMovement([
                'company_id' => $companyId,
                'product_id' => (string) $item['product_id'],
                'movement_type' => 'egreso',
                'quantity' => (float) $item['quantity'],
                'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                'total_cost' => ((float) ($item['unit_cost'] ?? 0)) * ((float) $item['quantity']),
                'source_warehouse_id' => $warehouseId,
                'destination_warehouse_id' => null,
                'performed_by' => $this->currentUser()['id'],
                'occurred_at' => date('Y-m-d H:i:s'),
                'reason' => $reason,
                'source_document' => $sale['sale_number'],
                'notes' => $reason === 'REMITO' ? 'Salida por remito' : 'Confirmacion de venta',
            ]);
        }

        return true;
    }

    private function releaseReservationsForSale(string $companyId, array $sale, array $items, string $finalStatus = 'released'): void
    {
        $reservationModel = new InventoryReservationModel();
        $warehouseId = $sale['warehouse_id'] ?: null;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $reservations = $reservationModel
                ->where('company_id', $companyId)
                ->where('sale_id', $sale['id'])
                ->where('product_id', (string) $item['product_id'])
                ->where('status', 'active')
                ->findAll();

            foreach ($reservations as $reservation) {
                $reservationModel->update($reservation['id'], [
                    'status' => $finalStatus,
                    'released_by' => $this->currentUser()['id'],
                    'released_at' => date('Y-m-d H:i:s'),
                ]);
                $this->applyReservedDelta($companyId, (string) $item['product_id'], $warehouseId, ((float) $reservation['quantity']) * -1);
            }
        }

        (new SaleModel())->update($sale['id'], [
            'reservation_status' => $finalStatus === 'cancelled' ? 'cancelled' : 'released',
            'reservation_released_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function restockDeliveredSale(string $companyId, array $sale, array $items, string $reason): void
    {
        $sourceSale = ! empty($sale['source_sale_id']) ? $this->ownedSale($companyId, (string) $sale['source_sale_id']) : null;
        $sourceDocumentType = (! empty($sourceSale['document_type_id'])) ? (new SalesDocumentTypeModel())->find($sourceSale['document_type_id']) : null;
        $sourceCategory = (string) ($sourceDocumentType['category'] ?? '');
        if ($sourceCategory === 'delivery_note' && ($sourceSale['status'] ?? '') === 'confirmed') {
            return;
        }

        foreach ($items as $item) {
            $this->applyStockDelta($companyId, (string) $item['product_id'], $sale['warehouse_id'], (float) $item['quantity']);
            $this->registerInventoryMovement([
                'company_id' => $companyId,
                'product_id' => (string) $item['product_id'],
                'movement_type' => 'ingreso',
                'quantity' => (float) $item['quantity'],
                'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                'total_cost' => ((float) ($item['unit_cost'] ?? 0)) * ((float) $item['quantity']),
                'source_warehouse_id' => null,
                'destination_warehouse_id' => $sale['warehouse_id'],
                'performed_by' => $this->currentUser()['id'],
                'occurred_at' => date('Y-m-d H:i:s'),
                'reason' => $reason,
                'source_document' => $sale['sale_number'],
                'notes' => 'Reversion por cancelacion de documento',
            ]);
        }
    }

    private function nextSequenceNumber(string $companyId, string $documentType, string $defaultPrefix): string
    {
        $model = new VoucherSequenceModel();
        $sequence = $model->where('company_id', $companyId)->where('document_type', $documentType)->first();

        if (! $sequence) {
            $branch = (new BranchModel())->where('company_id', $companyId)->where('code', 'MAIN')->first();
            $id = $model->insert([
                'company_id' => $companyId,
                'branch_id' => $branch['id'] ?? null,
                'document_type' => $documentType,
                'prefix' => $defaultPrefix,
                'current_number' => 1,
                'active' => 1,
            ], true);
            $sequence = $model->find($id);
        }

        $number = (int) ($sequence['current_number'] ?? 1);
        $formatted = strtoupper(trim((string) ($sequence['prefix'] ?? $defaultPrefix))) . '-' . str_pad((string) $number, 8, '0', STR_PAD_LEFT);
        $model->update($sequence['id'], ['current_number' => $number + 1]);

        return $formatted;
    }

    private function previewSequenceNumber(string $companyId, string $documentType, string $defaultPrefix): string
    {
        $model = new VoucherSequenceModel();
        $sequence = $model->where('company_id', $companyId)->where('document_type', $documentType)->first();

        if (! $sequence) {
            $branch = (new BranchModel())->where('company_id', $companyId)->where('code', 'MAIN')->first();
            $id = $model->insert([
                'company_id' => $companyId,
                'branch_id' => $branch['id'] ?? null,
                'document_type' => $documentType,
                'prefix' => $defaultPrefix,
                'current_number' => 1,
                'active' => 1,
            ], true);
            $sequence = $model->find($id);
        }

        $number = (int) ($sequence['current_number'] ?? 1);
        return strtoupper(trim((string) ($sequence['prefix'] ?? $defaultPrefix))) . '-' . str_pad((string) $number, 8, '0', STR_PAD_LEFT);
    }

    private function ensureConsumerFinalCustomer(string $companyId): array
    {
        $customerModel = new CustomerModel();
        $customer = $customerModel->where('company_id', $companyId)->where('name', 'Consumidor Final')->first();

        if ($customer) {
            return $customer;
        }

        $id = $customerModel->insert([
            'company_id' => $companyId,
            'name' => 'Consumidor Final',
            'document_number' => 'CF',
            'price_list_name' => 'KIOSCO',
            'active' => 1,
        ], true);

        return $customerModel->find($id) ?? [];
    }

    private function inventorySettings(string $companyId): array
    {
        return (new InventorySettingModel())->where('company_id', $companyId)->first() ?? [];
    }

    private function salesSettings(string $companyId): array
    {
        $model = new SalesSettingModel();
        $settings = $model->where('company_id', $companyId)->first();
        if ($settings) {
            $normalized = (new ArcaService())->sanitizeSettings($settings, $companyId);
            if (($settings['token_cache_path'] ?? '') !== ($normalized['token_cache_path'] ?? '')) {
                $model->update($settings['id'], ['token_cache_path' => $normalized['token_cache_path']]);
                $settings['token_cache_path'] = $normalized['token_cache_path'];
            }
            return $settings;
        }

        $company = (new CompanyModel())->find($companyId);
        $id = $model->insert([
            'company_id' => $companyId,
            'default_currency_code' => $company['currency_code'] ?? null,
            'invoice_mode_standard_enabled' => 1,
            'invoice_mode_kiosk_enabled' => 1,
            'strict_company_currencies' => 1,
            'profile' => 'argentina_arca',
            'kiosk_document_label' => 'Ticket Consumidor Final',
            'arca_auto_authorize' => 0,
            'token_cache_path' => WRITEPATH . 'arca' . DIRECTORY_SEPARATOR . $companyId,
        ], true);

        return $model->find($id) ?? [];
    }

    private function arcaEventRows(string $companyId, int $limit = 10): array
    {
        return db_connect()->table('sales_arca_events sae')
            ->select('sae.*, s.sale_number, dt.name AS document_type_name, u.name AS performed_by_name')
            ->join('sales s', 's.id = sae.sale_id', 'left')
            ->join('sales_document_types dt', 'dt.id = s.document_type_id', 'left')
            ->join('users u', 'u.id = sae.performed_by', 'left')
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

        $requestId = $this->recordArcaEvent(
            $companyId,
            $sale['id'],
            $result['service_slug'] ?? 'wsaa',
            'authorize',
            $result,
            $result['request_payload'] ?? []
        );

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
        $this->logIntegration($companyId, 'arca', (string) ($result['service_slug'] ?? 'arca'), 'sale', $sale['id'], (string) ($result['status'] ?? 'pending'), $result['request_payload'] ?? null, $result['response_payload'] ?? $result, $result['message'] ?? null);

        return $result;
    }

    private function consultSaleInArca(string $companyId, array $sale): array
    {
        $settings = $this->salesSettings($companyId);
        $result = (new ArcaService())->consultSale($sale, $settings);

        $requestId = $this->recordArcaEvent(
            $companyId,
            $sale['id'],
            $result['service_slug'] ?? 'wsaa',
            'consult',
            $result,
            ['sale_number' => $sale['sale_number'] ?? null]
        );

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
            'performed_by' => $this->currentUser()['id'] ?? null,
            'performed_at' => date('Y-m-d H:i:s'),
        ], true);

        return (string) $id;
    }

    private function recordHardwareEvent(string $companyId, string $channel, string $deviceType, string $eventType, string $status, ?string $referenceType = null, ?string $referenceId = null, array $payload = [], ?string $message = null): void
    {
        (new HardwareLogModel())->insert([
            'company_id' => $companyId,
            'channel' => $channel,
            'device_type' => $deviceType,
            'event_type' => $eventType,
            'status' => $status,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'payload_json' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'message' => $message,
        ]);
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

    private function canWithdraw(string $companyId, string $productId, ?string $warehouseId, float $quantity, bool $allowNegative, ?string $ignoreSaleId = null): bool
    {
        if ($allowNegative || $warehouseId === null) {
            return true;
        }

        $row = (new InventoryStockLevelModel())
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $available = ((float) ($row['quantity'] ?? 0)) - ((float) ($row['reserved_quantity'] ?? 0));
        if ($ignoreSaleId) {
            $available += (float) ((new InventoryReservationModel())
                ->selectSum('quantity', 'qty')
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('sale_id', $ignoreSaleId)
                ->where('status', 'active')
                ->first()['qty'] ?? 0);
        }

        return $available >= $quantity;
    }

    private function applyStockDelta(string $companyId, string $productId, ?string $warehouseId, float $delta): void
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
            $stockLevelModel->update($existing['id'], ['quantity' => ((float) $existing['quantity']) + $delta]);
            return;
        }

        $stockLevelModel->insert([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
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
            $stockLevelModel->update($existing['id'], [
                'reserved_quantity' => max(0, ((float) $existing['reserved_quantity']) + $delta),
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

    private function registerInventoryMovement(array $payload): void
    {
        (new InventoryMovementModel())->insert([
            'company_id' => $payload['company_id'],
            'product_id' => $payload['product_id'],
            'movement_type' => $payload['movement_type'],
            'quantity' => $payload['quantity'],
            'unit_cost' => $payload['unit_cost'] ?? null,
            'total_cost' => $payload['total_cost'] ?? null,
            'adjustment_mode' => $payload['adjustment_mode'] ?? null,
            'source_warehouse_id' => $payload['source_warehouse_id'] ?? null,
            'destination_warehouse_id' => $payload['destination_warehouse_id'] ?? null,
            'performed_by' => $payload['performed_by'],
            'occurred_at' => $payload['occurred_at'] ?? date('Y-m-d H:i:s'),
            'reason' => $payload['reason'] ?? null,
            'source_document' => $payload['source_document'] ?? null,
            'lot_number' => $payload['lot_number'] ?? null,
            'serial_number' => $payload['serial_number'] ?? null,
            'expiration_date' => $payload['expiration_date'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);
    }

    private function renderPdf(string $view, array $data, string $filename)
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }
}
