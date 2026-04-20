<?php

namespace App\Libraries;

use App\Models\AuditLogModel;

class AuditService
{
    private AuditLogModel $model;

    public function __construct()
    {
        $this->model = new AuditLogModel();
    }

    /**
     * Log an audit event.
     *
     * @param string      $module     Module name: 'auth', 'sales', 'purchases', 'inventory', 'cash', 'users', 'settings'
     * @param string      $action     Action performed: 'login', 'logout', 'create', 'update', 'delete', 'confirm', 'cancel', 'export', 'fiscal'
     * @param string      $entityType Entity type: 'user', 'sale', 'purchase_order', 'product', etc.
     * @param string|null $entityId   UUID of the affected entity
     * @param array|null  $before     Previous state (for updates)
     * @param array|null  $after      New state (for creates/updates)
     * @param string|null $notes      Additional context
     */
    public function log(
        string  $module,
        string  $action,
        string  $entityType,
        ?string $entityId = null,
        ?array  $before = null,
        ?array  $after = null,
        ?string $notes = null
    ): void {
        $user = auth_user();

        $data = [
            'company_id'     => $user['company_id'] ?? null,
            'module'         => $module,
            'action'         => $action,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'before_payload' => $before ? json_encode($before) : null,
            'after_payload'  => $after ? json_encode($after) : null,
            'user_id'        => $user['id'] ?? null,
            'ip_address'     => service('request')->getIPAddress(),
            'user_agent'     => substr((string) service('request')->getUserAgent(), 0, 255),
            'notes'          => $notes,
        ];

        try {
            $this->model->insert($data);
        } catch (\Throwable $e) {
            log_message('error', 'AuditService::log failed: ' . $e->getMessage());
        }
    }

    /**
     * Compute changed fields between old and new state.
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];
        $sensitiveFields = ['password_hash', 'two_factor_secret', 'two_factor_backup_codes', 'token_hash'];

        foreach ($after as $key => $newValue) {
            if (in_array($key, $sensitiveFields, true)) {
                if (($before[$key] ?? null) !== $newValue) {
                    $changes[$key] = ['from' => '***', 'to' => '***'];
                }
                continue;
            }

            $oldValue = $before[$key] ?? null;

            if ((string) $oldValue !== (string) $newValue) {
                $changes[$key] = [
                    'from' => $oldValue,
                    'to'   => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Shortcut: log a create event.
     */
    public function logCreate(string $module, string $entityType, string $entityId, array $data): void
    {
        $this->log($module, 'create', $entityType, $entityId, null, $data);
    }

    /**
     * Shortcut: log an update event with diff.
     */
    public function logUpdate(string $module, string $entityType, string $entityId, array $before, array $after): void
    {
        $changes = $this->diff($before, $after);

        if (empty($changes)) {
            return; // No actual changes
        }

        $this->log($module, 'update', $entityType, $entityId, $before, $after);
    }

    /**
     * Shortcut: log a delete event.
     */
    public function logDelete(string $module, string $entityType, string $entityId, array $data): void
    {
        $this->log($module, 'delete', $entityType, $entityId, $data, null);
    }

    /**
     * Shortcut: log a login event.
     */
    public function logLogin(string $userId, bool $success, ?string $reason = null): void
    {
        $action = $success ? 'login' : 'login_failed';
        $this->log('auth', $action, 'user', $userId, null, null, $reason);
    }

    /**
     * Shortcut: log a fiscal event.
     */
    public function logFiscal(string $entityType, string $entityId, string $status, ?string $detail = null): void
    {
        $this->log('fiscal', 'authorize', $entityType, $entityId, null, ['status' => $status], $detail);
    }
}
