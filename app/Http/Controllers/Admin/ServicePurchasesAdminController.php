<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashBox;
use App\Models\ServicePurchase;
use App\Notifications\ServicePurchaseReceiptNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServicePurchasesAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request)
    {
        $query = ServicePurchase::with('user', 'quotation')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('purchase_number', 'like', "%{$q}%")
                    ->orWhere('service_name_ar', 'like', "%{$q}%")
                    ->orWhere('transfer_reference', 'like', "%{$q}%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$q}%"));
            });
        }

        $purchases = $query->paginate(20)->appends($request->query());

        $stats = [
            'pending'   => ServicePurchase::where('status', 'pending')->count(),
            'reviewing' => ServicePurchase::where('status', 'reviewing')->count(),
            'confirmed' => ServicePurchase::where('status', 'confirmed')->count(),
        ];

        return view('admin.service-purchases.index', compact('purchases', 'stats'));
    }

    public function show(ServicePurchase $servicePurchase)
    {
        $servicePurchase->load('user', 'subscriptionPlan', 'confirmedBy', 'quotation');
        return view('admin.service-purchases.show', compact('servicePurchase'));
    }

    /**
     * تأكيد الدفع وإرسال سند القبض للعميل
     */
    public function confirm(Request $request, ServicePurchase $servicePurchase)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
            'cash_box_id' => 'nullable|exists:cash_boxes,id',
        ]);

        $invoice = null;
        $receipt = null;

        DB::transaction(function () use ($request, $servicePurchase, &$invoice, &$receipt) {
            $servicePurchase->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
                'admin_notes'  => $request->admin_notes,
            ]);

            $invoice = app(\App\Services\CentralFinanceBillingService::class)
                ->createOrUpdateServicePurchaseInvoice($servicePurchase->fresh());

            $cashBoxId = $request->input('cash_box_id');
            if (!$cashBoxId) {
                $cashBoxId = CashBox::query()
                    ->where('is_active', true)
                    ->orderByRaw("CASE WHEN type = 'main' THEN 0 ELSE 1 END")
                    ->value('id');
            }

            $receipt = app(\App\Services\CentralReceiptService::class)
                ->issueReceiptForInvoice(
                    invoice: $invoice,
                    amount: (float) $servicePurchase->total_amount,
                    paymentMethod: (string) ($servicePurchase->payment_method ?: 'bank_transfer'),
                    cashBoxId: $cashBoxId ? (int) $cashBoxId : null,
                    meta: [
                        'source_type' => 'service_purchase',
                        'source_id' => (int) $servicePurchase->id,
                        'source_event' => 'confirmed',
                        'reference_number' => $servicePurchase->transfer_reference,
                        'bank_name' => $servicePurchase->bank_name,
                        'notes' => 'سند قبض شراء خدمة منصة: ' . $servicePurchase->purchase_number,
                        'affects_invoice_balance' => true,
                    ]
                );

            $servicePurchase->update([
                'invoice_id' => $invoice?->id,
                'receipt_id' => $receipt?->id,
                'cash_box_id' => $receipt?->cash_box_id,
            ]);
        });

        // إرسال إشعار سند القبض
        try {
            if ($servicePurchase->user) {
                $servicePurchase->user->notify(
                    new ServicePurchaseReceiptNotification($servicePurchase)
                );
            }
        } catch (\Throwable $e) {
            // لا نوقف العملية إن فشل الإشعار
        }

        return back()->with('success', 'تم تأكيد الدفع وإرسال سند القبض للعميل.');
    }

    /**
     * رفض الطلب
     */
    public function reject(Request $request, ServicePurchase $servicePurchase)
    {
        $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        $servicePurchase->update([
            'status'      => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        return back()->with('success', 'تم رفض الطلب وإشعار العميل.');
    }

    /**
     * عرض صورة إيصال الحوالة
     */
    public function receipt(ServicePurchase $servicePurchase)
    {
        abort_if(empty($servicePurchase->transfer_receipt_path), 404);

        $path = $servicePurchase->transfer_receipt_path;
        abort_if(!Storage::disk('public')->exists($path), 404);

        return response()->file(storage_path('app/public/' . $path));
    }
}
