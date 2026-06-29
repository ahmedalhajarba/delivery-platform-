<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchCapacityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BranchCapacityController extends Controller
{
    protected BranchCapacityService $capacityService;

    public function __construct(BranchCapacityService $capacityService)
    {
        $this->capacityService = $capacityService;
    }

    public function dashboard()
    {
        abort_if(Gate::denies('branch_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $utilization = $this->capacityService->getTodayUtilization();

        $stats = [
            'total_branches' => count($utilization),
            'intake_stopped' => collect($utilization)->where('intake_enabled', false)->count(),
            'regular_exhausted' => collect($utilization)->where('is_regular_exhausted', true)->count(),
            'total_exhausted' => collect($utilization)->where('is_total_exhausted', true)->count(),
            'avg_total_percentage' => collect($utilization)->avg('total_percentage') ?? 0,
        ];

        return view('admin.branch-capacities.dashboard', compact('utilization', 'stats'));
    }

    public function update(Request $request)
    {
        abort_if(Gate::denies('branch_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'daily_waybills_cap' => 'nullable|integer|min:0',
            'subscription_reserved_cap' => 'required|integer|min:0',
            'intake_enabled' => 'nullable|boolean',
        ]);

        $cap = (int) ($validated['daily_waybills_cap'] ?? 0);
        $reserved = (int) $validated['subscription_reserved_cap'];
        if ($cap > 0 && $reserved > $cap) {
            return back()->withErrors([
                'subscription_reserved_cap' => 'لا يمكن أن تتجاوز حصة المشتركين السعة اليومية الكلية للفرع.',
            ])->withInput();
        }

        $branch = Branch::findOrFail((int) $validated['branch_id']);
        $validated['intake_enabled'] = $request->boolean('intake_enabled');

        $this->capacityService->updateBranchCapacity($branch, $validated, auth()->id());

        return back()->with('success', 'تم تحديث سعة الفرع بنجاح.');
    }

    public function logs(Request $request)
    {
        abort_if(Gate::denies('branch_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $date = $request->date ?? now()->toDateString();
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        $logs = $this->capacityService->getLogs($date, $branchId);
        $branches = Branch::orderBy('title_ar')->get(['id', 'title_ar']);

        return view('admin.branch-capacities.logs', compact('logs', 'date', 'branchId', 'branches'));
    }
}
