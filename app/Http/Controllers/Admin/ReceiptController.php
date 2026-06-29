<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\Invoice;
use App\Models\CashBox;
use App\Models\BranchEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    /**
     * قائمة سندات القبض مع فلاتر وإحصائيات
     */
    public function index(Request $request)
    {
        abort_if(!$this->canAccessReceipts(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = Receipt::with(['invoice', 'cashBox', 'user'])
            ->latest('receipt_date');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('receipt_number', 'like', "%$s%")
                  ->orWhere('reference_number', 'like', "%$s%")
                  ->orWhere('bank_name', 'like', "%$s%")
                  ->orWhereHas('invoice', fn($q) => $q->where('invoice_number', 'like', "%$s%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('cash_box_id')) {
            $query->where('cash_box_id', $request->cash_box_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('receipt_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('receipt_date', '<=', $request->to);
        }

        $stats = [
            'total'           => Receipt::count(),
            'confirmed'       => Receipt::where('status', 'confirmed')->count(),
            'cancelled'       => Receipt::where('status', 'cancelled')->count(),
            'total_amount'    => Receipt::where('status', 'confirmed')->sum('amount'),
            'today_amount'    => Receipt::where('status', 'confirmed')->whereDate('receipt_date', today())->sum('amount'),
            'this_month'      => Receipt::where('status', 'confirmed')->whereMonth('receipt_date', now()->month)->sum('amount'),
        ];

        $receipts  = $query->paginate(20)->appends($request->query());
        $cashBoxes = CashBox::where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.receipts.index', compact('receipts', 'stats', 'cashBoxes'));
    }

    /**
     * نموذج إنشاء سند قبض جديد
     */
    public function create(Request $request)
    {
        abort_if(!$this->canCreateReceipts(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $invoice   = null;
        $invoices  = Invoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->with('user')
            ->orderByDesc('id')
            ->get();
        $cashBoxes = CashBox::where('is_active', true)->orderBy('name_ar')->get();
        $employees = BranchEmployee::orderBy('name')->get();

        if ($request->filled('invoice_id')) {
            $invoice = Invoice::with('user', 'company')->find($request->invoice_id);
        }

        return view('admin.receipts.create', compact('invoices', 'cashBoxes', 'employees', 'invoice'));
    }

    /**
     * حفظ سند القبض الجديد
     */
    public function store(Request $request)
    {
        abort_if(!$this->canCreateReceipts(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'invoice_id'      => 'required|exists:invoices,id',
            'cash_box_id'     => 'required|exists:cash_boxes,id',
            'amount'          => 'required|numeric|min:0.01',
            'payment_method'  => 'required|in:' . implode(',', array_keys(Receipt::PAYMENT_METHOD)),
            'receipt_date'    => 'required|date',
            'reference_number'=> 'nullable|string|max:100',
            'bank_name'       => 'nullable|string|max:150',
            'notes'           => 'nullable|string|max:1000',
            'received_by'     => 'nullable|exists:branch_employees,id',
        ]);

        $invoice = Invoice::findOrFail($data['invoice_id']);

        // التحقق من أن المبلغ لا يتجاوز المتبقي
        if ($data['amount'] > $invoice->remaining_amount) {
            return back()->withInput()->withErrors([
                'amount' => 'المبلغ المدخل (' . $data['amount'] . ' ﷼) يتجاوز المتبقي في الفاتورة (' . $invoice->remaining_amount . ' ﷼).'
            ]);
        }

        $data['user_id']     = $invoice->user_id;
        $data['status']      = 'confirmed';
        $data['source_type'] = 'manual_receipt';
        $data['source_id'] = $invoice->id;
        $data['source_event'] = 'manual';
        $data['affects_invoice_balance'] = true;

        DB::transaction(function () use ($data) {
            Receipt::create($data);
        });

        return redirect()->route('admin.receipts.index')
            ->with('success', 'تم إنشاء سند القبض بنجاح وتحديث الفاتورة والصندوق.');
    }

    /**
     * عرض تفاصيل سند القبض
     */
    public function show(Receipt $receipt)
    {
        abort_if(!$this->canShowReceipts(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $receipt->load([
            'invoice.user',
            'invoice.company',
            'invoice.items',
            'cashBox',
            'receivedByEmployee',
            'user',
        ]);

        return view('admin.receipts.show', compact('receipt'));
    }

    /**
     * طباعة سند القبض (PDF)
     */
    public function print(Receipt $receipt)
    {
        abort_if(!$this->canShowReceipts(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $receipt->load([
            'invoice.user',
            'invoice.company',
            'invoice.items',
            'cashBox',
            'receivedByEmployee',
            'user',
        ]);

        return view('admin.receipts.print', compact('receipt'));
    }

    /**
     * إلغاء سند القبض
     */
    public function cancel(Request $request, Receipt $receipt)
    {
        abort_if(!$this->canEditReceipts(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($receipt->status === 'cancelled') {
            return back()->with('error', 'السند ملغي بالفعل.');
        }

        $request->validate([
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($receipt, $request) {
            $receipt->notes = ($receipt->notes ? $receipt->notes . "\n" : '') . 'سبب الإلغاء: ' . ($request->cancel_reason ?? 'غير محدد');
            $receipt->status = 'cancelled';
            $receipt->save(); // triggers updating boot hook which reverses cashbox & invoice
        });

        return back()->with('success', 'تم إلغاء سند القبض وعكس التأثير على الفاتورة والصندوق.');
    }

    /**
     * AJAX: جلب بيانات الفاتورة
     */
    public function getInvoiceData(Request $request)
    {
        abort_if(!$this->canCreateReceipts(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $invoice = Invoice::with('user', 'company')
            ->findOrFail($request->invoice_id);

        return response()->json([
            'invoice_number'   => $invoice->invoice_number,
            'client_name'      => $invoice->company?->name_ar ?? $invoice->client_name ?? $invoice->user?->name,
            'total_amount'     => $invoice->total_amount,
            'paid_amount'      => $invoice->paid_amount,
            'remaining_amount' => $invoice->remaining_amount,
            'due_date'         => $invoice->due_date?->format('d/m/Y'),
            'status'           => $invoice->status,
        ]);
    }

    private function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (Gate::allows($permission)) {
                return true;
            }
        }

        return false;
    }

    private function canAccessReceipts(): bool
    {
        return $this->hasAnyPermission([
            'receipt_access',
            'invoice_access',
            'cash_box_access',
            'order_access',
        ]);
    }

    private function canCreateReceipts(): bool
    {
        return $this->hasAnyPermission([
            'receipt_create',
            'receipt_access',
            'invoice_create',
            'invoice_edit',
            'invoice_access',
            'cash_box_edit',
            'cash_box_access',
            'order_access',
        ]);
    }

    private function canShowReceipts(): bool
    {
        return $this->hasAnyPermission([
            'receipt_show',
            'receipt_access',
            'invoice_access',
            'cash_box_show',
            'cash_box_access',
            'order_access',
        ]);
    }

    private function canEditReceipts(): bool
    {
        return $this->hasAnyPermission([
            'receipt_edit',
            'receipt_create',
            'receipt_access',
            'cash_box_edit',
            'cash_box_access',
            'invoice_edit',
            'invoice_access',
            'order_access',
        ]);
    }
}
