<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\CodSetting;
use App\Models\CustomerProfile;
use App\Models\Insurance;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\SalesDiscountCode;
use App\Models\User;
use App\Services\CentralSalesDiscountService;
use App\Services\SalesCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    protected $salesDiscountService;
    protected $salesCommissionService;

    public function __construct(CentralSalesDiscountService $salesDiscountService, SalesCommissionService $salesCommissionService)
    {
        $this->salesDiscountService = $salesDiscountService;
        $this->salesCommissionService = $salesCommissionService;
    }

    /** قائمة الفواتير */
    public function index(Request $request)
    {
        abort_if(!$this->canAccessInvoices(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = Invoice::with(['user.profile', 'orders', 'receipts', 'items'])
            ->latest();

        // فلترة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('invoice_type', $request->type);
        }
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('issue_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('issue_date', '<=', $request->to);
        }
        if ($request->filled('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%')
                  ->orWhere('client_name', 'like', '%' . $request->search . '%');
        }

        $invoices = $query->paginate(20)->appends($request->query());
        $customers = CustomerProfile::query()
            ->with('user')
            ->get()
            ->filter(fn ($p) => $p->user)
            ->mapWithKeys(fn ($p) => [(int) $p->user_id => ($p->company_name ?: $p->user->name)])
            ->toArray();
        $stats     = [
            'total'          => Invoice::count(),
            'draft'          => Invoice::where('status', 'draft')->count(),
            'issued'         => Invoice::where('status', 'issued')->count(),
            'partially_paid' => Invoice::where('status', 'partially_paid')->count(),
            'overdue'        => Invoice::where('status', 'overdue')
                                       ->orWhere(function ($q) {
                                           $q->whereDate('due_date', '<', now())
                                             ->whereNotIn('status', ['paid', 'cancelled']);
                                       })->count(),
            'total_amount'   => Invoice::whereNotIn('status', ['cancelled'])->sum('total_amount'),
            'total_remaining'=> Invoice::whereNotIn('status', ['paid', 'cancelled'])->sum('remaining_amount'),
        ];

        return view('admin.invoices.index', compact('invoices', 'customers', 'stats'));
    }

    /** تفاصيل فاتورة */
    public function show(Invoice $invoice)
    {
        abort_if(!$this->canAccessInvoices(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $invoice->load([
            'user.profile', 'branch',
            'items.order.sender', 'items.order.recipient',
            'receipts.cashBox',
        ]);

        $orderIds = $invoice->items->pluck('order_id')->filter()->values()->all();
        $linkedInsurances = Insurance::query()
            ->with('order')
            ->where(function ($query) use ($invoice, $orderIds) {
                $query->where('invoice_id', $invoice->id);

                if (!empty($orderIds)) {
                    $query->orWhereIn('order_id', $orderIds);
                }
            })
            ->latest('id')
            ->get();

        $billingSettings = BillingSetting::current();

        return view('admin.invoices.show', compact('invoice', 'billingSettings', 'linkedInsurances'));
    }

    /** طباعة / PDF */
    public function print(Invoice $invoice)
    {
        abort_if(!$this->canAccessInvoices(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $invoice->load([
            'user.profile', 'branch',
            'items.order.sender.governorate', 'items.order.sender.city',
            'items.order.recipient.governorate', 'items.order.recipient.city',
            'receipts',
        ]);

        $billingSettings = BillingSetting::current();

        return view('admin.invoices.print', compact('invoice', 'billingSettings'));
    }

    /** إشعار COD مستقل مرتبط بالفاتورة */
    public function printCodNotice(Invoice $invoice)
    {
        abort_if(!$this->canAccessInvoices(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $invoice->load([
            'user.profile',
            'items.order.sender',
            'items.order.recipient',
        ]);

        $codSettings = CodSetting::instance();

        $rows = $invoice->items
            ->filter(fn ($item) => (float) ($item->order?->cod_amount ?? 0) > 0)
            ->values()
            ->map(function ($item) use ($codSettings) {
                $order = $item->order;
                $codAmount = (float) ($order?->cod_amount ?? 0);
                $shippingAmount = (float) (
                    $order?->final_shopping_cost
                    ?? $order?->total_cost
                    ?? $order?->shipping_cost
                    ?? $item->total
                    ?? 0
                );
                $bankFee = (float) ($order?->bank_fee_amount ?? 0);
                $clearingAmount = (float) ($order?->clearing_amount ?? 0);
                $collectionFee = $codAmount > 0
                    ? (float) $codSettings->calcCollectionFee($codAmount)
                    : 0.0;
                $shipmentValue = (float) ($order?->stated_value ?? 0);
                $netAmount = round($codAmount - $shippingAmount - $bankFee - $clearingAmount - $collectionFee, 2);

                return [
                    'order_id' => $order?->id,
                    'waybill_number' => $order?->waybill_number,
                    'client_name' => $order?->user?->name,
                    'sender_name' => $order?->sender?->name,
                    'recipient_name' => $order?->recipient?->name,
                    'cod_amount' => $codAmount,
                    'shipping_amount' => $shippingAmount,
                    'bank_fee' => $bankFee,
                    'clearing_amount' => $clearingAmount,
                    'collection_fee' => $collectionFee,
                    'shipment_value' => $shipmentValue,
                    'net_amount' => $netAmount,
                ];
            });

        if ($rows->isEmpty()) {
            return back()->with('error', 'لا توجد شحنات COD ضمن هذه الفاتورة.');
        }

        $totals = [
            'cod_amount' => (float) $rows->sum('cod_amount'),
            'shipping_amount' => (float) $rows->sum('shipping_amount'),
            'bank_fee' => (float) $rows->sum('bank_fee'),
            'clearing_amount' => (float) $rows->sum('clearing_amount'),
            'collection_fee' => (float) $rows->sum('collection_fee'),
            'shipment_value' => (float) $rows->sum('shipment_value'),
            'net_amount' => (float) $rows->sum('net_amount'),
        ];

        return view('admin.invoices.print-cod-notice', compact('invoice', 'rows', 'totals'));
    }

    /** إنشاء فاتورة مجمّعة لشركة آجلة */
    public function createBatch(Request $request)
    {
        abort_if(!$this->canCreateInvoices(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $customers = CustomerProfile::query()
            ->where('billing_type', 'deferred')
            ->where('account_status', 'active')
            ->with('user')
            ->get();

        $billingSettings = BillingSetting::current();
        $discountCodes = SalesDiscountCode::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        return view('admin.invoices.create-batch', compact('customers', 'billingSettings', 'discountCodes'));
    }

    /** حفظ فاتورة مجمّعة */
    public function storeBatch(Request $request)
    {
        abort_if(!$this->canCreateInvoices(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'user_id'     => 'required|exists:users,id',
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'discount_code' => 'nullable|string|max:100',
            'apply_finance_early_discount' => 'nullable|boolean',
            'finance_discount_percent' => 'nullable|numeric|min:0|max:100',
            'due_date' => 'nullable|date|after_or_equal:period_to',
        ]);

        $customer = User::query()->with('profile')->findOrFail($request->user_id);
        $profile = $customer->profile;
        if (!$profile || $profile->billing_type !== 'deferred') {
            return back()->withErrors(['user_id' => 'الزبون المحدد ليس ضمن نظام الحساب الآجل.']);
        }

        // جلب الطلبات المحددة
        $orders = Order::with(['sender', 'recipient'])
            ->whereIn('id', $request->order_ids)
            ->where('user_id', $customer->id)
            ->where('order_type', 'deferred')
            ->whereNull('invoice_id')
            ->get();

        if ($orders->isEmpty()) {
            return back()->withErrors(['order_ids' => 'لا توجد طلبات مؤهلة للفوترة']);
        }

        // احتساب الخصم الخاص بالشركة
        $subtotal = 0;
        foreach ($orders as $order) {
            $cost = $profile->special_shipping_rate
                ? (float)$profile->special_shipping_rate
                : (float)($order->total_cost ?? 0);
            $subtotal += $cost;
        }

        $billingSettings = BillingSetting::current();
        $customerDiscountAmount = round($subtotal * (((float) $profile->discount_percent) / 100), 2);
        $afterCustomerDiscount = round($subtotal - $customerDiscountAmount, 2);

        $actorRole = $this->resolveActorRole();
        $codeResult = $this->salesDiscountService->apply(
            $request->input('discount_code'),
            $customer,
            $afterCustomerDiscount,
            $actorRole
        );

        if (isset($codeResult['error'])) {
            return back()->withErrors(['discount_code' => $codeResult['error']])->withInput();
        }

        $salesDiscountAmount = (float) ($codeResult['discount_amount'] ?? 0);
        $afterSalesDiscount = round($afterCustomerDiscount - $salesDiscountAmount, 2);

        $financeDiscountPercent = 0.0;
        if ($request->boolean('apply_finance_early_discount')) {
            $requestedFinanceDiscount = $request->filled('finance_discount_percent')
                ? (float) $request->input('finance_discount_percent')
                : (float) $billingSettings->finance_early_discount_percent;

            $financeDiscountPercent = min(100, max(0, $requestedFinanceDiscount));
        }

        $financeDiscountAmount = round($afterSalesDiscount * ($financeDiscountPercent / 100), 2);
        $totalDiscountAmount = round($customerDiscountAmount + $salesDiscountAmount + $financeDiscountAmount, 2);
        $totalAmount = round(max(0, $subtotal - $totalDiscountAmount), 2);
        $dueDate = $request->filled('due_date')
            ? now()->parse($request->due_date)
            : now()->addDays((int) ($profile->payment_cycle_days ?? 30));

        $discountCode = $codeResult['discount_code'] ?? null;
        $salesOwnerId = $profile->sales_rep_id ?: null;

        $invoice = Invoice::create([
            'invoice_type'    => 'deferred',
            'status'          => 'issued',
            'user_id'         => $customer->id,
            'sales_owner_id'  => $salesOwnerId,
            'client_name'     => $profile->company_name ?: $customer->name,
            'client_phone'    => $profile->contact_person_mobile ?: $customer->mobile,
            'client_address'  => $profile->address_line1,
            'subtotal'        => $subtotal,
            'discount_amount' => $totalDiscountAmount,
            'sales_discount_code_id' => $discountCode?->id,
            'sales_discount_amount' => $salesDiscountAmount,
            'finance_discount_amount' => $financeDiscountAmount,
            'total_amount'    => $totalAmount,
            'remaining_amount'=> $totalAmount,
            'paid_amount'     => 0,
            'issue_date'      => now()->toDateString(),
            'due_date'        => $dueDate->toDateString(),
            'billing_period'  => $request->period_from . ' / ' . $request->period_to,
            'period_from'     => $request->period_from,
            'period_to'       => $request->period_to,
            'notes'           => $request->notes,
            'bank_name_snapshot' => $billingSettings->bank_name,
            'bank_account_name_snapshot' => $billingSettings->bank_account_name,
            'iban_snapshot' => $billingSettings->iban,
            'account_number_snapshot' => $billingSettings->account_number,
            'swift_code_snapshot' => $billingSettings->swift_code,
            'bank_branch_snapshot' => $billingSettings->bank_branch,
            'payment_instructions_snapshot' => $billingSettings->payment_instructions,
        ]);

        // إنشاء بنود الفاتورة
        foreach ($orders as $order) {
            $unitPrice = $profile->special_shipping_rate
                ? (float)$profile->special_shipping_rate
                : (float)($order->total_cost ?? 0);

            $invoice->items()->create([
                'order_id'    => $order->id,
                'description' => 'شحنة رقم: ' . $order->waybill_number
                    . ' — ' . ($order->recipient?->name ?? '')
                    . ' / ' . ($order->recipient?->city?->title_ar ?? ''),
                'quantity'    => 1,
                'unit_price'  => $unitPrice,
                'total'       => $unitPrice,
            ]);

            $order->update(['invoice_id' => $invoice->id]);
        }

        // تحديث الرصيد المستخدم للزبون
        $profile->credit_used = (float) $profile->credit_used + (float) $totalAmount;
        $profile->deferred_balance = (float) $profile->deferred_balance + (float) $totalAmount;
        $profile->syncBillingTypeFromState();
        $profile->save();

        if ($discountCode) {
            $this->salesDiscountService->recordUsage(
                $discountCode,
                $customer,
                $afterCustomerDiscount,
                $salesDiscountAmount,
                $afterSalesDiscount,
                $invoice->id,
                $actorRole
            );
        }

        if ($salesOwnerId) {
            $salesUser = User::query()->find($salesOwnerId);
            $this->salesCommissionService->createOrUpdateInvoiceSalesCommission($invoice, $salesUser);
        }

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'تم إنشاء الفاتورة المجمّعة: ' . $invoice->invoice_number);
    }

    /** الحصول على طلبات شركة غير مُفاتَرة بعد (AJAX) */
    public function getCompanyOrders(Request $request)
    {
        abort_if(!$this->canCreateInvoices(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $customer = User::query()->with('profile')->findOrFail($request->user_id);
        $profile = $customer->profile;
        if (!$profile) {
            return response()->json(['orders' => [], 'subtotal' => 0]);
        }

        $query = Order::with(['sender', 'recipient'])
            ->where('user_id', $customer->id)
            ->where('order_type', 'deferred')
            ->whereNull('invoice_id');

        $from = $request->input('from', $request->input('period_from'));
        $to = $request->input('to', $request->input('period_to'));

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $orders = $query->get()->map(function ($o) use ($profile) {
            $cost = $profile->special_shipping_rate ?? $o->total_cost ?? 0;
            return [
                'id'             => $o->id,
                'waybill_number' => $o->waybill_number,
                'recipient_name' => $o->recipient?->name ?? '—',
                'recipient_phone'=> $o->recipient?->mobile ?? '',
                'city'           => $o->recipient?->city?->title_ar ?? '—',
                'date'           => $o->created_at->format('Y-m-d'),
                'shipping_amount'=> (float) $cost,
                'packages_count' => $o->packages_count ?? 1,
            ];
        });

        return response()->json([
            'orders'   => $orders,
            'subtotal' => $orders->sum('shipping_amount'),
        ]);
    }

    /** تغيير حالة الفاتورة */
    public function updateStatus(Request $request, Invoice $invoice)
    {
        abort_if(!$this->canEditInvoices(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'status' => 'required|in:draft,issued,overdue,cancelled',
        ]);

        $invoice->update(['status' => $request->status]);

        return back()->with('success', 'تم تحديث حالة الفاتورة');
    }

    private function canAccessInvoices(): bool
    {
        return $this->hasAnyPermission([
            'invoice_access',
            'receipt_access',
            'cash_box_access',
            'order_access',
        ]);
    }

    private function canCreateInvoices(): bool
    {
        return $this->hasAnyPermission([
            'invoice_create',
            'invoice_access',
            'cash_box_access',
            'receipt_access',
            'order_access',
        ]);
    }

    private function canEditInvoices(): bool
    {
        return $this->hasAnyPermission([
            'invoice_edit',
            'invoice_access',
            'cash_box_edit',
            'cash_box_access',
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

    private function resolveActorRole(): string
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (!$user) {
            return 'system';
        }

        $roles = $user->roles()->pluck('title')->map(fn ($r) => strtolower((string) $r))->toArray();

        if (in_array('finance', $roles, true) || in_array('finance_manager', $roles, true)) {
            return 'finance';
        }

        if (in_array('sales', $roles, true) || in_array('sales_manager', $roles, true)) {
            return 'sales';
        }

        return 'system';
    }
}
