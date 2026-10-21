<?php

namespace App\Controllers\Api\V1;

use App\Libraries\NotificationService;

class NotificationsController extends BaseApiController
{
    public function index()
    {
        $user = $this->apiUser();
        $notifications = new NotificationService();
        return $this->success([
            'notifications' => $notifications->getForUser($user['id'], (string) ($user['company_id'] ?? '')),
            'unread_count'  => $notifications->unreadCount($user['id'], (string) ($user['company_id'] ?? '')),
        ]);
    }

    public function markRead(string $id)
    {
        $user = $this->apiUser();
        if (!(new NotificationService())->markRead($id, (string) ($user['id'] ?? ''), (string) ($user['company_id'] ?? ''))) {
            return $this->fail('Notificación no disponible.', 404);
        }
        return $this->success(['marked' => true]);
    }

    public function markAllRead()
    {
        $user = $this->apiUser();
        (new NotificationService())->markAllRead($user['id'], (string) ($user['company_id'] ?? ''));
        return $this->success(['marked' => true]);
    }
}
