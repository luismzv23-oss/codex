<?php
namespace App\Libraries;

use App\Models\AuditLogModel;

/** Field changes use the canonical audit schema; watched fields cannot expose secrets. */
class FieldAuditService
{
    public function logEntityDiff(
        string $entityName, string $entityId, array $oldState, array $newState,
        ?string $companyId = null, ?string $userId = null, array $watchedFields = []
    ): bool {
        $before = $after = [];
        $keys = $watchedFields ?: array_unique(array_merge(array_keys($oldState), array_keys($newState)));
        foreach ($keys as $key) {
            if (preg_match('/password|secret|token|backup_codes|private_key|api[_-]?key|authorization/i', $key)) continue;
            if (!$watchedFields && in_array($key, ['created_at', 'updated_at'], true)) continue;
            $old = $oldState[$key] ?? null;
            $new = $newState[$key] ?? null;
            if ($old !== $new) { $before[$key] = $old; $after[$key] = $new; }
        }
        if (!$after) return false;
        $user = auth_user();
        return (bool) (new AuditLogModel())->insert([
            'company_id'=>$companyId ?? ($user['company_id'] ?? null),
            'user_id'=>$userId ?? ($user['id'] ?? null),
            'module'=>'field_audit', 'action'=>'UPDATE_ENTITY_FIELD_DIFF',
            'entity_type'=>$entityName, 'entity_id'=>$entityId,
            'before_payload'=>json_encode($before, JSON_THROW_ON_ERROR),
            'after_payload'=>json_encode($after, JSON_THROW_ON_ERROR),
            'notes'=>json_encode(['changed_fields'=>array_keys($after)], JSON_THROW_ON_ERROR),
            'ip_address'=>service('request')->getIPAddress(),
        ]);
    }
}
