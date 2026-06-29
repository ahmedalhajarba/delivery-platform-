<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class HomeController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $sections = [];

        if ($this->canAny($user, ['employee_management_access', 'branch_employee_access', 'user_access'])) {
            $sections[] = [
                'key' => 'employees',
                'title' => 'الموظفون',
                'description' => 'متابعة القوى العاملة والأداء والمهام لكل موظف.',
                'stats' => [
                    ['label' => 'إجمالي الموظفين', 'value' => number_format((int) BranchEmployee::count())],
                    ['label' => 'موظفون جدد اليوم', 'value' => number_format((int) BranchEmployee::whereDate('created_at', today())->count())],
                    ['label' => 'مهام HR مفتوحة', 'value' => number_format((int) SupportTicket::where('category', 'target_review')->whereIn('status', ['open', 'in_progress'])->count())],
                ],
                'actions' => [
                    $this->action('إدارة الموظفين', 'admin.employee-management.index'),
                    $this->action('الرواتب', 'admin.payroll.index'),
                    $this->action('بيانات المستخدمين', 'admin.users.index'),
                ],
            ];
        }

        if ($this->canAny($user, ['courier_management_access', 'courier_access', 'courier_booking_access'])) {
            $sections[] = [
                'key' => 'couriers',
                'title' => 'المناديب',
                'description' => 'توزيع الشحنات، الجاهزية، والمتابعة اللحظية للمناديب.',
                'stats' => [
                    ['label' => 'إجمالي المناديب', 'value' => number_format((int) Courier::count())],
                    ['label' => 'المناديب المتاحون الآن', 'value' => number_format((int) Courier::where('status', 'active')->where('is_available', true)->count())],
                    ['label' => 'مهام توصيل نشطة', 'value' => number_format((int) CourierAssignment::whereIn('status', ['assigned', 'accepted', 'picked_up'])->count())],
                ],
                'actions' => [
                    $this->action('إدارة المناديب', 'admin.courier-management.index'),
                    $this->action('لوحة الرحلات', 'admin.trips.index'),
                    $this->action('حجوزات المندوب', 'admin.courier-bookings.index'),
                ],
            ];
        }

        if ($this->canAny($user, ['branch_management_access', 'branch_access'])) {
            $sections[] = [
                'key' => 'branches',
                'title' => 'الفروع',
                'description' => 'مراقبة أداء الفروع والسعة التشغيلية وتوزيع المناطق.',
                'stats' => [
                    ['label' => 'إجمالي الفروع', 'value' => number_format((int) Branch::count())],
                    ['label' => 'فروع بها موظفون', 'value' => number_format((int) Branch::has('branchBranchEmployees')->count())],
                    ['label' => 'متوسط العاملين لكل فرع', 'value' => number_format((float) $this->avgEmployeesPerBranch(), 1)],
                ],
                'actions' => [
                    $this->action('إدارة الفروع', 'admin.branches.index'),
                    $this->action('KPI الفروع', 'admin.branch-kpis.dashboard'),
                    $this->action('السعة التشغيلية', 'admin.branch-capacities.dashboard'),
                ],
            ];
        }

        if ($this->canAny($user, ['branch_employee_access', 'employee_management_access'])) {
            $sections[] = [
                'key' => 'workers',
                'title' => 'العاملون',
                'description' => 'لوحة متابعة فرق التشغيل الميدانية داخل الفروع.',
                'stats' => [
                    ['label' => 'إجمالي العاملين', 'value' => number_format((int) BranchEmployee::count())],
                    ['label' => 'العاملون داخل الفروع', 'value' => number_format((int) BranchEmployee::whereNotNull('branch_id')->count())],
                    ['label' => 'عناوين بريد مسجلة', 'value' => number_format((int) BranchEmployee::whereNotNull('email')->where('email', '!=', '')->count())],
                ],
                'actions' => [
                    $this->action('موظفو الفروع', 'admin.branch-employees.index'),
                    $this->action('أقسام الفروع', 'admin.branch-sections.index'),
                    $this->action('مهام HR', 'admin.hr-tasks.index'),
                ],
            ];
        }

        if ($this->canAny($user, ['customer_access'])) {
            $sections[] = [
                'key' => 'customers',
                'title' => 'العملاء',
                'description' => 'استعلامات حالة الحسابات، العقود، والجوانب المالية للعملاء.',
                'stats' => [
                    ['label' => 'إجمالي العملاء', 'value' => number_format((int) CustomerProfile::count())],
                    ['label' => 'حسابات نشطة', 'value' => number_format((int) CustomerProfile::where('account_status', 'active')->count())],
                    ['label' => 'حسابات قيد الانتظار', 'value' => number_format((int) CustomerProfile::where('account_status', 'pending')->count())],
                ],
                'actions' => [
                    $this->action('إدارة العملاء', 'admin.customers.index'),
                    $this->action('طلبات التفعيل', 'admin.account-activations.index'),
                    $this->action('الحسابات الآجلة', 'admin.account-statements.index'),
                ],
            ];
        }

        if ($this->canAny($user, ['order_access'])) {
            $sections[] = [
                'key' => 'orders',
                'title' => 'الطلبات',
                'description' => 'مؤشرات يومية للشحنات والطلبات والمتابعة التشغيلية.',
                'stats' => [
                    ['label' => 'إجمالي الطلبات', 'value' => number_format((int) Order::count())],
                    ['label' => 'طلبات اليوم', 'value' => number_format((int) Order::whereDate('created_at', today())->count())],
                    ['label' => 'طلبات مرتبطة ببوليصة', 'value' => number_format((int) Order::whereNotNull('waybill_number')->count())],
                ],
                'actions' => [
                    $this->action('قائمة الطلبات', 'admin.orders.index'),
                    $this->action('تتبع الشحنات', 'admin.shipment-tracking.index'),
                    $this->action('إعدادات الطلبات', 'admin.order-settings.index'),
                ],
            ];
        }

        $focus = $sections[0]['title'] ?? 'نظرة عامة';

        $kpis = [
            ['label' => 'إجمالي الطلبات', 'value' => number_format((int) Order::count())],
            ['label' => 'إجمالي العملاء', 'value' => number_format((int) CustomerProfile::count())],
            ['label' => 'إجمالي المناديب', 'value' => number_format((int) Courier::count())],
            ['label' => 'إجمالي الفروع', 'value' => number_format((int) Branch::count())],
            ['label' => 'تذاكر دعم مفتوحة', 'value' => number_format((int) SupportTicket::whereIn('status', ['open', 'in_progress'])->count())],
            ['label' => 'طلبات اليوم', 'value' => number_format((int) Order::whereDate('created_at', today())->count())],
        ];

        $quickActions = [];

        if ($this->canAny($user, ['order_create', 'order_access'])) {
            $quickActions[] = $this->action('إنشاء طلب شحن', 'admin.orders.create');
        }

        if ($this->canAny($user, ['subscription_access', 'subscriptions_plan_access', 'subscriptions_category_access', 'user_subscription_access'])) {
            $quickActions[] = $this->firstExistingAction(
                'الخدمات والاشتراكات',
                [
                    'admin.subscriptions-plans.index',
                    'admin.subscriptions-categories.index',
                    'admin.user-subscriptions.index',
                ]
            );
        }

        return view('home', [
            'dashboardFocus' => $focus,
            'dashboardKpis' => $kpis,
            'dashboardSections' => $sections,
            'dashboardQuickActions' => array_values(array_filter($quickActions)),
            'lastUpdatedAt' => now(),
        ]);
    }

    private function action(string $label, string $routeName): ?array
    {
        if (!Route::has($routeName)) {
            return null;
        }

        return [
            'label' => $label,
            'url' => route($routeName),
        ];
    }

    private function firstExistingAction(string $label, array $routeNames): ?array
    {
        foreach ($routeNames as $routeName) {
            $action = $this->action($label, $routeName);
            if ($action !== null) {
                return $action;
            }
        }

        return null;
    }

    private function avgEmployeesPerBranch(): float
    {
        $totalBranches = Branch::count();
        if ($totalBranches === 0) {
            return 0.0;
        }

        return BranchEmployee::count() / $totalBranches;
    }

    private function canAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (Gate::forUser($user)->allows($permission)) {
                return true;
            }
        }

        return false;
    }
}
