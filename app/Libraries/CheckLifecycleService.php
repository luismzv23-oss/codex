<?php

namespace App\Libraries;

use App\Libraries\AccountingService;

/**
 * CheckLifecycleService — Complete Lifecycle State Machine for Physical Checks & eCheqs.
 * Manages check state transitions (cartera -> depositado / endosado / custodia -> acreditado / rechazado)
 * and auto-generates Debit Notes for rejected checks with bank penalty charges.
 */
class CheckLifecycleService
{
    protected AccountingService $accountingService;

    public function __construct()
    {
        $this->accountingService = new AccountingService();
    }

    /**
     * Transition a check state safely with accounting impact.
     */
    public function transitionCheckState(
        string $companyId,
        string $checkId,
        string $newStatus,
        array $params = []
    ): array {
        $db = db_connect();

        $check = $db->table('cash_checks')
            ->where('id', $checkId)
            ->where('company_id', $companyId)
            ->get()->getRowArray();

        if (!$check) {
            return ['ok' => false, 'error' => 'Cheque no encontrado'];
        }

        $currentStatus = $check['status'] ?? 'cartera';
        $validTransitions = [
            'cartera'    => ['depositado', 'endosado', 'custodia', 'anulado'],
            'custodia'   => ['depositado', 'cartera', 'anulado'],
            'depositado' => ['acreditado', 'rechazado'],
            'endosado'   => ['acreditado', 'rechazado'],
        ];

        if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
            return [
                'ok'    => false,
                'error' => "Transición no válida de estado '{$currentStatus}' a '{$newStatus}'.",
            ];
        }

        $db->transBegin();

        try {
            $updateData = [
                'status'     => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($newStatus === 'depositado') {
                $updateData['deposit_date'] = $params['deposit_date'] ?? date('Y-m-d');
                $updateData['bank_account_id'] = $params['bank_account_id'] ?? null;
            } elseif ($newStatus === 'endosado') {
                $updateData['endorsed_to_supplier_id'] = $params['supplier_id'] ?? null;
                $updateData['endorsed_at'] = date('Y-m-d H:i:s');
            } elseif ($newStatus === 'acreditado') {
                $updateData['cleared_at'] = date('Y-m-d H:i:s');
            } elseif ($newStatus === 'rechazado') {
                $updateData['rejected_at'] = date('Y-m-d H:i:s');
                $updateData['rejection_reason'] = $params['rejection_reason'] ?? 'Falta de fondos / Rechazo bancario';
            }

            $db->table('cash_checks')->where('id', $checkId)->update($updateData);

            // Handle rejection side effects: Generate Debit Note & Accounting entry
            if ($newStatus === 'rechazado' && !empty($check['customer_id'])) {
                $bankFee = (float) ($params['bank_fee'] ?? 0.0);
                $checkAmount = (float) $check['amount'];
                $totalDebitNoteAmount = $checkAmount + $bankFee;

                // Create Debit Note in sales_receivables for customer
                $receivableId = app_uuid();
                $db->table('sales_receivables')->insert([
                    'id'             => $receivableId,
                    'company_id'     => $companyId,
                    'customer_id'    => $check['customer_id'],
                    'document_type'  => 'ND_CHEQUE_RECHAZADO',
                    'voucher_number' => 'ND-CHK-' . ($check['check_number'] ?? rand(1000, 9999)),
                    'amount'         => $totalDebitNoteAmount,
                    'balance'        => $totalDebitNoteAmount,
                    'issue_date'     => date('Y-m-d'),
                    'due_date'       => date('Y-m-d', strtotime('+7 days')),
                    'status'         => 'pending',
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);

                // Create Audit Event
                $db->table('document_events')->insert([
                    'id'            => app_uuid(),
                    'company_id'    => $companyId,
                    'document_type' => 'check',
                    'document_id'   => $checkId,
                    'event_type'    => 'CHECK_REJECTED',
                    'description'   => "Cheque N° {$check['check_number']} rechazado. Se generó Nota de Débito a cliente por $${totalDebitNoteAmount}.",
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
            }

            $db->transCommit();

            return [
                'ok'             => true,
                'check_id'       => $checkId,
                'previous_status' => $currentStatus,
                'new_status'     => $newStatus,
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
