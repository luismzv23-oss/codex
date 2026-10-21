<?php

namespace App\Libraries;

/**
 * NotificationService — Internal notification system.
 * Creates in-app notifications and supports future email/push channels.
 */
class NotificationService
{
    public function create(string $companyId, string $userId, string $type, string $title, string $message, ?string $url = null, string $priority = 'normal'): void
    {
        try {
            db_connect()->table('notifications')->insert([
                'id'         => app_uuid(),
                'company_id' => $companyId,
                'user_id'    => $userId,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'url'        => $url,
                'priority'   => $priority,
                'read_at'    => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService: ' . $e->getMessage());
        }
    }

    public function notifyRole(string $companyId, string $roleSlug, string $type, string $title, string $message, ?string $url = null): void
    {
        $users = db_connect()->table('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.company_id', $companyId)->where('u.active', 1)->where('r.slug', $roleSlug)
            ->select('u.id')->get()->getResultArray();

        foreach ($users as $u) {
            $this->create($companyId, $u['id'], $type, $title, $message, $url);
        }
    }

    public function unreadCount(string $userId, string $companyId): int
    {
        return (int) db_connect()->table('notifications')->where(['user_id' => $userId, 'company_id' => $companyId])->where('read_at IS NULL')->countAllResults();
    }

    public function getForUser(string $userId, string $companyId, int $limit = 20): array
    {
        return db_connect()->table('notifications')->where(['user_id' => $userId, 'company_id' => $companyId])->orderBy('created_at', 'DESC')->limit($limit)->get()->getResultArray();
    }

    public function markRead(string $notificationId, string $userId, string $companyId): bool
    {
        $builder = db_connect()->table('notifications')->where(['id' => $notificationId, 'user_id' => $userId, 'company_id' => $companyId]);
        if (!(clone $builder)->countAllResults()) return false;
        return $builder->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function markAllRead(string $userId, string $companyId): void
    {
        db_connect()->table('notifications')->where(['user_id' => $userId, 'company_id' => $companyId])->where('read_at IS NULL')->update(['read_at' => date('Y-m-d H:i:s')]);
    }
}
