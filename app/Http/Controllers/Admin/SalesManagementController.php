<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountActivationRequest;
use App\Models\BillingSetting;
use App\Models\Branch;
use App\Models\City;
use App\Models\Contract;
use App\Models\CustomerProfile;
use App\Models\CustomerSalesFollowup;
use App\Models\Governorate;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesCommission;
use App\Models\SalesDiscountCode;
use App\Models\SalesIncentiveRule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SalesManagementController extends Controller
{
    public function billingSettings()
    {
        $this->authorizeFinanceAccess();

        $settings = BillingSetting::current();

        return view('admin.sales-management.billing-settings', compact('settings'));
    }

    public function saveBillingSettings(Request $request)
    {
        $this->authorizeFinanceAccess();

        $data = $request->validate([
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:100',
            'bank_branch' => 'nullable|string|max:255',
            'default_currency' => 'nullable|string|max:10',
            'vat_number' => 'nullable|string|max:100',
            'finance_early_discount_percent' => 'nullable|numeric|min:0|max:100',
            'sales_default_commission_percent' => 'nullable|numeric|min:0|max:100',
            'settlement_mode' => 'nullable|in:manual,auto',
            'auto_settlement_generation' => 'nullable|in:none,referral,coupon,both',
            'referral_new_customer_commission_enabled' => 'nullable|boolean',
            'referral_new_customer_commission_amount' => 'nullable|numeric|min:0',
            'coupon_sales_commission_enabled' => 'nullable|boolean',
            'coupon_sales_commission_percent' => 'nullable|numeric|min:0|max:100',
            'payment_instructions' => 'nullable|string|max:5000',
        ]);

        $data['referral_new_customer_commission_enabled'] = $request->boolean('referral_new_customer_commission_enabled');
        $data['coupon_sales_commission_enabled'] = $request->boolean('coupon_sales_commission_enabled');

        $settings = BillingSetting::current();
        $settings->update($data);

        return back()->with('success', 'تم حفظ إعدادات الفوترة البنكية بنجاح.');
    }

    public function discountCodes()
    {
        $codes = SalesDiscountCode::query()->with('ownerSalesUser')->latest('id')->paginate(20);
        $customers = User::query()->where('user_type', 'customer')->orderBy('name')->get(['id', 'name']);
        $salesUsers = User::query()->whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))->orderBy('name')->get(['id', 'name']);

        return view('admin.sales-management.discount-codes', compact('codes', 'customers', 'salesUsers'));
    }

    public function storeDiscountCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:100|unique:sales_discount_codes,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'scope' => 'required|in:all_customers,selected_customers',
            'allowed_role' => 'required|in:sales,finance,both',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'owner_sales_user_id' => 'nullable|exists:users,id',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:users,id',
        ]);

        $code = SalesDiscountCode::query()->create([
            'code' => strtoupper(trim($data['code'])),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'scope' => $data['scope'],
            'allowed_role' => $data['allowed_role'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => auth()->id(),
            'owner_sales_user_id' => $data['owner_sales_user_id'] ?? null,
        ]);

        if (($data['scope'] ?? 'all_customers') === 'selected_customers') {
            $code->customers()->sync($data['customer_ids'] ?? []);
        }

        return back()->with('success', 'تم إنشاء كود الخصم بنجاح.');
    }

    public function updateDiscountCode(Request $request, SalesDiscountCode $code)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'scope' => 'required|in:all_customers,selected_customers',
            'allowed_role' => 'required|in:sales,finance,both',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'owner_sales_user_id' => 'nullable|exists:users,id',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:users,id',
        ]);

        $code->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'scope' => $data['scope'],
            'allowed_role' => $data['allowed_role'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'owner_sales_user_id' => $data['owner_sales_user_id'] ?? null,
        ]);

        if (($data['scope'] ?? 'all_customers') === 'selected_customers') {
            $code->customers()->sync($data['customer_ids'] ?? []);
        } else {
            $code->customers()->sync([]);
        }

        return back()->with('success', 'تم تحديث كود الخصم.');
    }

    public function referralLinks()
    {
        $salesUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))
            ->orderBy('name')
            ->get();

        foreach ($salesUsers as $user) {
            if (!$user->sales_referral_code) {
                $user->sales_referral_code = $this->generateReferralCode($user->id);
                $user->save();
            }
        }

        $salesUsers->loadCount('referredCustomers');

        return view('admin.sales-management.referral-links', compact('salesUsers'));
    }

    private function generateReferralCode(int $userId): string
    {
        do {
            $code = 'SR-' . $userId . '-' . strtoupper(Str::random(6));
        } while (User::query()->where('sales_referral_code', $code)->exists());

        return $code;
    }

    public function incentives()
    {
        $rules = SalesIncentiveRule::query()->orderBy('role_type')->orderBy('basis')->orderBy('min_amount')->get();

        return view('admin.sales-management.incentives', compact('rules'));
    }

    public function storeIncentive(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'role_type' => 'required|in:sales,finance',
            'basis' => 'required|in:invoice,contract',
            'min_amount' => 'required|numeric|min:0',
            'commission_type' => 'required|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
            'bonus_threshold_amount' => 'nullable|numeric|min:0',
            'bonus_amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        SalesIncentiveRule::query()->create([
            'name' => $data['name'],
            'role_type' => $data['role_type'],
            'basis' => $data['basis'],
            'min_amount' => $data['min_amount'],
            'commission_type' => $data['commission_type'],
            'commission_value' => $data['commission_value'],
            'bonus_threshold_amount' => $data['bonus_threshold_amount'] ?? null,
            'bonus_amount' => $data['bonus_amount'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('success', 'تم إنشاء قاعدة الحوافز/العمولة.');
    }

    public function updateIncentive(Request $request, SalesIncentiveRule $rule)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'min_amount' => 'required|numeric|min:0',
            'commission_type' => 'required|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
            'bonus_threshold_amount' => 'nullable|numeric|min:0',
            'bonus_amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $rule->update([
            'name' => $data['name'],
            'min_amount' => $data['min_amount'],
            'commission_type' => $data['commission_type'],
            'commission_value' => $data['commission_value'],
            'bonus_threshold_amount' => $data['bonus_threshold_amount'] ?? null,
            'bonus_amount' => $data['bonus_amount'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('success', 'تم تحديث قاعدة الحوافز.');
    }

    public function commissions(Request $request)
    {
        $query = SalesCommission::query()->with(['user', 'approvedBy'])->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $commissions = $query->paginate(30)->appends($request->query());
        $salesUsers = User::query()->whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))->get(['id', 'name']);

        return view('admin.sales-management.commissions', compact('commissions', 'salesUsers'));
    }

    public function updateCommissionStatus(Request $request, SalesCommission $commission)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,approved,paid',
            'notes' => 'nullable|string|max:2000',
        ]);

        $payload = [
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $commission->notes,
        ];

        if ($data['status'] === 'approved') {
            $payload['approved_by'] = auth()->id();
        }
        if ($data['status'] === 'paid') {
            $payload['paid_at'] = now();
            $payload['approved_by'] = $commission->approved_by ?: auth()->id();
        }

        $commission->update($payload);

        return back()->with('success', 'تم تحديث حالة العمولة.');
    }

    public function kpi(Request $request)
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $month = (int) ($request->input('month') ?: now()->month);

        $from = now()->setDate($year, $month, 1)->startOfDay();
        $to = (clone $from)->endOfMonth();

        $salesUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))
            ->get();

        $rows = $salesUsers->map(function (User $user) use ($from, $to) {
            $assignedCustomers = CustomerProfile::query()->where('sales_rep_id', $user->id)->count();
            $quotationsCount = Quotation::query()->where('created_by', $user->id)->whereBetween('created_at', [$from, $to])->count();
            $contractsCount = Contract::query()->where('created_by', $user->id)->whereBetween('created_at', [$from, $to])->count();
            $activatedAccounts = AccountActivationRequest::query()->where('assigned_to', $user->id)->where('status', 'activated')->whereBetween('updated_at', [$from, $to])->count();
            $billedAmount = Invoice::query()->where('sales_owner_id', $user->id)->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])->sum('total_amount');
            $commissionAmount = SalesCommission::query()->where('user_id', $user->id)->whereBetween('created_at', [$from, $to])->sum('net_amount');
            $followupsDone = CustomerSalesFollowup::query()->where('sales_user_id', $user->id)->where('status', 'done')->whereBetween('updated_at', [$from, $to])->count();

            return [
                'user' => $user,
                'assigned_customers' => $assignedCustomers,
                'quotations_count' => $quotationsCount,
                'contracts_count' => $contractsCount,
                'activated_accounts' => $activatedAccounts,
                'billed_amount' => (float) $billedAmount,
                'commission_amount' => (float) $commissionAmount,
                'followups_done' => $followupsDone,
            ];
        });

        return view('admin.sales-management.kpi', compact('rows', 'year', 'month'));
    }

    public function followups(Request $request)
    {
        $query = CustomerSalesFollowup::query()->with(['customer', 'salesUser', 'branch', 'governorate', 'city', 'creator'])->latest('id');

        if ($request->filled('sales_user_id')) {
            $query->where('sales_user_id', $request->sales_user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_branch_id')) {
            $query->where('assigned_branch_id', $request->assigned_branch_id);
        }
        if ($request->filled('assigned_governorate_id')) {
            $query->where('assigned_governorate_id', $request->assigned_governorate_id);
        }
        if ($request->filled('task_type')) {
            $query->where('task_type', $request->task_type);
        }

        $followups = $query->paginate(20)->appends($request->query());

        $customers = User::query()->where('user_type', 'customer')->orderBy('name')->get(['id', 'name']);
        $salesUsers = User::query()->whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))->orderBy('name')->get(['id', 'name']);
        $branches = Branch::query()->orderBy('title_ar')->get(['id', 'title_ar']);
        $governorates = Governorate::query()->orderBy('title_ar')->get(['id', 'title_ar']);
        $cities = City::query()->orderBy('title_ar')->get(['id', 'title_ar']);
        $taskTypes = CustomerSalesFollowup::TASK_TYPES;
        $settlementStatuses = CustomerSalesFollowup::SETTLEMENT_STATUSES;

        return view('admin.sales-management.followups', compact('followups', 'customers', 'salesUsers', 'branches', 'governorates', 'cities', 'taskTypes', 'settlementStatuses'));
    }

    public function storeFollowup(Request $request)
    {
        $this->authorizeSalesManagerAccess();

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'sales_user_id' => 'required|exists:users,id',
            'assigned_branch_id' => 'nullable|exists:branches,id',
            'assigned_governorate_id' => 'nullable|exists:governorates,id',
            'assigned_city_id' => 'nullable|exists:cities,id',
            'related_type' => 'nullable|string|max:100',
            'related_id' => 'nullable|integer|min:1',
            'followup_date' => 'nullable|date',
            'status' => 'required|in:planned,done,postponed,cancelled',
            'channel' => 'required|in:call,meeting,whatsapp,email,other',
            'task_type' => 'required|in:customer_followup,lead_followup,subscription_followup,contract_followup,payment_followup',
            'summary' => 'required|string|max:5000',
            'next_action' => 'nullable|string|max:5000',
            'next_followup_at' => 'nullable|date',
            'target_year' => 'nullable|integer|min:2020|max:2100',
            'target_month' => 'nullable|integer|min:1|max:12',
            'target_amount' => 'nullable|numeric|min:0',
            'assignment_note' => 'nullable|string|max:5000',
        ]);

        CustomerSalesFollowup::query()->create(array_merge($data, [
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', 'تم إضافة المتابعة بنجاح.');
    }

    public function updateFollowupStatus(Request $request, CustomerSalesFollowup $followup)
    {
        $data = $request->validate([
            'status' => 'required|in:planned,done,postponed,cancelled',
            'next_followup_at' => 'nullable|date',
            'next_action' => 'nullable|string|max:5000',
            'achieved_amount' => 'nullable|numeric|min:0',
            'commission_due' => 'nullable|numeric|min:0',
            'incentive_due' => 'nullable|numeric|min:0',
            'settlement_status' => 'nullable|in:pending,approved,paid,disputed',
        ]);

        $followup->update($data);

        return back()->with('success', 'تم تحديث حالة المتابعة.');
    }

    public function salesTeamAssignments()
    {
        $this->authorizeSalesManagerAccess();

        $salesUsers = User::query()
            ->with(['salesBranch', 'salesGovernorate', 'salesCity'])
            ->whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))
            ->orderBy('name')
            ->get();

        $branches = Branch::query()->orderBy('title_ar')->get(['id', 'title_ar']);
        $governorates = Governorate::query()->orderBy('title_ar')->get(['id', 'title_ar']);
        $cities = City::query()->orderBy('title_ar')->get(['id', 'title_ar']);

        return view('admin.sales-management.sales-team-assignments', compact('salesUsers', 'branches', 'governorates', 'cities'));
    }

    public function updateSalesTeamAssignment(Request $request, User $user)
    {
        $this->authorizeSalesManagerAccess();

        $data = $request->validate([
            'sales_branch_id' => 'nullable|exists:branches,id',
            'sales_governorate_id' => 'nullable|exists:governorates,id',
            'sales_city_id' => 'nullable|exists:cities,id',
        ]);

        $user->update($data);

        return back()->with('success', 'تم تحديث ربط موظف المبيعات بالفرع والمنطقة.');
    }

    public function settlements(Request $request)
    {
        $query = SalesCommission::query()->with(['user', 'approvedBy'])->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('period_year')) {
            $query->where('period_year', $request->period_year);
        }
        if ($request->filled('period_month')) {
            $query->where('period_month', $request->period_month);
        }
        if ($request->filled('settlement_status')) {
            $query->where('settlement_status', $request->settlement_status);
        }

        $rows = $query->paginate(30)->appends($request->query());
        $salesUsers = User::query()->whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))->orderBy('name')->get(['id', 'name']);

        return view('admin.sales-management.settlements', compact('rows', 'salesUsers'));
    }

    public function storeSettlement(Request $request)
    {
        $this->authorizeSalesManagerAccess();

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_year' => 'required|integer|min:2020|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
            'target_amount' => 'nullable|numeric|min:0',
            'achieved_amount' => 'nullable|numeric|min:0',
            'base_amount' => 'nullable|numeric|min:0',
            'commission_amount' => 'nullable|numeric|min:0',
            'bonus_amount' => 'nullable|numeric|min:0',
            'incentive_adjustment' => 'nullable|numeric',
            'discount_impact_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $target = (float) ($data['target_amount'] ?? 0);
        $achieved = (float) ($data['achieved_amount'] ?? 0);
        $achievement = $target > 0 ? round(($achieved / $target) * 100, 2) : 0;

        $commissionAmount = (float) ($data['commission_amount'] ?? 0);
        $bonusAmount = (float) ($data['bonus_amount'] ?? 0);
        $incentiveAdjustment = (float) ($data['incentive_adjustment'] ?? 0);
        $discountImpact = (float) ($data['discount_impact_amount'] ?? 0);
        $netAmount = $commissionAmount + $bonusAmount;
        $settlementAmount = $netAmount + $incentiveAdjustment - $discountImpact;

        SalesCommission::query()->updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'role_type' => 'sales',
                'source_type' => 'monthly_settlement',
                'source_id' => ((int) $data['period_year']) * 100 + (int) $data['period_month'],
            ],
            [
                'period_year' => $data['period_year'],
                'period_month' => $data['period_month'],
                'target_amount' => $target,
                'achieved_amount' => $achieved,
                'target_achievement_percent' => $achievement,
                'base_amount' => $data['base_amount'] ?? 0,
                'commission_amount' => $commissionAmount,
                'bonus_amount' => $bonusAmount,
                'net_amount' => $netAmount,
                'incentive_adjustment' => $incentiveAdjustment,
                'discount_impact_amount' => $discountImpact,
                'settlement_amount' => $settlementAmount,
                'status' => 'pending',
                'settlement_status' => 'pending',
                'calculated_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('success', 'تم إنشاء/تحديث مقاصة الشهر بنجاح.');
    }

    public function updateSettlementStatus(Request $request, SalesCommission $commission)
    {
        $this->authorizeSalesManagerAccess();

        $data = $request->validate([
            'settlement_status' => 'required|in:pending,approved,paid,disputed',
            'notes' => 'nullable|string|max:2000',
        ]);

        $payload = [
            'settlement_status' => $data['settlement_status'],
            'notes' => $data['notes'] ?? $commission->notes,
        ];

        if ($data['settlement_status'] === 'approved') {
            $payload['status'] = 'approved';
            $payload['approved_by'] = auth()->id();
        }
        if ($data['settlement_status'] === 'paid') {
            $payload['status'] = 'paid';
            $payload['approved_by'] = $commission->approved_by ?: auth()->id();
            $payload['paid_at'] = now();
        }

        $commission->update($payload);

        return back()->with('success', 'تم تحديث حالة المقاصة.');
    }

    private function authorizeSalesManagerAccess(): void
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->is_admin) {
            return;
        }

        $hasManagerRole = $user->roles()
            ->whereIn('title', ['sales_manager'])
            ->exists();

        abort_unless($hasManagerRole, 403, 'Only sales manager can assign and settle sales tasks.');
    }

    private function authorizeFinanceAccess(): void
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->is_admin) {
            return;
        }

        $hasFinanceRole = $user->roles()
            ->whereIn('title', ['finance', 'finance_manager'])
            ->exists();

        abort_unless($hasFinanceRole, 403, 'Unauthorized finance settings access.');
    }
}
