<?php

namespace App\Services;

use App\Models\SystemNotification;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    /**
     * إرسال إشعار لمستخدم محدد
     */
    public static function send(
        int    $userId,
        string $title,
        string $body     = '',
        string $type     = 'info',
        string $icon     = '',
        string $link     = '',
        string $linkText = '',
        string $module   = '',
        ?int   $moduleId = null
    ): SystemNotification {
        return SystemNotification::create([
            'user_id'   => $userId,
            'title'     => $title,
            'body'      => $body,
            'type'      => $type,
            'icon'      => $icon ?: null,
            'link'      => $link ?: null,
            'link_text' => $linkText ?: null,
            'module'    => $module ?: null,
            'module_id' => $moduleId,
        ]);
    }

    /**
     * إرسال إشعار للمستخدم الحالي
     */
    public static function sendToCurrentUser(string $title, string $body = '', string $type = 'info', string $link = ''): SystemNotification
    {
        return self::send(Auth::id(), $title, $body, $type, '', $link);
    }

    /**
     * إرسال إشعار لعدة مستخدمين
     */
    public static function sendToMany(array $userIds, string $title, string $body = '', string $type = 'info', string $link = ''): void
    {
        $now = now();
        $rows = array_map(fn($uid) => [
            'user_id'    => $uid,
            'title'      => $title,
            'body'       => $body,
            'type'       => $type,
            'link'       => $link ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_unique($userIds));

        SystemNotification::insert($rows);
    }

    /**
     * إشعار خاص بالطلبات
     */
    public static function orderNotification(int $userId, string $title, string $body, int $orderId, string $type = 'info'): SystemNotification
    {
        return self::send(
            $userId,
            $title,
            $body,
            $type,
            'bi-box-seam',
            route('admin.orders.show', $orderId),
            'عرض الطلب',
            'orders',
            $orderId
        );
    }

    /**
     * إشعار خاص بالاشتراكات
     */
    public static function subscriptionNotification(int $userId, string $title, string $body, int $subscriptionId, string $type = 'info'): SystemNotification
    {
        return self::send(
            $userId,
            $title,
            $body,
            $type,
            'bi-bookmark-star',
            route('admin.user-subscriptions.show', $subscriptionId),
            'عرض الاشتراك',
            'subscriptions',
            $subscriptionId
        );
    }
}
