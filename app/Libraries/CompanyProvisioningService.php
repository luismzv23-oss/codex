<?php

namespace App\Libraries;

use App\Models\{BranchModel, CompanyModel, CustomerModel, InventorySettingModel, InventoryWarehouseModel, SalesDocumentTypeModel,
    SalesPointOfSaleModel, SalesPriceListModel, SalesSettingModel, VoucherSequenceModel};

/**
 * Per-company defaults every module needs (moved from the Inventory, Sales, Purchases and Cash controllers,
 * where they were created lazily on first use). All methods are idempotent: they only create what is missing.
 */
class CompanyProvisioningService
{
    /** Creates every module default for a company. */
    public function all(string $companyId): void
    {
        $this->inventory($companyId);
        $this->sales($companyId);
        $this->purchases($companyId);
        (new CashService())->ensureDefaults($companyId, $this->mainBranchId($companyId));
    }

    public function inventory(string $companyId): void
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
            $warehouseModel->insert([
                'company_id' => $companyId,
                'branch_id' => $this->mainBranchId($companyId),
                'name' => 'Deposito Central',
                'code' => 'DEP-CENTRAL',
                'type' => 'central',
                'description' => 'Deposito inicial del sistema de inventario.',
                'is_default' => 1,
                'active' => 1,
            ]);
        }
    }

    public function sales(string $companyId): void
    {
        $branchId = $this->mainBranchId($companyId);
        $sequenceModel = new VoucherSequenceModel();
        foreach ([['VENTA', 'VTA'], ['FACTURA', 'FAC'], ['TICKET', 'TCK'], ['NC', 'NC'], ['RECIBO', 'REC']] as [$type, $prefix]) {
            if (! $sequenceModel->where('company_id', $companyId)->where('document_type', $type)->first()) {
                $sequenceModel->insert(['company_id' => $companyId, 'branch_id' => $branchId, 'document_type' => $type, 'prefix' => $prefix, 'current_number' => 1, 'active' => 1]);
            }
        }
        $customerModel = new CustomerModel();
        if (! $customerModel->where('company_id', $companyId)->where('name', 'Consumidor Final')->first()) {
            $customerModel->insert([
                'company_id' => $companyId, 'branch_id' => $branchId, 'name' => 'Consumidor Final', 'billing_name' => 'Consumidor Final',
                'document_number' => 'CF', 'document_type' => 'DNI', 'tax_profile' => 'consumidor_final', 'vat_condition' => 'Consumidor Final',
                'price_list_name' => 'Lista General', 'credit_limit' => 0, 'custom_discount_rate' => 0, 'payment_terms_days' => 0, 'active' => 1,
            ]);
        }
        $priceListModel = new SalesPriceListModel();
        if (! $priceListModel->where('company_id', $companyId)->where('is_default', 1)->first()) {
            $priceListModel->insert(['company_id' => $companyId, 'name' => 'Lista General', 'description' => 'Lista base del sistema de ventas.', 'is_default' => 1, 'active' => 1]);
        }
        $this->salesSettings($companyId);
        $this->salesDocuments($companyId, $branchId);
    }

    /** Sales settings row (created with the ARCA profile defaults when missing). */
    public function salesSettings(string $companyId): array
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

    public function purchases(string $companyId): void
    {
        $branchId = $this->mainBranchId($companyId);
        $sequenceModel = new VoucherSequenceModel();
        foreach ([['OCOMPRA', 'OC'], ['RCOMPRA', 'REC'], ['DEVPROV', 'DVP'], ['PAGCP', 'CXP'], ['PAGPROV', 'PP']] as [$type, $prefix]) {
            if (! $sequenceModel->where('company_id', $companyId)->where('document_type', $type)->first()) {
                $sequenceModel->insert(['company_id' => $companyId, 'branch_id' => $branchId, 'document_type' => $type, 'prefix' => $prefix, 'current_number' => 1, 'active' => 1]);
            }
        }
    }

    private function salesDocuments(string $companyId, ?string $branchId): void
    {
        $documentTypeModel = new SalesDocumentTypeModel();
        $pointOfSaleModel = new SalesPointOfSaleModel();
        $defaultWarehouse = (new InventoryWarehouseModel())->where('company_id', $companyId)->where('active', 1)->orderBy('is_default', 'DESC')->orderBy('name', 'ASC')->first();
        $hasAfipCode = db_connect()->fieldExists('afip_code', 'sales_document_types');
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
        $ids = [];
        $created = [];
        foreach ($definitions as $definition) {
            $existing = $documentTypeModel->where('company_id', $companyId)->where('code', $definition['code'])->first();
            if ($existing) {
                $ids[$definition['code']] = $existing['id'];
                continue;
            }
            if ($hasAfipCode && isset(ArcaService::AFIP_VOUCHER_TYPES[$definition['code']])) {
                $definition['afip_code'] = ArcaService::AFIP_VOUCHER_TYPES[$definition['code']];
            }
            $ids[$definition['code']] = $documentTypeModel->insert(array_merge($definition, ['company_id' => $companyId, 'active' => 1]), true);
            $created[$definition['code']] = true;
        }
        // A new company starts with Factura B as default document (existing companies keep their choice).
        if (isset($created['FACTURA_B']) && db_connect()->fieldExists('is_default', 'sales_document_types') && ! $documentTypeModel->getDefaultDocumentType($companyId)) {
            $documentTypeModel->setDefault((string) $ids['FACTURA_B'], $companyId);
        }
        foreach ([['PV-STD', 'Punto de Venta Principal', 'standard', 'FACTURA_B'], ['PV-KIOSCO', 'Punto de Venta Kiosco', 'kiosk', 'TICKET']] as [$code, $name, $channel, $document]) {
            if ($pointOfSaleModel->where('company_id', $companyId)->where('code', $code)->first()) {
                continue;
            }
            $pointOfSaleModel->insert(['company_id' => $companyId, 'branch_id' => $branchId, 'warehouse_id' => $defaultWarehouse['id'] ?? null,
                'document_type_id' => $ids[$document] ?? null, 'name' => $name, 'code' => $code, 'channel' => $channel, 'active' => 1]);
        }
    }

    private function mainBranchId(string $companyId): ?string
    {
        return (new BranchModel())->where('company_id', $companyId)->where('code', 'MAIN')->first()['id'] ?? null;
    }
}
