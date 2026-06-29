<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\QuotationExtraService;
use App\Models\QuotationPricingLine;
use App\Models\SubscriptionsPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $query = Quotation::with(['user'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('deal_stage')) {
            $query->where('deal_stage', $request->deal_stage);
        }

        if ($request->filled('search')) {
            $q = trim((string)$request->search);
            $query->where(function ($sub) use ($q) {
                $sub->where('quotation_number', 'like', '%' . $q . '%')
                    ->orWhere('notes', 'like', '%' . $q . '%')
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('name', 'like', '%' . $q . '%');
                    });
            });
        }

        $quotations = $query->paginate(20)->appends($request->query());
        $statuses = Quotation::getStatusLabels();
        $dealStages = Quotation::getDealStageLabels();
        $customers = User::query()->orderBy('name')->limit(300)->get();

        return view('admin.quotations.index', compact('quotations', 'statuses', 'dealStages', 'customers'));
    }

    public function create()
    {
        $customers = User::query()->orderBy('name')->limit(300)->get();
        $serviceTypes = $this->platformServiceTypes();

        return view('admin.quotations.create', compact('customers', 'serviceTypes'));
    }

    public function store(Request $request)
    {
        $serviceTypes = $this->platformServiceTypes();

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string',
            'pricing_lines' => 'nullable|array',
            'pricing_lines.*.service_type' => 'required_with:pricing_lines|string|in:' . implode(',', array_keys($serviceTypes)),
        ]);

        $quotation = DB::transaction(function () use ($request) {
            $quotation = Quotation::create([
                'user_id' => $request->user_id,
                'created_by' => auth()->id(),
                'status' => 'draft',
                'deal_stage' => 'proposal',
                'deal_probability' => 15,
                'valid_until' => $request->valid_until,
                'notes' => $request->notes,
            ]);

            foreach (($request->pricing_lines ?? []) as $line) {
                if (empty($line['service_type'])) {
                    continue;
                }
                $quotation->pricingLines()->create($line);
            }

            foreach (($request->extra_services ?? []) as $svc) {
                $quotation->extraServices()->create($svc);
            }

            return $quotation;
        });

        return redirect()->route('admin.quotations.show', $quotation)->with('success', 'تم إنشاء عرض السعر بنجاح.');
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['user', 'pricingLines', 'extraServices']);
        return view('admin.quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation)
    {
        $customers = User::query()->orderBy('name')->limit(300)->get();
        $serviceTypes = $this->platformServiceTypes();
        $quotation->load(['pricingLines', 'extraServices']);

        return view('admin.quotations.edit', compact('quotation', 'customers', 'serviceTypes'));
    }

    public function update(Request $request, Quotation $quotation)
    {
        $serviceTypes = $this->platformServiceTypes();

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string',
            'pricing_lines' => 'nullable|array',
            'pricing_lines.*.service_type' => 'required_with:pricing_lines|string|in:' . implode(',', array_keys($serviceTypes)),
        ]);

        DB::transaction(function () use ($request, $quotation) {
            $quotation->update([
                'user_id' => $request->user_id,
                'valid_until' => $request->valid_until,
                'notes' => $request->notes,
            ]);

            $quotation->pricingLines()->delete();
            foreach (($request->pricing_lines ?? []) as $line) {
                if (empty($line['service_type'])) {
                    continue;
                }
                $quotation->pricingLines()->create($line);
            }

            $quotation->extraServices()->delete();
            foreach (($request->extra_services ?? []) as $svc) {
                $quotation->extraServices()->create($svc);
            }
        });

        return redirect()->route('admin.quotations.show', $quotation)->with('success', 'تم تحديث عرض السعر.');
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();
        return redirect()->route('admin.quotations.index')->with('success', 'تم حذف عرض السعر.');
    }

    public function send(Quotation $quotation)
    {
        $quotation->update(['status' => 'sent']);
        return back()->with('success', 'تم إرسال العرض.');
    }

    public function approve(Quotation $quotation)
    {
        $quotation->update(['status' => 'approved']);
        return back()->with('success', 'تم اعتماد العرض.');
    }

    public function reject(Request $request, Quotation $quotation)
    {
        $request->validate(['comment' => 'required|string']);
        $quotation->update(['status' => 'rejected', 'internal_notes' => $request->comment]);
        return back()->with('success', 'تم رفض العرض.');
    }

    public function convertToContract(Quotation $quotation)
    {
        return redirect()->route('admin.quotations.show', $quotation)
            ->with('info', 'التحويل إلى عقد غير مُفعل في هذه النسخة.');
    }

    public function updateStage(Request $request, Quotation $quotation)
    {
        $request->validate([
            'deal_stage' => 'required|in:' . implode(',', array_keys(Quotation::DEAL_STAGES)),
        ]);

        $quotation->update(['deal_stage' => $request->deal_stage]);
        return back()->with('success', 'تم تحديث مرحلة الصفقة.');
    }

    public function storeActivity(Request $request, Quotation $quotation)
    {
        $request->validate(['content' => 'nullable|string']);
        $notes = trim((string)$quotation->internal_notes . PHP_EOL . '[Activity] ' . (string)$request->content);
        $quotation->update(['internal_notes' => $notes]);
        return back()->with('success', 'تم تسجيل النشاط.');
    }

    public function pipeline(Request $request)
    {
        $pipeline = [];
        foreach (Quotation::DEAL_STAGES as $key => $label) {
            $items = Quotation::with('user')->where('deal_stage', $key)->latest()->get();
            $pipeline[$key] = [
                'label' => $label,
                'quotations' => $items,
            ];
        }

        return view('admin.quotations.pipeline', compact('pipeline'));
    }

    private function platformServiceTypes(): array
    {
        $types = [
            'single_order_domestic' => 'طلب فردي - شحن محلي',
            'single_order_international' => 'طلب فردي - شحن دولي',
            'extra_services' => 'خدمات إضافية',
        ];

        $plans = SubscriptionsPlan::query()
            ->where('status', '0')
            ->orderBy('m_price')
            ->get(['id', 'title_ar', 'title_en']);

        foreach ($plans as $plan) {
            $types['subscription_plan_' . $plan->id] = 'اشتراك - ' . ($plan->title_ar ?: $plan->title_en ?: ('#' . $plan->id));
        }

        return $types;
    }
}
