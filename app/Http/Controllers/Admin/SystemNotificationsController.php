<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemNotificationsController extends Controller
{
    /* ── قائمة الإشعارات للمستخدم الحالي ─────────────────────── */
    public function index()
    {
        $userId        = Auth::id();
        $notifications = SystemNotification::forUser($userId)
            ->latest()
            ->paginate(20);

        $unreadCount = SystemNotification::forUser($userId)->unread()->count();

        return view('admin.notifications.index', compact('notifications', 'unreadCount'));
    }

    /* ── الإشعارات غير المقروءة (AJAX dropdown) ──────────────── */
    public function recent()
    {
        $userId = Auth::id();
        $items  = SystemNotification::forUser($userId)
            ->latest()
            ->limit(10)
            ->get();

        $unreadCount = SystemNotification::forUser($userId)->unread()->count();

        return response()->json([
            'notifications' => $items->map(fn($n) => [
                'id'          => $n->id,
                'title'       => $n->title,
                'body'        => $n->body,
                'type'        => $n->type,
                'icon'        => $n->displayIcon(),
                'badge_color' => $n->badgeColor(),
                'link'        => $n->link,
                'link_text'   => $n->link_text,
                'is_unread'   => $n->isUnread(),
                'time_ago'    => $n->created_at->diffForHumans(),
            ]),
            'unread_count'  => $unreadCount,
        ]);
    }

    /* ── قراءة إشعار واحد ─────────────────────────────────────── */
    public function markAsRead(SystemNotification $notification)
    {
        abort_unless($notification->user_id === Auth::id(), 403);
        $notification->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('message', 'تم تعليم الإشعار كمقروء');
    }

    /* ── قراءة كل الإشعارات ──────────────────────────────────── */
    public function markAllRead()
    {
        SystemNotification::forUser(Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('message', 'تم تعليم كل الإشعارات كمقروءة');
    }

    /* ── حذف إشعار ────────────────────────────────────────────── */
    public function destroy(SystemNotification $notification)
    {
        abort_unless($notification->user_id === Auth::id(), 403);
        $notification->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('message', 'تم حذف الإشعار');
    }

    /* ── حذف كل الإشعارات المقروءة ───────────────────────────── */
    public function clearRead()
    {
        SystemNotification::forUser(Auth::id())
            ->whereNotNull('read_at')
            ->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('message', 'تم حذف الإشعارات المقروءة');
    }

    /* ── إرسال إشعار (للمسؤولين فقط) ────────────────────────── */
    public function sendManual(Request $request)
    {
        $request->validate([
            'user_ids'  => 'required|array',
            'user_ids.*'=> 'exists:users,id',
            'title'     => 'required|string|max:255',
            'body'      => 'nullable|string|max:1000',
            'type'      => 'in:info,success,warning,danger',
            'link'      => 'nullable|url|max:500',
        ]);

        NotificationService::sendToMany(
            $request->user_ids,
            $request->title,
            $request->body ?? '',
            $request->type  ?? 'info',
            $request->link  ?? ''
        );

        return redirect()->back()->with('message', 'تم إرسال الإشعار بنجاح');
    }

    /* ── صفحة إرسال الإشعارات اليدوية (Admin) ───────────────── */
    public function compose()
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        return view('admin.notifications.compose', compact('users'));
    }
}
