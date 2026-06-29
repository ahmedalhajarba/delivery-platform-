<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\CashBox;
use App\Models\CashBoxTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CashBoxController extends Controller
{
    /**
     * نموذج إضافة صندوق جديد
     */
    public function create()
    {
        abort_if(!$this->hasAnyPermission(['cash_box_edit', 'cash_box_access', 'order_access']), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::query()->orderBy('title_ar')->get(['id', 'title_ar', 'title_en']);
        $couriers = BranchEmployee::query()->orderBy('name')->get(['id', 'name']);
        $managers = User::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.cashboxes.create', compact('branches', 'couriers', 'managers'));
    }

    /**
     * حفظ صندوق جديد
     */
    public function store(Request $request)
    {
        abort_if(!$this->hasAnyPermission(['cash_box_edit', 'cash_box_access', 'order_access']), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'type' => ['required', 'in:' . implode(',', array_keys(CashBox::TYPE))],
            'channel' => ['required', 'in:' . implode(',', array_keys(CashBox::CHANNEL))],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'courier_id' => ['nullable', 'exists:branch_employees,id'],
            'manager_user_id' => ['nullable', 'exists:users,id'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'gateway_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($data['type'] === 'branch' && empty($data['branch_id'])) {
            return back()->withErrors(['branch_id' => 'يجب اختيار الفرع عند إنشاء صندوق فرع.'])->withInput();
        }

        if ($data['type'] === 'courier' && empty($data['courier_id'])) {
            return back()->withErrors(['courier_id' => 'يجب اختيار المندوب عند إنشاء صندوق مندوب.'])->withInput();
        }

        if ($data['channel'] === 'bank_account' && (empty($data['account_name']) || empty($data['account_number']))) {
            return back()->withErrors(['account_name' => 'اسم الحساب ورقم الحساب البنكي مطلوبان للقناة البنكية.'])->withInput();
        }

        if ($data['channel'] === 'payment_gateway' && empty($data['gateway_name'])) {
            return back()->withErrors(['gateway_name' => 'اسم بوابة الدفع مطلوب لهذه القناة.'])->withInput();
        }

        $openingBalance = (float) ($data['opening_balance'] ?? 0);

        $cashBox = null;
        DB::transaction(function () use (&$cashBox, $data, $openingBalance) {
            $cashBox = CashBox::create([
                'name' => $data['name_ar'], // إضافة العمود name المطلوب
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'type' => $data['type'],
                'channel' => $data['channel'],
                'balance' => 0,
                'branch_id' => $data['type'] === 'branch' ? ($data['branch_id'] ?? null) : null,
                'courier_id' => $data['type'] === 'courier' ? ($data['courier_id'] ?? null) : null,
                'manager_user_id' => $data['manager_user_id'] ?? null,
                'account_name' => $data['account_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'iban' => $data['iban'] ?? null,
                'gateway_name' => $data['gateway_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($openingBalance > 0) {
                $cashBox->deposit($openingBalance, 'رصيد افتتاحي عند إنشاء الصندوق', null, Auth::id());
            }
        });

        return redirect()->route('admin.cashboxes.show', $cashBox)
            ->with('success', 'تم إضافة الصندوق المالي بنجاح.');
    }

    /**
     * قائمة الصناديق
     */
    public function index()
    {
        abort_if(!$this->hasAnyPermission(['cash_box_access', 'invoice_access', 'receipt_access', 'order_access']), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cashBoxes = CashBox::withCount('transactions')
            ->with('branch', 'courier', 'manager')
            ->orderBy('type')
            ->orderBy('name_ar')
            ->get();

        $managers = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get();

        $stats = [
            'total_balance'   => CashBox::where('is_active', true)->sum('balance'),
            'main_balance'    => CashBox::where('type', 'main')->sum('balance'),
            'branch_balance'  => CashBox::where('type', 'branch')->sum('balance'),
            'courier_balance' => CashBox::where('type', 'courier')->sum('balance'),
            'total_boxes'     => CashBox::count(),
            'active_boxes'    => CashBox::where('is_active', true)->count(),
        ];

        return view('admin.cashboxes.index', compact('cashBoxes', 'stats', 'managers'));
    }

    /**
     * عرض صندوق وحركاته
     */
    public function show(Request $request, CashBox $cashbox)
    {
        abort_if(!$this->hasAnyPermission(['cash_box_show', 'cash_box_access', 'invoice_access', 'receipt_access', 'order_access']), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cashbox->loadMissing('manager');

        $query = $cashbox->transactions()->with('receipt.invoice', 'performedBy');

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $transactions = $query->latest()->paginate(30)->withQueryString();

        $periodStats = [
            'total_credit' => (clone $query->getQuery())->where('transaction_type', 'credit')->sum('amount'),
            'total_debit'  => (clone $query->getQuery())->where('transaction_type', 'debit')->sum('amount'),
            'tx_count'     => $transactions->total(),
        ];

        // Daily chart data (last 30 days)
        $chartData = CashBoxTransaction::where('cash_box_id', $cashbox->id)
            ->where('created_at', '>=', now()->subDays(29))
            ->selectRaw('DATE(created_at) as date, transaction_type, SUM(amount) as total')
            ->groupBy('date', 'transaction_type')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        $managerUsers = User::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.cashboxes.show', compact('cashbox', 'transactions', 'periodStats', 'chartData', 'managerUsers'));
    }

    /**
     * تحويل مبلغ بين صندوقين
     */
    public function transfer(Request $request)
    {
        abort_if(!$this->hasAnyPermission(['cash_box_edit', 'cash_box_access', 'order_access']), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'from_box_id' => 'required|exists:cash_boxes,id',
            'to_box_id'   => 'required|exists:cash_boxes,id|different:from_box_id',
            'amount'      => 'required|numeric|min:0.01',
            'notes'       => 'nullable|string|max:500',
        ]);

        $fromBox = CashBox::findOrFail($data['from_box_id']);
        $toBox   = CashBox::findOrFail($data['to_box_id']);

        if ($fromBox->balance < $data['amount']) {
            return back()->withErrors(['amount' => 'رصيد الصندوق المصدر غير كافٍ.'])->withInput();
        }

        DB::transaction(function () use ($fromBox, $toBox, $data) {
            $desc = ($data['notes'] ?? '') ?: ('تحويل إلى ' . $toBox->name_ar);
            $fromBox->withdraw($data['amount'], $desc, Auth::id());

            $desc2 = 'تحويل من ' . $fromBox->name_ar . ($data['notes'] ? ': ' . $data['notes'] : '');
            $toBox->deposit($data['amount'], $desc2, null, Auth::id());
        });

        return back()->with('success', 'تم التحويل بين الصندوقين بنجاح.');
    }

    /**
     * تعيين مسؤول مالي للصندوق
     */
    public function assignManager(Request $request, CashBox $cashbox)
    {
        abort_if(!$this->hasAnyPermission(['cash_box_edit', 'cash_box_access', 'order_access']), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'manager_user_id' => 'required|exists:users,id',
        ]);

        $manager = User::query()->findOrFail($data['manager_user_id']);

        $cashbox->update([
            'manager_user_id' => $manager->id,
        ]);

        return back()->with('success', 'تم تعيين مسؤول الصندوق بنجاح.');
    }

    /**
     * شاشة إدارة مسؤولي الصناديق (تعيين/تبديل/تتبع صلاحيات)
     */
    public function managers(Request $request)
    {
        abort_if(!$this->hasAnyPermission(['cash_box_access', 'invoice_access', 'receipt_access', 'order_access']), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cashBoxes = CashBox::query()
            ->with(['branch', 'courier', 'manager.roles.permissions', 'manager.permissions'])
            ->orderBy('type')
            ->orderBy('name_ar')
            ->get();

        $managerUsers = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->orderBy('name')
            ->get();

        $trackedPermissions = [
            'cash_box_access',
            'cash_box_show',
            'cash_box_edit',
            'receipt_access',
            'invoice_access',
        ];

        $managerCapabilityMap = [];
        foreach ($managerUsers as $manager) {
            $managerCapabilityMap[$manager->id] = [
                'name' => $manager->name,
                'roles' => $manager->roles->pluck('title')->values()->toArray(),
                'permissions' => collect($trackedPermissions)
                    ->mapWithKeys(fn ($permission) => [$permission => $manager->hasPermissionTo($permission)])
                    ->toArray(),
            ];
        }

        $logsQuery = AuditLog::query()
            ->where('subject_type', 'like', 'App\\Models\\CashBox#%')
            ->where('description', 'audit:updated')
            ->latest('id');

        if ($request->filled('cash_box_id')) {
            $logsQuery->where('subject_type', 'like', 'App\\Models\\CashBox#' . (int) $request->cash_box_id);
        }

        $assignmentLogs = $logsQuery->limit(80)->get()->filter(function ($log) {
            return data_get($log, 'properties.manager_user_id') !== null;
        })->values();

        return view('admin.cashboxes.managers', [
            'cashBoxes' => $cashBoxes,
            'managerUsers' => $managerUsers,
            'trackedPermissions' => $trackedPermissions,
            'managerCapabilityMap' => $managerCapabilityMap,
            'assignmentLogs' => $assignmentLogs,
        ]);
    }

    /**
     * تبديل مسؤول الصندوق
     */
    public function switchManager(Request $request, CashBox $cashbox)
    {
        abort_if(!$this->hasAnyPermission(['cash_box_edit', 'cash_box_access', 'order_access']), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'manager_user_id' => 'required|exists:users,id',
            'switch_note' => 'nullable|string|max:500',
        ]);

        $previousManagerName = optional($cashbox->manager)->name;
        $newManager = User::query()->findOrFail($data['manager_user_id']);

        $noteParts = [];
        if ($previousManagerName) {
            $noteParts[] = 'تبديل المسؤول من: ' . $previousManagerName;
        }
        $noteParts[] = 'إلى: ' . $newManager->name;
        if (!empty($data['switch_note'])) {
            $noteParts[] = 'ملاحظة: ' . $data['switch_note'];
        }

        $existingNotes = trim((string) $cashbox->notes);
        $line = '[' . now()->format('Y-m-d H:i') . '] ' . implode(' | ', $noteParts);

        $cashbox->update([
            'manager_user_id' => $newManager->id,
            'notes' => trim($existingNotes . PHP_EOL . $line),
        ]);

        return back()->with('success', 'تم تبديل مسؤول الصندوق بنجاح.');
    }

    private function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (Gate::allows($permission)) {
                return true;
            }
        }

        return false;
    }
}