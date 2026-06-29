<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchKpiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BranchKpiController extends Controller
{
    private BranchKpiService $kpiService;

    public function __construct(BranchKpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    public function dashboard(Request $request)
    {
        abort_if(Gate::denies('branch_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $from = Carbon::parse($request->input('from', now()->subDays(7)->toDateString()));
        $to = Carbon::parse($request->input('to', now()->toDateString()));
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        $this->kpiService->refreshRange($from->copy(), $to->copy(), $branchId);
        $data = $this->kpiService->getDashboardData($from, $to, $branchId);

        $branches = Branch::orderBy('title_ar')->get(['id', 'title_ar']);

        return view('admin.branch-kpis.dashboard', [
            'overview' => $data['overview'],
            'rows' => $data['rows'],
            'branches' => $branches,
            'branchId' => $branchId,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }
}
