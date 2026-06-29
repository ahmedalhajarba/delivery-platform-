<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrategicFinance\InvestmentCommitment;
use App\Models\StrategicFinance\InvestmentRound;
use App\Models\StrategicFinance\LegalCase;
use App\Models\StrategicFinance\Obligation;
use App\Models\StrategicFinance\Settlement;
use Illuminate\Http\RedirectResponse;

class StrategicFinanceController extends Controller
{
    public function index()
    {
        $obligationsTotal = (float) Obligation::sum('original_amount');
        $obligationsRemaining = (float) Obligation::sum('outstanding_amount');
        $settledAmount = max($obligationsTotal - $obligationsRemaining, 0);

        $byCategory = Obligation::query()
            ->selectRaw('category, COUNT(*) as total_items, SUM(outstanding_amount) as total_outstanding')
            ->groupBy('category')
            ->orderByDesc('total_outstanding')
            ->get();

        $activeRounds = InvestmentRound::query()
            ->whereIn('status', ['planned', 'open'])
            ->orderBy('planned_open_date')
            ->get();

        $committedCapital = (float) InvestmentCommitment::sum('committed_amount');
        $receivedCapital = (float) InvestmentCommitment::sum('received_amount');

        $recentSettlements = Settlement::query()
            ->with('obligation:id,reference_code,title')
            ->latest('settlement_date')
            ->limit(10)
            ->get();

        $legalStats = [
            'total' => (int) LegalCase::count(),
            'open' => (int) LegalCase::where('status', 'open')->count(),
            'in_progress' => (int) LegalCase::where('status', 'in_progress')->count(),
            'closed' => (int) LegalCase::where('status', 'closed')->count(),
        ];

        return view('admin.strategic-finance.dashboard', [
            'obligationsTotal' => $obligationsTotal,
            'obligationsRemaining' => $obligationsRemaining,
            'settledAmount' => $settledAmount,
            'byCategory' => $byCategory,
            'activeRounds' => $activeRounds,
            'committedCapital' => $committedCapital,
            'receivedCapital' => $receivedCapital,
            'recentSettlements' => $recentSettlements,
            'legalStats' => $legalStats,
        ]);
    }

    public function exitScope(): RedirectResponse
    {
        request()->session()->forget('strategic_scope_active');

        return redirect()->route('admin.home')->with('status', 'تم الخروج من المنظومة الاستراتيجية.');
    }
}
// تم تعطيل هذا الملف نهائياً بعد حذف قاعدة بيانات strategic_finance
// تم حذف هذا الملف بناءً على طلب الإدارة
