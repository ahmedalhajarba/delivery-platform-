<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchEmployee;
use App\Models\CashBox;
use App\Models\CourierSettlement;
use App\Models\CourierTripFinancial;
use App\Models\EmployeePayroll;
use App\Models\FinanceDocument;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountStatementController extends Controller
{
    public function index(Request $request)
    {
        $employeeSearch = trim((string) $request->input('employee_q', ''));
        $customerSearch = trim((string) $request->input('customer_q', ''));
        $courierSearch = trim((string) $request->input('courier_q', ''));

        $employees = User::query()
            ->where(function ($q) {
                $q->whereNull('user_type')->orWhere('user_type', '!=', 'customer');
            })
            ->whereHas('roles', function ($q) {
                $q->whereNotIn('title', ['customer']);
            })
            ->when($employeeSearch !== '', function ($q) use ($employeeSearch) {
                $q->where(function ($qq) use ($employeeSearch) {
                    $qq->where('name', 'like', '%' . $employeeSearch . '%')
                        ->orWhere('email', 'like', '%' . $employeeSearch . '%')
                        ->orWhere('mobile', 'like', '%' . $employeeSearch . '%');
                });
            })
            ->orderBy('name')
            ->paginate(12, ['*'], 'employees_page')
            ->appends($request->query());

        $customers = User::query()
            ->where(function ($q) {
                $q->where('user_type', 'customer')
                    ->orWhereHas('roles', function ($rq) {
                        $rq->where('title', 'customer');
                    });
            })
            ->when($customerSearch !== '', function ($q) use ($customerSearch) {
                $q->where(function ($qq) use ($customerSearch) {
                    $qq->where('name', 'like', '%' . $customerSearch . '%')
                        ->orWhere('email', 'like', '%' . $customerSearch . '%')
                        ->orWhere('mobile', 'like', '%' . $customerSearch . '%');
                });
            })
            ->orderBy('name')
            ->paginate(12, ['*'], 'customers_page')
            ->appends($request->query());

        $couriers = BranchEmployee::query()
            ->when($courierSearch !== '', function ($q) use ($courierSearch) {
                $q->where(function ($qq) use ($courierSearch) {
                    $qq->where('name', 'like', '%' . $courierSearch . '%')
                        ->orWhere('mobile', 'like', '%' . $courierSearch . '%')
                        ->orWhere('email', 'like', '%' . $courierSearch . '%');
                });
            })
            ->orderBy('name')
            ->paginate(12, ['*'], 'couriers_page')
            ->appends($request->query());

        return view('admin.account-statements.index', compact(
            'employees',
            'customers',
            'couriers',
            'employeeSearch',
            'customerSearch',
            'courierSearch'
        ));
    }

    public function employee(User $user, Request $request)
    {
        $query = EmployeePayroll::query()->where('user_id', $user->id)->latest('payroll_month');

        if ($request->filled('from')) {
            $query->whereDate('payroll_month', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('payroll_month', '<=', $request->to);
        }

        $payrolls = $query->paginate(18)->appends($request->query());

        $paidPayrollsAmount = EmployeePayroll::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->sum('net_amount');

        $pendingPayrollsAmount = EmployeePayroll::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['draft', 'approved'])
            ->sum('net_amount');

        $paymentVouchers = FinanceDocument::query()
            ->where('document_type', 'payment_voucher')
            ->where('related_user_id', $user->id)
            ->latest('document_date')
            ->limit(20)
            ->get();

        return view('admin.account-statements.employee', compact(
            'user',
            'payrolls',
            'paidPayrollsAmount',
            'pendingPayrollsAmount',
            'paymentVouchers'
        ));
    }

    public function courier(BranchEmployee $courier, Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subMonths(3)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $tripFinancials = CourierTripFinancial::query()
            ->where('branch_employee_id', $courier->id)
            ->whereBetween('trip_date', [$from->toDateString(), $to->toDateString()])
            ->latest('trip_date')
            ->get();

        $settlements = CourierSettlement::query()
            ->where('branch_employee_id', $courier->id)
            ->whereBetween('settlement_date', [$from->toDateString(), $to->toDateString()])
            ->latest('settlement_date')
            ->get();

        $collections = Receipt::query()
            ->where('received_by', $courier->id)
            ->where('status', 'confirmed')
            ->whereBetween('receipt_date', [$from->toDateString(), $to->toDateString()])
            ->latest('receipt_date')
            ->get();

        $cashBoxes = CashBox::query()->where('courier_id', $courier->id)->get();

        $summary = [
            'trips_count' => (int) $tripFinancials->count(),
            'base_wage' => (float) $tripFinancials->sum('base_wage'),
            'commission_amount' => (float) $tripFinancials->sum('commission_amount'),
            'bonus_amount' => (float) $tripFinancials->sum('bonus_amount'),
            'deduction_amount' => (float) $tripFinancials->sum('deduction_amount'),
            'operational_cost' => (float) $tripFinancials->sum('operational_cost'),
            'net_amount' => (float) $tripFinancials->sum('net_amount'),
            'settled_amount' => (float) $settlements->sum('paid_amount'),
            'remaining_settlement_amount' => (float) $settlements->sum('balance_amount'),
            'collections_amount' => (float) $collections->sum('amount'),
            'cashbox_balance' => (float) $cashBoxes->sum('balance'),
        ];

        return view('admin.account-statements.courier', compact(
            'courier',
            'tripFinancials',
            'settlements',
            'collections',
            'summary',
            'from',
            'to'
        ));
    }

    public function customer(User $user, Request $request)
    {
        $invoicesQuery = Invoice::query()->where('user_id', $user->id)->latest('issue_date');
        $receiptsQuery = Receipt::query()->where('user_id', $user->id)->where('status', 'confirmed')->latest('receipt_date');

        if ($request->filled('from')) {
            $invoicesQuery->whereDate('issue_date', '>=', $request->from);
            $receiptsQuery->whereDate('receipt_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $invoicesQuery->whereDate('issue_date', '<=', $request->to);
            $receiptsQuery->whereDate('receipt_date', '<=', $request->to);
        }

        $invoices = $invoicesQuery->paginate(15, ['*'], 'invoices_page')->appends($request->query());
        $receipts = $receiptsQuery->paginate(15, ['*'], 'receipts_page')->appends($request->query());

        $summary = [
            'total_invoices' => (float) Invoice::where('user_id', $user->id)->sum('total_amount'),
            'total_paid' => (float) Receipt::where('user_id', $user->id)->where('status', 'confirmed')->sum('amount'),
            'total_remaining' => (float) Invoice::where('user_id', $user->id)->sum('remaining_amount'),
        ];

        return view('admin.account-statements.customer', compact('user', 'invoices', 'receipts', 'summary'));
    }

    /**
     * عرض الحركات البنكية الديناميكية
     */
    public function bankDynamic(Request $request)
    {
        // جلب أول 100 حركة بنكية من جدول bank_statement
        $bankStatements = \DB::table('bank_statement')
            ->orderByDesc('operation_date')
            ->limit(100)
            ->get();

        return view('admin.account-statements.bank-dynamic', compact('bankStatements'));
    }
}