<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchServiceArea;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Neighborhood;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BranchServiceAreaController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('branch_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $areas = BranchServiceArea::with(['branch', 'governorate', 'city', 'neighborhood'])
            ->orderByDesc('priority')
            ->paginate(50);

        $branches = Branch::query()
            ->where('is_frozen', false)
            ->where('is_blocked', false)
            ->orderBy('title_ar')
            ->get(['id', 'title_ar']);
        $governorates = Governorate::orderBy('title_ar')->get(['id', 'title_ar']);
        $cities = City::orderBy('title_ar')->get(['id', 'title_ar']);
        $neighborhoods = Neighborhood::orderBy('title_ar')->get(['id', 'title_ar']);

        return view('admin.branch-service-areas.index', compact('areas', 'branches', 'governorates', 'cities', 'neighborhoods'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('branch_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'governorate_id' => 'nullable|exists:governorates,id',
            'city_id' => 'nullable|exists:cities,id',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'priority' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if (empty($data['governorate_id']) && empty($data['city_id']) && empty($data['neighborhood_id'])) {
            return back()->withErrors(['city_id' => 'يجب تحديد منطقة واحدة على الأقل (محافظة أو مدينة أو حي).'])->withInput();
        }

        $data['is_active'] = $request->boolean('is_active', true);
        $data['priority'] = (int) ($data['priority'] ?? 1);

        BranchServiceArea::create($data);

        return back()->with('success', 'تم إضافة منطقة خدمة الفرع بنجاح.');
    }

    public function destroy(BranchServiceArea $branchServiceArea)
    {
        abort_if(Gate::denies('branch_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchServiceArea->delete();

        return back()->with('success', 'تم حذف المنطقة بنجاح.');
    }
}
