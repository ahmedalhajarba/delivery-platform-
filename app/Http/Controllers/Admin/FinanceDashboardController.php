<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\CashBox;
use App\Models\CashBoxTransaction;
use App\Models\Order;
use App\Models\CustomerProfile;
use Illuminate\Support\Facades\DB;

class FinanceDashboardController extends Controller
{
    public function index()
    {
        // ─── KPI Cards ───────────────────────────────────────────────
        $kpis = [
            'total_revenue'      => Invoice::where('status', 'paid')->sum('total_amount'),
            'monthly_revenue'    => Invoice::where('status', 'paid')->whereMonth('issue_date', now()->month)->sum('total_amount'),
            'total_outstanding'  => Invoice::whereIn('status', ['issued','partially_paid','overdue'])->sum('remaining_amount'),
            'overdue_amount'     => Invoice::where('status', 'overdue')->sum('remaining_amount'),
            'invoices_this_month'=> Invoice::whereMonth('issue_date', now()->month)->count(),
            'receipts_today'     => Receipt::where('status', 'confirmed')->whereDate('receipt_date', today())->sum('amount'),
            'total_cashbox'      => CashBox::where('is_active', true)->sum('balance'),
            'deferred_credit_used' => CustomerProfile::where('billing_type', 'deferred')->sum('credit_used'),
        ];

        // ─── Invoices by status ───────────────────────────────────────
        $invoicesByStatus = Invoice::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // ─── Monthly revenue (last 12 months) ────────────────────────
        $monthlyRevenue = Invoice::where('status', 'paid')
            ->where('issue_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE_FORMAT(issue_date, "%Y-%m") as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // ─── Daily collections (last 30 days) ────────────────────────
        $dailyCollections = Receipt::where('status', 'confirmed')
            ->where('receipt_date', '>=', now()->subDays(29))
            ->selectRaw('DATE(receipt_date) as day, SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        // ─── Payment method breakdown ─────────────────────────────────
        $paymentMethods = Receipt::where('status', 'confirmed')
            ->whereMonth('receipt_date', now()->month)
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get();

        // ─── Top deferred customers ───────────────────────────────────
        $topDeferred = CustomerProfile::with('user')
            ->where('billing_type', 'deferred')
            ->where('account_status', 'active')
            ->where('credit_used', '>', 0)
            ->orderByDesc('credit_used')
            ->limit(5)
            ->get();

        // ─── Recent receipts ──────────────────────────────────────────
        $recentReceipts = Receipt::with('invoice', 'cashBox')
            ->where('status', 'confirmed')
            ->latest('receipt_date')
            ->limit(10)
            ->get();

        // ─── Overdue invoices ─────────────────────────────────────────
        $overdueInvoices = Invoice::with('company', 'user')
            ->where('status', 'overdue')
            ->orderByDesc('remaining_amount')
            ->limit(10)
            ->get();

        // ─── Cashboxes ────────────────────────────────────────────────
        $cashBoxes = CashBox::where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.finance.dashboard', compact(
            'kpis', 'invoicesByStatus', 'monthlyRevenue', 'dailyCollections',
            'paymentMethods', 'topDeferred', 'recentReceipts', 'overdueInvoices', 'cashBoxes'
        ));
    }
}
