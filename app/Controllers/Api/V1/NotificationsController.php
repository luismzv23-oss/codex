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
            'notifications' => $notifications->getForUser($user['id']),
            'unread_count'  => $notifications->unreadCount($user['id']),
        ]);
    }

    public function markRead(string $id)
    {
        (new NotificationService())->markRead($id);
        return $this->success(['marked' => true]);
    }

    public function markAllRead()
    {
        $user = $this->apiUser();
        (new NotificationService())->markAllRead($user['id']);
        return $this->success(['marked' => true]);
    }
}
