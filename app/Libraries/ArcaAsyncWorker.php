<?php
namespace App\Libraries;

use App\Models\{CompanyModel, SaleModel, SaleItemModel, SalesSettingModel, SalesDocumentTypeModel, SalesPointOfSaleModel, SalesArcaEventModel, IntegrationLogModel};

/** Adapter to the same fiscal engine and schema used by Sales. No independent SOAP payload. */
class ArcaAsyncWorker
{
    protected ArcaService $arcaService;

    public function __construct(?ArcaService $arcaService = null)
    {
        $this->arcaService = $arcaService ?? new ArcaService();
    }

    public function queueInvoice(string $companyId, string $saleId, array $saleData = []): bool
    {
        return (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $saleId) {
            $sale = $this->sale($companyId, $saleId);
            if (!$sale || !in_array($sale['status'], ['confirmed', 'returned_partial', 'returned_total'], true) || !empty($sale['cae'])) return false;
            if (in_array($sale['arca_status'], ['queued', 'retry', 'processing'], true)) return true;
            // An ambiguous previous request must be consulted before another authorization.
            if ($sale['arca_status'] === 'contingency') return false;
            $this->save($companyId, $saleId, ['arca_status'=>'queued']);
            $this->log($companyId, $saleId, 'queue_invoice', 'queued', []);
            return true;
        });
    }

    public function processBatch(string $companyId, int $limit = 20): array
    {
        $pending = db_connect()->table('sales')->where('company_id', $companyId)
            ->whereIn('arca_status', ['queued', 'retry'])->orderBy('created_at')->limit(max(1, min(100, $limit)))
            ->get()->getResultArray();
        $summary = ['processed'=>0, 'successes'=>0, 'errors'=>0, 'details'=>[]];
        foreach ($pending as $sale) {
            $result = $this->authorizeSingleSale($sale);
            $summary['processed']++;
            $summary[$result['ok'] ? 'successes' : 'errors']++;
            $summary['details'][] = $result;
        }
        return $summary;
    }

    public function authorizeSingleSale(array $sale): array
    {
        $companyId = (string) ($sale['company_id'] ?? '');
        $saleId = (string) ($sale['id'] ?? '');
        $claimed = (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $saleId) {
            $fresh = $this->sale($companyId, $saleId);
            if (!$fresh || !in_array($fresh['arca_status'], ['queued', 'retry'], true) || !empty($fresh['cae'])) return null;
            if (!in_array($fresh['status'], ['confirmed', 'returned_partial', 'returned_total'], true)) {
                $this->save($companyId, $saleId, ['arca_status'=>'arca_error', 'arca_result_message'=>'El comprobante no está confirmado.']);
                return null;
            }
            $this->save($companyId, $saleId, ['arca_status'=>'processing']);
            return $fresh;
        });
        if (!$claimed) return ['ok'=>false, 'sale_id'=>$saleId, 'error'=>'Comprobante no disponible para procesamiento.'];

        $settings = (new SalesSettingModel())->where('company_id', $companyId)->first();
        $company = (new CompanyModel())->find($companyId);
        $document = (new SalesDocumentTypeModel())->where(['company_id'=>$companyId, 'id'=>$claimed['document_type_id']])->first();
        $point = empty($claimed['point_of_sale_id']) ? [] : (new SalesPointOfSaleModel())->where(['company_id'=>$companyId, 'id'=>$claimed['point_of_sale_id']])->first();
        if (!$settings || !$company || !$document || $point === null) {
            $result = ['status'=>'arca_error', 'message'=>'Configuración fiscal o referencias de empresa incompletas.'];
        } else {
            try {
                $settings = $this->arcaService->sanitizeSettings($settings, $companyId);
                $items = (new SaleItemModel())->where('sale_id', $saleId)->orderBy('line_number')->findAll();
                $result = $this->arcaService->authorizeSale($claimed, $document, $company, $settings, $items, $point);
            } catch (\Throwable $e) {
                // Unknown transport outcomes cannot safely be retried without fiscal consultation.
                $result = ['status'=>'error', 'message'=>'No se pudo determinar el resultado fiscal. Consultá el comprobante antes de reintentar.'];
            }
        }

        return (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $saleId, $result, $settings) {
            $current = $this->sale($companyId, $saleId);
            if (!$current) throw new \RuntimeException('Comprobante no disponible.');
            $ok = !empty($result['cae']) || ($result['status'] ?? '') === 'No Aplica';
            $attempts = db_connect()->table('integration_logs')->where(['company_id'=>$companyId, 'provider'=>'arca_async', 'service'=>'authorize', 'reference_id'=>$saleId])->countAllResults() + 1;
            $status = $result['status'] ?? 'error';
            if (!$ok && $status === 'error') $status = 'contingency';
            elseif (!$ok && $status === 'rejected' && ($result['result_code'] ?? '') !== 'CFG001') $status = $attempts < 5 ? 'retry' : 'contingency';
            elseif (!$ok) $status = 'arca_error';
            $eventId = (new SalesArcaEventModel())->insert([
                'company_id'=>$companyId, 'sale_id'=>$saleId, 'service_slug'=>$result['service_slug'] ?? 'arca',
                'event_type'=>'authorize', 'environment'=>$settings['arca_environment'] ?? 'homologacion',
                'status'=>$result['status'] ?? 'error', 'result_code'=>$result['result_code'] ?? null,
                'message'=>$result['message'] ?? null, 'request_payload'=>json_encode($result['request_payload'] ?? []),
                'response_payload'=>json_encode($result['response_payload'] ?? []), 'performed_at'=>date('Y-m-d H:i:s'),
            ], true);
            if (!$eventId) throw new \RuntimeException('No se pudo registrar el resultado fiscal.');
            $changes = ['arca_status'=>$status, 'arca_service'=>$result['service_slug'] ?? null,
                'arca_operation_mode'=>'automatic', 'arca_result_code'=>$result['result_code'] ?? null,
                'arca_result_message'=>$result['message'] ?? null, 'arca_last_checked_at'=>date('Y-m-d H:i:s'), 'arca_request_id'=>$eventId];
            if (!empty($result['cae'])) $changes += ['cae'=>$result['cae'], 'cae_due_date'=>$result['cae_due_date'] ?? null, 'arca_authorized_at'=>$result['authorized_at'] ?? date('Y-m-d H:i:s')];
            // A concurrent fiscal consultation may already have recovered the CAE.
            if (!empty($current['cae'])) return ['ok'=>true, 'sale_id'=>$saleId, 'cae'=>$current['cae']];
            $this->save($companyId, $saleId, $changes);
            $this->log($companyId, $saleId, 'authorize', $status, ['attempt'=>$attempts]);
            if ($settings) (new SalesSettingModel())->update($settings['id'], ['arca_last_sync_at'=>date('Y-m-d H:i:s'), 'arca_last_error'=>$ok ? null : ($result['message'] ?? 'Error fiscal')]);
            return ['ok'=>$ok, 'sale_id'=>$saleId, 'cae'=>$result['cae'] ?? null, 'error'=>$ok ? null : ($result['message'] ?? 'Error fiscal')];
        });
    }

    private function sale(string $companyId, string $saleId): ?array
    {
        return (new SaleModel())->where(['company_id'=>$companyId, 'id'=>$saleId])->first();
    }

    private function save(string $companyId, string $saleId, array $changes): void
    {
        if (!(new SaleModel())->where('company_id', $companyId)->update($saleId, $changes)) throw new \RuntimeException('No se pudo guardar el estado fiscal.');
    }

    private function log(string $companyId, string $saleId, string $operation, string $status, array $data): void
    {
        if (!(new IntegrationLogModel())->insert([
            'company_id'=>$companyId, 'provider'=>'arca_async', 'service'=>$operation,
            'reference_type'=>'sale', 'reference_id'=>$saleId, 'status'=>$status,
            'request_payload'=>json_encode($data), 'message'=>'Procesamiento fiscal asíncrono',
        ])) throw new \RuntimeException('No se pudo registrar la integración fiscal.');
    }
}
