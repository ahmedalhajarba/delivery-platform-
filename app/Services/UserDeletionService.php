<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use App\Models\WalletTitle;
use App\Models\Contract;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserDeletionService
{
    /**
     * فحص هل يمكن حذف المستخدم نهائياً.
     * يعيد مصفوفة: ['allowed' => bool, 'reasons' => string[]]
     */
    public function canDelete(User $user): array
    {
        $reasons = [];

        // 1. طلبات نشطة
        try {
            $activeOrders = $user->userOrders()
                ->whereHas('order_status', function ($q) {
                    $q->where(function ($s) {
                        $s->whereIn('name_ar', ['قيد التنفيذ', 'قيد المراجعة', 'لم يتم تسليمه بعد', 'مؤجل'])
                          ->orWhereIn('name_en', ['processing', 'in_review', 'not_delivered', 'delayed', 'pending']);
                    });
                })
                ->count();

            if ($activeOrders > 0) {
                $reasons[] = 'لا يمكن حذف الحساب لوجود ' . $activeOrders . ' طلب(ات) نشطة. يُرجى إتمامها أو إلغاؤها أولاً.';
            }
        } catch (Throwable $e) {
            Log::warning('User deletion check failed: active orders', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $reasons[] = 'تعذر التحقق من حالة الطلبات حالياً. لا يمكن حذف الحساب الآن.';
        }

        // 2. اشتراكات فعالة
        try {
            $activeSubscriptions = $user->userUserSubscriptions()
                ->where('subscription_status', 'active')
                ->where(fn($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()))
                ->count();
            if ($activeSubscriptions > 0) {
                $reasons[] = 'لا يمكن حذف الحساب لوجود ' . $activeSubscriptions . ' اشتراك(ات) فعالة. قم بإلغاء الاشتراك أولاً.';
            }
        } catch (Throwable $e) {
            Log::warning('User deletion check failed: subscriptions', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $reasons[] = 'تعذر التحقق من الاشتراكات حالياً. لا يمكن حذف الحساب الآن.';
        }

        // 3. فواتير غير مدفوعة
        try {
            $unpaidInvoices = Invoice::where('user_id', $user->id)
                ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                ->count();
            if ($unpaidInvoices > 0) {
                $reasons[] = 'لا يمكن حذف الحساب لوجود ' . $unpaidInvoices . ' فاتورة(ات) غير مدفوعة. يُرجى تسوية رصيدك أولاً.';
            }
        } catch (Throwable $e) {
            Log::warning('User deletion check failed: invoices', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $reasons[] = 'تعذر التحقق من الفواتير حالياً. لا يمكن حذف الحساب الآن.';
        }

        // 4. رصيد دائن أو مقدم
        try {
            $prepaidBalance = WalletTitle::where('user_id', $user->id)
                ->where('balance', '>', 0)
                ->count();
            if ($prepaidBalance > 0) {
                $reasons[] = 'لا يمكن حذف الحساب لوجود رصيد دائن في المحفظة. تواصل مع الدعم لاسترداده قبل الحذف.';
            }
        } catch (Throwable $e) {
            Log::warning('User deletion check failed: wallet balance', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $reasons[] = 'تعذر التحقق من رصيد المحفظة حالياً. لا يمكن حذف الحساب الآن.';
        }

        // 5. عقود سارية
        try {
            $activeContracts = Contract::where('user_id', $user->id)
                ->where('status', 'active')
                ->where(fn($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
                ->get();
            foreach ($activeContracts as $contract) {
                $until = $contract->end_date ? ' حتى تاريخ ' . $contract->end_date : '';
                $reasons[] = 'لا يمكن حذف الحساب لوجود التزام تعاقدي ساري' . $until . ' (عقد #' . $contract->contract_number . ').';
            }
        } catch (Throwable $e) {
            Log::warning('User deletion check failed: contracts', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $reasons[] = 'تعذر التحقق من العقود حالياً. لا يمكن حذف الحساب الآن.';
        }

        // 6. تذاكر دعم مفتوحة
        try {
            $openTickets = $user->supportTickets()
                ->whereIn('status', ['open', 'in_progress'])
                ->count();
            if ($openTickets > 0) {
                $reasons[] = 'لا يمكن حذف الحساب لوجود ' . $openTickets . ' طلب(ات) دعم مفتوحة. يُرجى إنهاء النزاع أولاً.';
            }
        } catch (Throwable $e) {
            Log::warning('User deletion check failed: support tickets', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $reasons[] = 'تعذر التحقق من طلبات الدعم حالياً. لا يمكن حذف الحساب الآن.';
        }

        return [
            'allowed' => empty($reasons),
            'reasons' => $reasons,
        ];
    }
}
