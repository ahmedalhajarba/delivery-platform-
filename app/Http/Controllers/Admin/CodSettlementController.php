<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodSettlement;
use App\Models\CodSettlementOrder;
use App\Models\CodSetting;
use App\Models\Order;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CodSettlementController extends Controller
{
    // ────────────────────────────────────────────────
    // قائمة دفعات المقاصة
    // ────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = CodSettlement::with('user')
            ->when($request->user_id,  fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->status,   fn($q) => $q->where('status',  $request->status))
            ->when($request->date_from, fn($q) => $q->whereDate('settlement_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('settlement_date', '<=', $request->date_to))
            ->orderByDesc('id');

        $settlements = $query->paginate(20)->withQueryString();

        // إحصاءات سريعة
        $stats = [
            'pending_count'  => CodSettlement::where('status', 'pending')->count(),
            'pending_amount' => CodSettlement::where('status', 'pending')->sum('net_amount'),
            'paid_amount'    => CodSettlement::where('status', 'paid')->sum('net_amount'),
            'total_count'    => CodSettlement::count(),
            // طلبات COD مستحقة لم تُسوَّ بعد
            'unsettled_orders' => Order::whereNotNull('cod_amount')
                ->where('cod_amount', '>', 0)
                ->where(fn($q) => $q->whereNull('cod_status')->orWhere('cod_status', 'pending'))
                ->count(),
        ];

        $clients = User::whereHas('orders', fn($q) => $q->whereNotNull('cod_amount')->where('cod_amount', '>', 0))
            ->orderBy('name')->get(['id','name']);

        return view('admin.cod-settlements.index', compact('settlements', 'stats', 'clients'));
    }

    // ────────────────────────────────────────────────
    // استعراض دفعة واحدة
    // ────────────────────────────────────────────────
    public function show(CodSettlement $codSettlement)
    {
        $codSettlement->load(['user', 'orders.order.sender', 'createdBy', 'paidBy']);
        return view('admin.cod-settlements.show', compact('codSettlement'));
    }

    // ────────────────────────────────────────────────
    // نموذج إنشاء دفعة مقاصة جديدة
    // ────────────────────────────────────────────────
    public function create(Request $request)
    {
        $settings = CodSetting::instance();
        $clients  = User::whereHas('orders', fn($q) =>
            $q->whereNotNull('cod_amount')
              ->where('cod_amount', '>', 0)
              ->where(fn($q2) => $q2->whereNull('cod_status')->orWhere('cod_status', 'pending'))
        )->orderBy('name')->get(['id','name']);

        // إذا جاء user_id في الطلب: أظهر طلباته القابلة للمقاصة
        $pendingOrders = collect();
        $selectedUser  = null;
        if ($request->user_id) {
            $selectedUser  = User::findOrFail($request->user_id);
            $pendingOrders = Order::where('user_id', $request->user_id)
                ->whereNotNull('cod_amount')
                ->where('cod_amount', '>', 0)
                ->where(fn($q) => $q->whereNull('cod_status')->orWhere('cod_status', 'pending'))
                ->with('sender')
                ->orderByDesc('id')
                ->get();
        }

        return view('admin.cod-settlements.create', compact('settings', 'clients', 'pendingOrders', 'selectedUser'));
    }

    // ────────────────────────────────────────────────
    // حفظ دفعة مقاصة جديدة
    // ────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'user_id'          => 'required|exists:users,id',
            'order_ids'        => 'required|array|min:1',
            'order_ids.*'      => 'exists:orders,id',
            'other_deductions' => 'nullable|numeric|min:0',
            'bank_name'        => 'nullable|string|max:100',
            'iban'             => 'nullable|string|max:34',
            'notes'            => 'nullable|string|max:1000',
            'settlement_date'  => 'required|date',
        ]);

        $settings = CodSetting::instance();

        DB::transaction(function () use ($request, $settings) {
            // جلب الطلبات المختارة
            $orders = Order::whereIn('id', $request->order_ids)
                ->where('user_id', $request->user_id)
                ->where(fn($q) => $q->whereNull('cod_status')->orWhere('cod_status', 'pending'))
                ->get();

            if ($orders->isEmpty()) {
                abort(422, 'لا توجد طلبات صالحة للمقاصة');
            }

            $grossCod      = $orders->sum('cod_amount');
            $collectionFee = 0;
            $orderLines    = [];

            foreach ($orders as $order) {
                $fee  = $settings->calcCollectionFee((float) $order->cod_amount);
                $net  = round($order->cod_amount - $fee, 2);
                $collectionFee += $fee;
                $orderLines[]   = [
                    'order_id'       => $order->id,
                    'cod_amount'     => $order->cod_amount,
                    'collection_fee' => $fee,
                    'net_amount'     => $net,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            // ── حساب المقاصة مع الاشتراك ──
            /** @var SubscriptionService $subscriptionService */
            $subscriptionService = app(SubscriptionService::class);
            $breakdown = $subscriptionService->calculateSettlementBreakdown(
                userId:           $request->user_id,
                totalCodAmount:   $grossCod
            );

            $grossAfterFees  = $grossCod - $collectionFee;
            $bankFee         = $breakdown['cod_prepaid_by_subscription']
                ? 0
                : $settings->calcBankFee($grossAfterFees);

            $otherDeductions = (float) ($request->other_deductions ?? 0);

            // الرسوم الإضافية من الاشتراك (كراتين، تغليف، إلخ)
            $extraChargesDeducted = $breakdown['extra_charges_deducted'];

            $netAmount = round(
                $grossCod
                - $collectionFee
                - $bankFee
                - $otherDeductions
                - $extraChargesDeducted
                - $breakdown['cod_fee_deducted']
                - $breakdown['bank_fee_deducted'],
                2
            );

            // جلب الاشتراك الفعّال
            $activeSub = $subscriptionService->getActiveSubscription($request->user_id)
                      ?? $subscriptionService->getLatestSubscription($request->user_id);

            $settlement = CodSettlement::create([
                'user_id'                     => $request->user_id,
                'settlement_number'           => CodSettlement::generateNumber(),
                'gross_cod_amount'            => $grossCod,
                'collection_fee'              => round($collectionFee, 2),
                'bank_fee'                    => $bankFee,
                'other_deductions'            => $otherDeductions,
                'net_amount'                  => max(0, $netAmount),
                'status'                      => 'pending',
                'settlement_date'             => $request->settlement_date,
                'bank_name'                   => $request->bank_name,
                'iban'                        => $request->iban,
                'notes'                       => $request->notes,
                'created_by'                  => auth()->id(),
                // حقول الاشتراك الجديدة
                'user_subscription_id'        => $activeSub?->id,
                'cod_fee_deducted'            => $breakdown['cod_fee_deducted'],
                'bank_fee_deducted'           => $breakdown['bank_fee_deducted'],
                'extra_charges_deducted'      => $extraChargesDeducted,
                'cod_prepaid_by_subscription' => $breakdown['cod_prepaid_by_subscription'],
                'bank_fees_prepaid'           => $breakdown['bank_fees_prepaid'],
                'net_amount_transferred'      => max(0, $netAmount),
            ]);

            // حفظ تفاصيل الطلبات
            foreach ($orderLines as &$line) {
                $line['cod_settlement_id'] = $settlement->id;
            }
            CodSettlementOrder::insert($orderLines);

            // تحديث حالة الطلبات
            Order::whereIn('id', $orders->pluck('id'))
                ->update([
                    'cod_status'        => 'pending',
                    'cod_settlement_id' => $settlement->id,
                ]);

            // تسوية الرسوم الإضافية المعلّقة
            if ($extraChargesDeducted > 0) {
                $subscriptionService->settleExtraChargesFromCod($request->user_id);
            }
        });

        return redirect()->route('admin.cod-settlements.index')
            ->with('message', 'تم إنشاء دفعة المقاصة بنجاح');
    }

    // ────────────────────────────────────────────────
    // تنفيذ الصرف (تغيير الحالة إلى paid)
    // ────────────────────────────────────────────────
    public function markPaid(Request $request, CodSettlement $codSettlement)
    {
        $request->validate([
            'transfer_reference' => 'required|string|max:100',
            'paid_date'          => 'required|date',
        ]);

        if ($codSettlement->isPaid()) {
            return back()->with('error', 'هذه الدفعة تم صرفها مسبقاً');
        }

        DB::transaction(function () use ($request, $codSettlement) {
            $codSettlement->update([
                'status'             => 'paid',
                'paid_date'          => $request->paid_date,
                'transfer_reference' => $request->transfer_reference,
                'paid_by'            => auth()->id(),
            ]);

            // تحديث حالة الطلبات إلى "settled"
            $orderIds = $codSettlement->orders()->pluck('order_id');
            Order::whereIn('id', $orderIds)->update(['cod_status' => 'settled']);
        });

        return back()->with('message', 'تم تسجيل صرف الدفعة بنجاح');
    }

    // ────────────────────────────────────────────────
    // إلغاء دفعة مقاصة
    // ────────────────────────────────────────────────
    public function cancel(CodSettlement $codSettlement)
    {
        if ($codSettlement->isPaid()) {
            return back()->with('error', 'لا يمكن إلغاء دفعة مدفوعة');
        }

        DB::transaction(function () use ($codSettlement) {
            $orderIds = $codSettlement->orders()->pluck('order_id');
            // إعادة الطلبات للحالة السابقة
            Order::whereIn('id', $orderIds)->update([
                'cod_status'        => 'pending',
                'cod_settlement_id' => null,
            ]);
            $codSettlement->update(['status' => 'cancelled']);
        });

        return back()->with('message', 'تم إلغاء دفعة المقاصة');
    }

    // ────────────────────────────────────────────────
    // الطلبات المعلقة (غير المسوَّاة) لعميل معين – AJAX
    // ────────────────────────────────────────────────
    public function pendingOrders(Request $request)
    {
        $userId = $request->user_id;
        $orders = Order::where('user_id', $userId)
            ->whereNotNull('cod_amount')
            ->where('cod_amount', '>', 0)
            ->where(fn($q) => $q->whereNull('cod_status')->orWhere('cod_status', 'pending'))
            ->with('sender')
            ->orderByDesc('id')
            ->get(['id','waybill_number','cod_amount','cod_status','created_at','sender_id']);

        $settings = CodSetting::instance();
        $data = $orders->map(fn($o) => [
            'id'             => $o->id,
            'waybill'        => $o->waybill_number,
            'sender'         => $o->sender?->name,
            'cod_amount'     => $o->cod_amount,
            'collection_fee' => $settings->calcCollectionFee((float) $o->cod_amount),
            'net'            => round($o->cod_amount - $settings->calcCollectionFee((float) $o->cod_amount), 2),
            'date'           => $o->created_at?->format('Y-m-d'),
        ]);

        return response()->json($data);
    }

    // ────────────────────────────────────────────────
    // صفحة الإعدادات
    // ────────────────────────────────────────────────
    public function settings()
    {
        $settings = CodSetting::instance();
        return view('admin.cod-settlements.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'collection_fee_rate'  => 'required|numeric|min:0|max:100',
            'collection_fee_fixed' => 'required|numeric|min:0',
            'bank_fee_rate'        => 'required|numeric|min:0|max:100',
            'bank_fee_fixed'       => 'required|numeric|min:0',
            'payout_days'          => 'required|array|min:1',
            'payout_days.*'        => 'integer|between:1,7',
            'min_payout_amount'    => 'required|integer|min:0',
        ]);

        CodSetting::instance()->update([
            'collection_fee_rate'  => $request->collection_fee_rate,
            'collection_fee_fixed' => $request->collection_fee_fixed,
            'bank_fee_rate'        => $request->bank_fee_rate,
            'bank_fee_fixed'       => $request->bank_fee_fixed,
            'payout_days'          => implode(',', $request->payout_days),
            'min_payout_amount'    => $request->min_payout_amount,
        ]);

        return back()->with('message', 'تم حفظ إعدادات COD بنجاح');
    }

    // ────────────────────────────────────────────────
    // تقرير مستحقات العملاء (غير مسوَّاة)
    // ────────────────────────────────────────────────
    public function pendingReport()
    {
        $settings = CodSetting::instance();
        $clients  = Order::whereNotNull('cod_amount')
            ->where('cod_amount', '>', 0)
            ->where(fn($q) => $q->whereNull('cod_status')->orWhere('cod_status', 'pending'))
            ->select('user_id', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(cod_amount) as gross_cod'))
            ->groupBy('user_id')
            ->with('user:id,name,email')
            ->get()
            ->map(function ($row) use ($settings) {
                $cFee    = round($row->gross_cod * $settings->collection_fee_rate / 100
                              + $settings->collection_fee_fixed * $row->orders_count, 2);
                $bFee    = $settings->calcBankFee($row->gross_cod - $cFee);
                return [
                    'user'          => $row->user,
                    'orders_count'  => $row->orders_count,
                    'gross_cod'     => $row->gross_cod,
                    'collection_fee'=> $cFee,
                    'bank_fee'      => $bFee,
                    'net'           => round($row->gross_cod - $cFee - $bFee, 2),
                ];
            })
            ->sortByDesc('gross_cod');

        return view('admin.cod-settlements.pending-report', compact('clients', 'settings'));
    }
}
