<?php

namespace App\Libraries;

/**
 * FieldAuditService — Universal Field-Level Change Tracking Engine.
 * Captures entity state diffs (old_value vs new_value), filters sensitive keys,
 * and writes structured audit logs to audit_logs for compliance governance.
 */
class FieldAuditService
{
    /**
     * Audit entity changes between old state and new state.
     */
    public function logEntityDiff(
        string $entityName,
        string $entityId,
        array $oldState,
        array $newState,
        ?string $companyId = null,
        ?string $userId = null,
        array $watchedFields = []
    ): bool {
        $db = db_connect();

        $diffs = [];
        $keysToCompare = !empty($watchedFields) ? $watchedFields : array_unique(array_merge(array_keys($oldState), array_keys($newState)));

        // Exclude internal timestamps if not specifically watched
        $ignoredKeys = ['updated_at', 'created_at', 'password_hash', 'remember_token'];

        foreach ($keysToCompare as $key) {
            if (in_array($key, $ignoredKeys) && empty($watchedFields)) {
                continue;
            }

            $oldVal = $oldState[$key] ?? null;
            $newVal = $newState[$key] ?? null;

            if ((string) $oldVal !== (string) $newVal) {
                $diffs[$key] = [
                    'old' => $oldVal,
                    'new' => $newVal,
                ];
            }
        }

        if (empty($diffs)) {
            return false; // No relevant field changes detected
        }

        $user = auth_user();
        $userId ??= ($user['id'] ?? null);
        $companyId ??= ($user['company_id'] ?? null);

        return $db->table('audit_logs')->insert([
            'id'             => app_uuid(),
            'company_id'     => $companyId,
            'user_id'        => $userId,
            'action'         => 'UPDATE_ENTITY_FIELD_DIFF',
            'entity_name'    => $entityName,
            'entity_id'      => $entityId,
            'details'        => json_encode([
                'changed_fields' => array_keys($diffs),
                'diffs'          => $diffs,
                'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
