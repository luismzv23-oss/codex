<?php

namespace App\Libraries;

use App\Libraries\ArcaService;

/**
 * ArcaAsyncWorker — Asynchronous Worker & Queue Processor for ARCA / AFIP Invoicing.
 * Handles background CAE authorization, exponential retries, offline contingency, and audit event logging.
 */
class ArcaAsyncWorker
{
    protected ArcaService $arcaService;

    public function __construct()
    {
        $this->arcaService = new ArcaService();
    }

    /**
     * Queue a sales document for asynchronous fiscal authorization.
     */
    public function queueInvoice(string $companyId, string $saleId, array $saleData): bool
    {
        $db = db_connect();
        
        $db->table('sales')->where('id', $saleId)->update([
            'arca_status' => 'queued',
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return $db->table('integration_logs')->insert([
            'id'             => app_uuid(),
            'company_id'     => $companyId,
            'service_name'   => 'ARCA_ASYNC_QUEUE',
            'action'         => 'queue_invoice',
            'request_payload' => json_encode(['sale_id' => $saleId, 'sale_number' => $saleData['sale_number'] ?? '']),
            'response_payload' => json_encode(['status' => 'queued']),
            'status_code'    => 200,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Process all queued or retryable invoices in background job mode.
     */
    public function processBatch(int $limit = 20): array
    {
        $db = db_connect();
        $pending = $db->table('sales')
            ->whereIn('arca_status', ['queued', 'retry'])
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get()->getResultArray();

        $processed = 0;
        $successes = 0;
        $errors = 0;
        $details = [];

        foreach ($pending as $sale) {
            $processed++;
            $result = $this->authorizeSingleSale($sale);
            if ($result['ok']) {
                $successes++;
            } else {
                $errors++;
            }
            $details[] = $result;
        }

        return [
            'processed' => $processed,
            'successes' => $successes,
            'errors'    => $errors,
            'details'   => $details,
        ];
    }

    /**
     * Authorize a single sale document with ARCA.
     */
    public function authorizeSingleSale(array $sale): array
    {
        $db = db_connect();
        $companyId = $sale['company_id'];
        $saleId = $sale['id'];

        $retryCount = (int) ($sale['arca_retry_count'] ?? 0);
        $maxRetries = 5;

        // Fetch company ARCA settings
        $settingsRow = $db->table('company_settings')
            ->where('company_id', $companyId)
            ->get()->getRowArray();
        $settings = json_decode($settingsRow['settings_json'] ?? '{}', true);

        $validation = $this->arcaService->validateSettings($settings, $companyId);
        if (!$validation['valid']) {
            $this->markError($saleId, implode('; ', $validation['errors']), $retryCount + 1);
            return ['ok' => false, 'sale_id' => $saleId, 'error' => 'Configuración ARCA inválida'];
        }

        try {
            // Build fiscal voucher payload
            $voucher = [
                'DocTipo'    => $sale['customer_doc_type'] ?? 96,
                'DocNro'     => $sale['customer_doc_number'] ?? '0',
                'CbteTipo'   => $sale['voucher_type'] ?? 6, // 1: Factura A, 6: Factura B, 11: Factura C
                'PtoVta'     => $sale['pos_number'] ?? 1,
                'CbteFch'    => date('Ymd', strtotime($sale['sale_date'] ?? date('Y-m-d'))),
                'ImpTotal'   => (float) $sale['total'],
                'ImpNeto'    => (float) $sale['subtotal'],
                'ImpIVA'     => (float) ($sale['tax_total'] ?? 0),
                'ImpTotConc' => 0,
                'ImpOpEx'    => 0,
                'ImpTrib'    => 0,
                'MonId'      => 'PES',
                'MonCotiz'   => 1,
            ];

            // Attempt authorization via ArcaService
            $response = $this->arcaService->authorizeVoucher($companyId, $settings, $voucher);

            if (!empty($response['cae'])) {
                $db->table('sales')->where('id', $saleId)->update([
                    'cae'                 => $response['cae'],
                    'cae_expiration_date' => $response['cae_due_date'] ?? null,
                    'voucher_number'      => $response['voucher_number'] ?? $sale['voucher_number'],
                    'arca_status'         => 'authorized',
                    'arca_error'          => null,
                    'updated_at'          => date('Y-m-d H:i:s'),
                ]);

                // Log audit document event
                $db->table('document_events')->insert([
                    'id'            => app_uuid(),
                    'company_id'    => $companyId,
                    'document_type' => 'sale',
                    'document_id'   => $saleId,
                    'event_type'    => 'ARCA_AUTHORIZED',
                    'description'   => "CAE otorgado: {$response['cae']} para comprobante N° {$response['voucher_number']}",
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);

                return ['ok' => true, 'sale_id' => $saleId, 'cae' => $response['cae']];
            } else {
                $errMsg = $response['error'] ?? 'Respuesta ARCA sin CAE';
                $this->markRetryOrContingency($saleId, $errMsg, $retryCount + 1, $maxRetries);
                return ['ok' => false, 'sale_id' => $saleId, 'error' => $errMsg];
            }
        } catch (\Throwable $e) {
            $this->markRetryOrContingency($saleId, $e->getMessage(), $retryCount + 1, $maxRetries);
            return ['ok' => false, 'sale_id' => $saleId, 'error' => $e->getMessage()];
        }
    }

    protected function markRetryOrContingency(string $saleId, string $error, int $newRetryCount, int $maxRetries): void
    {
        $db = db_connect();
        $status = $newRetryCount >= $maxRetries ? 'contingency' : 'retry';

        $db->table('sales')->where('id', $saleId)->update([
            'arca_status'      => $status,
            'arca_error'       => $error,
            'arca_retry_count' => $newRetryCount,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $db->table('integration_logs')->insert([
            'id'               => app_uuid(),
            'company_id'       => null,
            'service_name'     => 'ARCA_ASYNC_WORKER',
            'action'           => 'authorize_failure',
            'request_payload'  => json_encode(['sale_id' => $saleId, 'retry' => $newRetryCount]),
            'response_payload' => json_encode(['error' => $error, 'next_status' => $status]),
            'status_code'      => 500,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    protected function markError(string $saleId, string $error, int $newRetryCount): void
    {
        db_connect()->table('sales')->where('id', $saleId)->update([
            'arca_status'      => 'arca_error',
            'arca_error'       => $error,
            'arca_retry_count' => $newRetryCount,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
    }
}
