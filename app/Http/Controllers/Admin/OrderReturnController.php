<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtraServiceSetting;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderReturnController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderReturn::with(['originalOrder.sender', 'originalOrder.recipient', 'creator'])
            ->latest();

        if ($request->filled('order_id')) {
            $query->where('original_order_id', (int) $request->order_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('return_waybill_number', 'like', "%$s%")
                  ->orWhereHas('originalOrder', fn($r) => $r->where('waybill_number', 'like', "%$s%"));
            });
        }

        $returns = $query->paginate(20)->withQueryString();
        return view('admin.orderReturns.index', compact('returns'));
    }

    public function create(Order $order)
    {
        $settings       = ExtraServiceSetting::first();
        $existingReturn = OrderReturn::where('original_order_id', $order->id)->first();
        return view('admin.orderReturns.create', compact('order', 'settings', 'existingReturn'));
    }

    public function store(Request $request, Order $order)
    {
        // التحقق من عدم وجود إرجاع سابق
        if (OrderReturn::where('original_order_id', $order->id)->exists()) {
            return back()->with('error', 'يوجد طلب إرجاع مسبق لهذه الشحنة');
        }

        $data = $request->validate([
            'return_reason'      => ['required', 'in:' . implode(',', array_keys(OrderReturn::RETURN_REASONS))],
            'return_reason_note' => ['nullable', 'string', 'max:500'],
            'return_cost'        => ['required', 'numeric', 'min:0'],
        ]);

        // توليد رقم بوليصة الإرجاع
        $returnWaybill = 'RET-' . strtoupper(Str::random(8));
        while (OrderReturn::where('return_waybill_number', $returnWaybill)->exists()) {
            $returnWaybill = 'RET-' . strtoupper(Str::random(8));
        }

        // إنشاء طلب الإرجاع
        $orderReturn = OrderReturn::create([
            'original_order_id'  => $order->id,
            'return_reason'      => $data['return_reason'],
            'return_reason_note' => $data['return_reason_note'],
            'return_cost'        => $data['return_cost'],
            'return_waybill_number' => $returnWaybill,
            'status'             => 'pending',
            'created_by'         => auth()->id(),
        ]);

        // تحديث حالة الطلب الأصلي إلى "إرجاع"
        $returnStatus = OrderStatus::where('name_ar', 'like', '%إرجاع%')
                                   ->orWhere('name_en', 'like', '%return%')
                                   ->first();
        if ($returnStatus) {
            $order->update(['order_status_id' => $returnStatus->id]);
        }

        return redirect()->route('admin.order-returns.waybill', $orderReturn)
            ->with('message', 'تم إنشاء طلب الإرجاع برقم بوليصة: ' . $returnWaybill);
    }

    public function updateStatus(Request $request, OrderReturn $orderReturn)
    {
        $request->validate(['status' => ['required', 'in:pending,in_transit,delivered,cancelled']]);
        $orderReturn->update(['status' => $request->status]);

        return back()->with('message', 'تم تحديث حالة الإرجاع');
    }

    public function printWaybill(OrderReturn $orderReturn)
    {
        $orderReturn->load('originalOrder.sender', 'originalOrder.recipient', 'originalOrder.originBranch', 'originalOrder.destinationBranch');
        $siteSettings = \App\Models\AboutSite::first();
        return view('admin.orderReturns.waybill', compact('orderReturn', 'siteSettings'));
    }
}
