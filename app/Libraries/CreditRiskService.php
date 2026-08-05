<?php

namespace App\Libraries;

/**
 * CreditRiskService — Customer Credit Risk & Commercial Discount Approval Engine.
 * Enforces credit limit checks, overdue invoice blocking, and discount policy authorization workflows.
 */
class CreditRiskService
{
    /**
     * Evaluate credit risk and commercial policies for a proposed sale.
     */
    public function evaluateSaleRisk(
        string $companyId,
        string $customerId,
        float $proposedTotal,
        float $requestedDiscountPct = 0.0,
        ?string $salesAgentId = null
    ): array {
        $db = db_connect();

        // 1. Fetch customer details
        $customer = $db->table('customers')
            ->where('id', $customerId)
            ->where('company_id', $companyId)
            ->get()->getRowArray();

        if (!$customer) {
            return [
                'approved' => false,
                'block_reason' => 'CUSTOMER_NOT_FOUND',
                'messages' => ['Cliente no encontrado.'],
            ];
        }

        $messages = [];
        $requiresApproval = false;
        $approvalReasons = [];

        // 2. Credit limit check
        $creditLimit = (float) ($customer['credit_limit'] ?? 0);

        // Sum current unpaid receivables
        $unpaidReceivables = $db->table('sales_receivables')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'partial'])
            ->selectSum('balance')
            ->get()->getRowArray();
        
        $currentDebt = (float) ($unpaidReceivables['balance'] ?? 0);
        $projectedDebt = $currentDebt + $proposedTotal;

        if ($creditLimit > 0 && $projectedDebt > $creditLimit) {
            $requiresApproval = true;
            $overLimit = round($projectedDebt - $creditLimit, 2);
            $approvalReasons[] = "Exceso de límite de crédito: Deuda actual (${currentDebt}) + Venta (${proposedTotal}) supera el límite (${creditLimit}) por $${overLimit}.";
        }

        // 3. Overdue receivables check (> X days overdue)
        $today = date('Y-m-d');
        $overdueReceivables = $db->table('sales_receivables')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'partial'])
            ->where('due_date <', $today)
            ->get()->getResultArray();

        if (!empty($overdueReceivables)) {
            $requiresApproval = true;
            $overdueCount = count($overdueReceivables);
            $overdueTotal = array_sum(array_column($overdueReceivables, 'balance'));
            $approvalReasons[] = "El cliente posee {$overdueCount} comprobante(s) vencido(s) por un total de $${overdueTotal}.";
        }

        // 4. Discount policy check
        $maxDiscountPct = 10.0; // Default threshold
        $policy = $db->table('sales_discount_policies')
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->get()->getRowArray();

        if ($policy && isset($policy['max_discount_pct'])) {
            $maxDiscountPct = (float) $policy['max_discount_pct'];
        }

        if ($requestedDiscountPct > $maxDiscountPct) {
            $requiresApproval = true;
            $approvalReasons[] = "El descuento solicitado (${requestedDiscountPct}%) supera el máximo permitido sin autorización (${maxDiscountPct}%).";
        }

        // 5. Credit flag check
        if (!empty($customer['credit_flag']) && $customer['credit_flag'] === 'blocked') {
            return [
                'approved' => false,
                'block_reason' => 'CUSTOMER_BLOCKED',
                'messages' => ['El cliente está bloqueado administrativamente para operar a crédito.'],
                'current_debt' => $currentDebt,
                'credit_limit' => $creditLimit,
            ];
        }

        if ($requiresApproval) {
            // Create an authorization request record if requested
            $authId = app_uuid();
            $db->table('sales_authorizations')->insert([
                'id'                 => $authId,
                'company_id'         => $companyId,
                'customer_id'        => $customerId,
                'sales_agent_id'     => $salesAgentId,
                'proposed_total'     => $proposedTotal,
                'discount_pct'       => $requestedDiscountPct,
                'status'             => 'pending',
                'reasons_json'       => json_encode($approvalReasons),
                'requested_at'       => date('Y-m-d H:i:s'),
                'created_at'         => date('Y-m-d H:i:s'),
            ]);

            return [
                'approved'          => false,
                'requires_approval' => true,
                'authorization_id'  => $authId,
                'block_reason'      => 'APPROVAL_REQUIRED',
                'messages'          => $approvalReasons,
                'current_debt'      => $currentDebt,
                'credit_limit'      => $creditLimit,
                'projected_debt'    => $projectedDebt,
            ];
        }

        return [
            'approved'          => true,
            'requires_approval' => false,
            'messages'          => ['Riesgo crediticio y condiciones comerciales aprobadas.'],
            'current_debt'      => $currentDebt,
            'credit_limit'      => $creditLimit,
            'projected_debt'    => $projectedDebt,
        ];
    }
}
