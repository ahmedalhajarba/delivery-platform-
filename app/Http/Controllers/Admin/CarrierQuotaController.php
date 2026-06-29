<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarrierMonthlyQuota;
use App\Models\CarrierMonthlyConsumption;
use App\Models\CarrierQuotaLog;
use App\Models\CarrierCompany;
use App\Services\CarrierQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class CarrierQuotaController extends Controller
{
    protected $quotaService;

    public function __construct(CarrierQuotaService $quotaService)
    {
        $this->quotaService = $quotaService;
    }

    /**
     * Dashboard: عرض استهلاك البوالص الشهري الحالي.
     */
    public function dashboard(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $utilization = $this->quotaService->getCurrentMonthUtilization();

        // إحصاءات عامة
        $stats = [
            'total_carriers' => count($utilization),
            'exhausted_subscription_pools' => collect($utilization)->where('is_subscription_exhausted', true)->count(),
            'exhausted_total_quotas' => collect($utilization)->where('is_total_exhausted', true)->count(),
            'avg_subscription_percentage' => collect($utilization)->avg('subscription_percentage'),
            'avg_total_percentage' => collect($utilization)->avg('total_percentage'),
        ];

        $alerts = [];
        foreach ($utilization as $item) {
            $warning = $this->quotaService->getQuotaWarning($item['carrier_id']);
            if ($warning) {
                $alerts[] = [
                    'carrier_name' => $item['carrier'],
                    'message' => $warning,
                ];
            }
        }

        return view('admin.carrier-quotas.dashboard', compact('utilization', 'stats', 'alerts'));
    }

    /**
     * Quotas: إدارة إعدادات الحد الأقصى الشهري للناقل.
     */
    public function quotas(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;

            $carriers = CarrierCompany::where('is_active', true)->orderBy('name_ar')->get();
        $quotas = CarrierMonthlyQuota::where('year', $year)
            ->where('month', $month)
            ->with('carrierCompany')
            ->orderBy('carrier_id')
            ->get()
            ->keyBy('carrier_id');

        // تحضير البيانات المعروضة
        $quotasData = $carriers->map(function ($carrier) use ($quotas) {
            $quota = $quotas->get($carrier->id);
            return [
                'carrier' => $carrier,
                'quota' => $quota,
                'total_cap' => $quota?->total_waybills_cap ?? 0,
                'subscription_cap' => $quota?->subscription_pool_cap ?? 0,
            ];
        });

        return view('admin.carrier-quotas.quotas', compact('quotasData', 'year', 'month', 'carriers'));
    }

    /**
     * Store or update quota for a carrier.
     */
    public function storeQuota(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'carrier_id' => 'required|exists:carrier_companies,id',
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'total_waybills_cap' => 'required|integer|min:0',
            'subscription_pool_cap' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // التحقق من أن subscription pool لا يتجاوز total cap
        if ($validated['subscription_pool_cap'] > $validated['total_waybills_cap']) {
            return back()->withErrors([
                'subscription_pool_cap' => 'لا يمكن أن يتجاوز حد باقات الاشتراكات الحد الأقصى الإجمالي.',
            ])->withInput();
        }

        $quota = CarrierMonthlyQuota::updateOrCreate(
            [
                'carrier_id' => $validated['carrier_id'],
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            $validated
        );

        return redirect()->route('admin.carrier-quotas.quotas', [
            'year' => $validated['year'],
            'month' => $validated['month'],
        ])->with('success', 'تم تحديث حد البوالص بنجاح.');
    }

    /**
     * Consumption logs: عرض سجل الاستهلاك.
     */
    public function logs(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;
        $carrierId = $request->carrier_id ?? null;

        $logsQuery = CarrierQuotaLog::where('year', $year)->where('month', $month);

        if ($carrierId) {
            $logsQuery->where('carrier_id', $carrierId);
        }

        $logs = $logsQuery->with(['carrierCompany', 'order', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

            $carriers = CarrierCompany::where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.carrier-quotas.logs', compact('logs', 'year', 'month', 'carrierId', 'carriers'));
    }

    /**
     * Manual adjustment: التعديل اليدوي على استهلاك الحد الأقصى.
     */
    public function manualAdjustment(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'carrier_id' => 'required|exists:carrier_companies,id',
            'adjustment_quantity' => 'required|integer',
            'reason' => 'required|string|max:500',
        ]);

        $this->quotaService->manualAdjustment(
            $validated['carrier_id'],
            $validated['adjustment_quantity'],
            $validated['reason'],
            auth()->id()
        );

        return back()->with('success', 'تم التعديل بنجاح.');
    }

    /**
     * Reset monthly consumption (admin only).
     */
    public function resetMonth(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'carrier_id' => 'nullable|exists:carrier_companies,id',
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
        ]);

        if ($validated['carrier_id']) {
            $this->quotaService->resetMonthlyConsumption(
                $validated['carrier_id'],
                $validated['year'],
                $validated['month']
            );
            $message = 'تم إعادة تعيين استهلاك الناقل بنجاح.';
        } else {
            // Reset all carriers for the month
            $carriers = CarrierCompany::where('is_active', true)->get();
            foreach ($carriers as $carrier) {
                $this->quotaService->resetMonthlyConsumption(
                    $carrier->id,
                    $validated['year'],
                    $validated['month']
                );
            }
            $message = 'تم إعادة تعيين جميع النواقل للشهر المحدد بنجاح.';
        }

        return back()->with('success', $message);
    }

    /**
     * Export report: تصدير تقرير الاستهلاك.
     */
    public function export(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;

        $utilization = $this->quotaService->getCurrentMonthUtilization();

        // Generate CSV
        $filename = "carrier_quotas_{$year}_{$month}.csv";
        $handle = fopen('php://memory', 'r+');

        // UTF-8 BOM improves Arabic display in Excel.
        fwrite($handle, "\xEF\xBB\xBF");

        // Headers
        fputcsv($handle, ['الناقل', 'الحد الأقصى الإجمالي', 'باقات الاشتراكات', 'الشحن الفردي', 'مستهلك من الاشتراكات', 'مستهلك فردي', 'المتبقي من الاشتراكات', 'المتبقي فردي', 'نسبة استهلاك الاشتراكات %', 'نسبة الاستهلاك الكلي %']);

        foreach ($utilization as $item) {
            fputcsv($handle, [
                $item['carrier'],
                $item['total_cap'],
                $item['subscription_pool_cap'],
                $item['individual_pool_cap'],
                $item['subscription_used'],
                $item['individual_used'],
                $item['subscription_remaining'],
                $item['individual_remaining'],
                $item['subscription_percentage'],
                $item['total_percentage'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}
