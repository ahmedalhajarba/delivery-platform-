<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Address;
use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\City;
use App\Models\Country;
use App\Models\CustomerProfile;
use App\Models\Governorate;
use App\Models\Invoice;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\User;
use App\Models\UserSubscription;
use App\Exports\OrderTemplateExport;
use App\Imports\OrdersImport;
use App\Models\ImportLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $relations = [
            'sender', 'recipient', 'order_status',
            'originBranch', 'destinationBranch', 'assignedCourier',
            'partner',
        ];

        if (Schema::hasTable('shipment_trackings')) {
            $relations[] = 'latestTracking.trackingStatus';
        }

        $query = Order::with($relations);

        $hasWaybillNumber = Schema::hasColumn('orders', 'waybill_number');
        $hasOrderStatusId = Schema::hasColumn('orders', 'order_status_id');
        $hasPaymentStatus = Schema::hasColumn('orders', 'payment_status');
        $hasOrderType = Schema::hasColumn('orders', 'order_type');
        $hasOriginBranchId = Schema::hasColumn('orders', 'origin_branch_id');
        $hasCodAmount = Schema::hasColumn('orders', 'cod_amount');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                if (Schema::hasColumn('orders', 'waybill_number')) {
                    $q->where('waybill_number', 'like', "%$s%")
                      ->orWhere('id', $s);
                } else {
                    $q->where('id', $s);
                }

                $q
                  ->orWhereHas('sender',    fn($r) => $r->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"))
                  ->orWhereHas('recipient', fn($r) => $r->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"));
            });
        }
        if ($request->filled('status_id') && $hasOrderStatusId) {
            $query->where('order_status_id', $request->status_id);
        }
        if ($request->filled('payment_status') && $hasPaymentStatus) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('order_type') && $hasOrderType) {
            $query->where('order_type', $request->order_type);
        }
        if ($request->filled('branch_id') && $hasOriginBranchId) {
            $query->where('origin_branch_id', $request->branch_id);
        }
        if ($request->filled('date_from'))    $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))      $query->whereDate('created_at', '<=', $request->date_to);

        $orders = $query->latest()->paginate(25)->appends($request->query());

        // إحصاءات سريعة
        $stats = [
            'total'     => Order::count(),
            'today'     => Order::whereDate('created_at', today())->count(),
            'pending'   => $hasPaymentStatus ? Order::where('payment_status', 'pending')->count() : 0,
            'paid'      => $hasPaymentStatus ? Order::where('payment_status', 'paid')->count() : 0,
            'cod_total' => $hasCodAmount ? Order::sum('cod_amount') : 0,
        ];

        $statuses = \App\Models\OrderStatus::orderBy('id')->get();
        $branches = Branch::orderBy('title_ar')->pluck('title_ar', 'id');

        return view('admin.orders.index', compact('orders', 'stats', 'statuses', 'branches'));
    }

    public function create()
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries  = Country::pluck('name', 'id');
        $branches   = Branch::pluck('title_ar', 'id')->prepend('— اختر الفرع —', '');
        $couriers   = BranchEmployee::pluck('name', 'id')->prepend('— اختر المندوب —', '');
        $users      = User::pluck('name', 'id')->prepend('— اختر العميل —', '');

        // في بيئات البيانات المختلطة قد تكون بعض الجداول غير منشأة بعد.
        $subscriptions = collect();
        if (Schema::hasTable('user_subscriptions')) {
            $subscriptionsQuery = UserSubscription::query();

            if (Schema::hasTable('subscriptions_plans')) {
                $subscriptionsQuery->with('subscription');
            }

            if (Schema::hasColumn('user_subscriptions', 'status')) {
                $subscriptionsQuery->where('status', 0);
            }

            if (Schema::hasColumn('user_subscriptions', 'end_date')) {
                $subscriptionsQuery->where('end_date', '>=', now()->toDateString());
            }

            $subscriptions = $subscriptionsQuery->get();
        }

        $svcSettings = Schema::hasTable('extra_service_settings')
            ? \App\Models\ExtraServiceSetting::first()
            : null;

        $orderSetting = Schema::hasTable('order_settings')
            ? \App\Models\OrderSetting::first()
            : null;

        $deliverySpeeds = collect();
        if (Schema::hasTable('delivery_speed_settings')) {
            $deliverySpeedsQuery = \App\Models\DeliverySpeedSetting::query();

            if (Schema::hasColumn('delivery_speed_settings', 'enabled')) {
                $deliverySpeedsQuery->where('enabled', true);
            }

            if (Schema::hasColumn('delivery_speed_settings', 'sort_order')) {
                $deliverySpeedsQuery->orderBy('sort_order');
            }

            $deliverySpeeds = $deliverySpeedsQuery->get();
        }

        return view('admin.orders.create', compact(
            'countries', 'branches', 'couriers', 'users', 'subscriptions', 'svcSettings', 'orderSetting', 'deliverySpeeds'
        ));
    }

    public function store(StoreOrderRequest $request)
    {
        $data = $request->validated();
        $orderType = $data['order_type'] ?? 'single';

        $customerProfile = null;
        if (!empty($data['user_id'])) {
            $customerProfile = CustomerProfile::query()->where('user_id', (int) $data['user_id'])->first();
            if ($customerProfile) {
                if ($customerProfile->billing_type === 'subscription') {
                    $data['order_type'] = 'subscription';
                    $data['payment_status'] = 'paid';
                } elseif ($customerProfile->billing_type === 'deferred') {
                    $data['order_type'] = 'deferred';
                    $data['payment_status'] = 'deferred';
                } else {
                    $data['order_type'] = 'single';
                    $data['payment_status'] = $data['payment_status'] ?? 'pending';
                }
            }
        }

        $orderType = $data['order_type'] ?? $orderType;

        // التوجيه الآلي للفرع حسب المناطق المعرفة
        if (empty($data['origin_branch_id'])) {
            $routingService = app(\App\Services\BranchRoutingService::class);
            $resolvedBranch = $routingService->resolveBranchForOrderPayload($data);
            if ($resolvedBranch) {
                $data['origin_branch_id'] = $resolvedBranch->id;
            }
        }

        if (($data['order_type'] ?? null) === 'subscription') {
            if (empty($data['user_id'])) {
                return redirect()->back()
                    ->withErrors(['user_id' => 'يجب اختيار العميل لطلبات الاشتراك.'])
                    ->withInput();
            }

            $subscriptionService = app(\App\Services\SubscriptionService::class);
            $eligibility = $subscriptionService->canCreateSubscriptionOrder((int) $data['user_id']);
            if (empty($eligibility['allowed'])) {
                return redirect()->back()
                    ->withErrors(['order_type' => (string) $eligibility['message']])
                    ->withInput();
            }
        }

        // التحقق من الطاقة الاستيعابية للفرع (يومي)
        $originBranchId = $data['origin_branch_id'] ?? null;
        if ($originBranchId) {
            try {
                $branchCapacityService = app(\App\Services\BranchCapacityService::class);
                $branchCapacityService->validateOrderCapacity((int) $originBranchId, $orderType);
            } catch (\Exception $e) {
                return redirect()->back()
                    ->withErrors(['origin_branch_id' => $e->getMessage()])
                    ->withInput();
            }
        }

        // التحقق من حد أقصى البوالص الشهري للناقل
        $quotaService = app(\App\Services\CarrierQuotaService::class);
        $carrierCompanyId = $data['carrier_company_id'] ?? null;
        $carrierOrderType = $orderType === 'subscription' ? 'subscription' : 'individual';
        
        try {
            if ($carrierCompanyId) {
                $tempOrder = new Order(['carrier_company_id' => $carrierCompanyId, 'order_type' => $orderType]);
                $quotaService->validateOrderQuota(
                    $tempOrder,
                    $carrierCompanyId,
                    $carrierOrderType
                );
            }
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['carrier_company_id' => $e->getMessage()])
                ->withInput();
        }

        // إنشاء عنوان المرسل إذا أُدخل inline
        if ($request->filled('sender_new_name')) {
            $sender = Address::create([
                'name'            => $request->sender_new_name,
                'mobile'          => $request->sender_new_mobile,
                's_mobile'        => $request->sender_new_s_mobile,
                'street'          => $request->sender_new_street,
                'neighborhood_id' => $request->sender_new_neighborhood_id ?: null,
                'country_id'      => $request->sender_new_country_id,
                'governorate_id'  => $request->sender_new_governorate_id,
                'city_id'         => $request->sender_new_city_id,
                'user_id'         => $request->user_id,
                'type'            => 1, // sender
            ]);
            $data['sender_id'] = $sender->id;
        }

        // إنشاء عنوان المستلم إذا أُدخل inline
        if ($request->filled('recipient_new_name')) {
            $recipient = Address::create([
                'name'            => $request->recipient_new_name,
                'mobile'          => $request->recipient_new_mobile,
                's_mobile'        => $request->recipient_new_s_mobile,
                'street'          => $request->recipient_new_street,
                'neighborhood_id' => $request->recipient_new_neighborhood_id ?: null,
                'country_id'      => $request->recipient_new_country_id,
                'governorate_id'  => $request->recipient_new_governorate_id,
                'city_id'         => $request->recipient_new_city_id,
                'user_id'         => $request->user_id,
                'type'            => 0, // recipient
            ]);
            $data['recipient_id'] = $recipient->id;
        }

        // التحقق الهرمي لتفعيل المناطق قبل متابعة إنشاء الطلب.
        try {
            $senderAddress = !empty($data['sender_id']) ? Address::find($data['sender_id']) : null;
            $recipientAddress = !empty($data['recipient_id']) ? Address::find($data['recipient_id']) : null;

            app(\App\Services\LocationActivationService::class)->assertOrderCreationAllowed(
                $senderAddress?->city_id,
                $senderAddress?->neighborhood_id,
                $recipientAddress?->city_id,
                $recipientAddress?->neighborhood_id
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors(['sender_id' => $e->getMessage()])
                ->withInput();
        }

        // توليد رقم البوليصة
        $data['waybill_number'] = 'WB-' . strtoupper(Str::random(6)) . '-' . date('ymd');

        // احتساب الوزن الحجمي = (L × W × H) / 5000
        if (!empty($data['length']) && !empty($data['width']) && !empty($data['height'])) {
            $data['volumetric_weight'] = round(
                ($data['length'] * $data['width'] * $data['height']) / 5000, 2
            );
            $data['chargeable_weight'] = max(
                $data['actual_weight'] ?? 0,
                $data['volumetric_weight']
            );
        }

        // ── إعادة احتساب التكاليف من الإعدادات (لا نعتمد كليًا على قيم العميل) ──
        $settings    = \App\Models\ExtraServiceSetting::first();
        $orderSetting = \App\Models\OrderSetting::first();

        $deliveryType = $data['delivery_type'] ?? 'domestic';
        $shippingCost   = (float) ($data['shipping_cost'] ?? 0);
        $shippingType   = $data['shipping_type'] ?? 'standard';

        // للشحن الدولي: إعادة حساب السعر من جدول الأسعار الدولية
        $chargeableForIntl = (float) ($data['chargeable_weight'] ?? $data['actual_weight'] ?? 0);
        if ($deliveryType === 'international') {
            $destCountryId = $data['intl_destination_country_id'] ?? null;
            if ($destCountryId) {
                $intlPrice = \App\Models\IntlShippingRate::calcPrice((int)$destCountryId, $chargeableForIntl, $shippingType);
                if ($intlPrice !== null) {
                    $shippingCost = $intlPrice;
                    $data['shipping_cost']  = $shippingCost;
                    $data['intl_surcharge'] = $shippingCost;
                }
            }
        }

        // للتوصيل المحلي: التحقق من أن المرسل والمستلم في نفس المدينة
        if ($deliveryType === 'local_delivery') {
            $senderCityId    = null;
            $recipientCityId = null;
            if (!empty($data['sender_id'])) {
                $sAddr = \App\Models\Address::find($data['sender_id']);
                $senderCityId = $sAddr?->city_id;
            }
            if (!empty($data['recipient_id'])) {
                $rAddr = \App\Models\Address::find($data['recipient_id']);
                $recipientCityId = $rAddr?->city_id;
            }
            if ($senderCityId && $recipientCityId && $senderCityId !== $recipientCityId) {
                return redirect()->back()
                    ->withErrors(['delivery_type' => 'التوصيل المحلي يتطلب أن يكون المرسل والمستلم في نفس المدينة.'])
                    ->withInput();
            }
        }

        // رسوم سرعة التوصيل
        $deliverySpeed = $data['delivery_speed'] ?? 'standard';
        $speedSetting  = \App\Models\DeliverySpeedSetting::where('code', $deliverySpeed)->first();
        $speedSurcharge = 0;
        if ($speedSetting) {
            $speedSurcharge = $speedSetting->calcSurcharge($shippingCost);
        }
        $data['speed_surcharge'] = $speedSurcharge;

        // رسوم الشحن الخاص
        $surchargeMap = [
            'cold'   => (float) ($settings?->cold_shipping_surcharge   ?? 0),
            'frozen' => (float) ($settings?->frozen_shipping_surcharge ?? 0),
            'dry'    => (float) ($settings?->dry_shipping_surcharge    ?? 0),
        ];
        $shippingSurcharge = $surchargeMap[$shippingType] ?? 0;

        // تكلفة الوزن الزائد
        $chargeable     = (float) ($data['chargeable_weight'] ?? $data['actual_weight'] ?? 0);
        $allowedWeight  = (float) ($orderSetting?->allowed_weight  ?? 0);
        $overWeightRate = (float) ($orderSetting?->over_weight_rate ?? 0);
        $overExcess     = ($allowedWeight > 0 && $chargeable > $allowedWeight)
                            ? round($chargeable - $allowedWeight, 2) : 0;
        $overWeightCost = round($overExcess * $overWeightRate, 2);
        $data['over_weight_cost'] = $overWeightCost;

        // تكلفة التغليف
        $packagingCost = 0;
        if ($settings?->packaging_enabled && isset($data['packaging_cost']) && $data['packaging_cost'] > 0) {
            $packagingCost = (float) $settings->packaging_cost;
        }
        $data['packaging_cost'] = $packagingCost;

        // تكلفة التخزين
        $storageCost = 0;
        $storageDays = (int) ($data['storage_days'] ?? 0);
        if ($settings?->storage_enabled && $storageDays > 0) {
            $freeDays = (int) ($settings->storage_free_days ?? 0);
            $billable = max(0, $storageDays - $freeDays);
            $dailyRate = match ($shippingType) {
                'cold'   => (float) ($settings->storage_cold_daily   ?? 0),
                'frozen' => (float) ($settings->storage_frozen_daily ?? 0),
                default  => (float) ($settings->storage_normal_daily ?? 0),
            };
            $storageCost = round($billable * $dailyRate, 2);
        }
        $data['storage_cost'] = $storageCost;
        $data['storage_days'] = $storageDays;

        // التأمين
        $insuranceCost = (float) ($data['insurance_cost'] ?? 0);
        if ($settings?->insurance_enabled && $insuranceCost > 0) {
            $statedValue  = (float) ($data['stated_value'] ?? 0);
            $insuranceCost = round($statedValue * ((float)$settings->insurance_rate / 100), 2);
        }
        $data['insurance_cost'] = $insuranceCost;

        // الضريبة
        $vatAmount = 0;
        if ($settings?->vat_enabled) {
            $vatBase = 0;
            if ($settings->vat_on_shipping)  $vatBase += $shippingCost + $speedSurcharge + $shippingSurcharge + $overWeightCost;
            if ($settings->vat_on_insurance) $vatBase += $insuranceCost;
            if ($settings->vat_on_extras)    $vatBase += $packagingCost + $storageCost;
            $vatAmount = round($vatBase * ((float)$settings->vat_rate / 100), 2);
        }
        $data['vat_amount'] = $vatAmount;

        // الإجمالي النهائي
        $data['total_cost'] = round(
            $shippingCost + $speedSurcharge + $shippingSurcharge + $overWeightCost + $packagingCost + $storageCost + $insuranceCost + $vatAmount,
            2
        );

        // حالة الدفع الأولية
        $data['order_status_id'] = $data['order_status_id'] ?? 1;

        if (($data['order_type'] ?? 'single') === 'deferred' && $customerProfile) {
            if (($customerProfile->account_status ?? 'pending') !== 'active') {
                return redirect()->back()
                    ->withErrors(['user_id' => 'الحساب الآجل للزبون غير نشط.'])
                    ->withInput();
            }

            $projectedDeferred = (float) $customerProfile->deferred_balance + (float) ($data['total_cost'] ?? 0);
            if ((float) $customerProfile->credit_limit > 0 && $projectedDeferred > (float) $customerProfile->credit_limit) {
                return redirect()->back()
                    ->withErrors(['user_id' => 'تجاوز الطلب الحد الائتماني المسموح للزبون.'])
                    ->withInput();
            }

            if (!empty($customerProfile->shipment_limit) && ((int) $customerProfile->shipments_used + 1 > (int) $customerProfile->shipment_limit)) {
                return redirect()->back()
                    ->withErrors(['user_id' => 'تجاوز الطلب الحد الأقصى للشحنات الآجلة للزبون.'])
                    ->withInput();
            }
        }

        $order = Order::create($data);

        // الربط والخصم سيتمان عند حالة التتبع PICKED_UP

        // إنشاء فاتورة تلقائي عند طلب فردي أو آجل
        if (in_array($order->order_type, ['single', 'deferred'])) {
            $deferredDueDays = (int) ($customerProfile->payment_cycle_days ?? 30);
            $invoice = Invoice::create([
                'invoice_type'     => $order->order_type === 'deferred' ? 'deferred' : 'shipping',
                'status'           => $order->order_type === 'single' ? 'issued' : 'draft',
                'user_id'          => $order->user_id,
                'client_name'      => $order->sender?->name,
                'client_phone'     => $order->sender?->mobile,
                'subtotal'         => $order->total_cost - ($order->vat_amount ?? 0),
                'total_amount'     => $order->total_cost,
                'remaining_amount' => $order->total_cost,
                'issue_date'       => now()->toDateString(),
                'due_date'         => $order->order_type === 'deferred'
                                        ? now()->addDays($deferredDueDays)->toDateString()
                                        : null,
                'branch_id'        => $order->origin_branch_id,
            ]);

            // بنود الفاتورة المفصّلة
            $invoiceLines = [
                ['description' => 'سعر الشحن الأساسي — ' . $order->waybill_number, 'amount' => $order->shipping_cost ?? 0],
                ['description' => 'رسوم الوزن الزائد',    'amount' => $order->over_weight_cost ?? 0],
                ['description' => 'رسوم التغليف',          'amount' => $order->packaging_cost ?? 0],
                ['description' => 'رسوم التخزين (' . ($order->storage_days ?? 0) . ' يوم)', 'amount' => $order->storage_cost ?? 0],
                ['description' => 'التأمين',               'amount' => $order->insurance_cost ?? 0],
                ['description' => 'ضريبة القيمة المضافة', 'amount' => $order->vat_amount ?? 0],
            ];
            foreach ($invoiceLines as $line) {
                if ((float)$line['amount'] > 0) {
                    $invoice->items()->create([
                        'order_id'    => $order->id,
                        'description' => $line['description'],
                        'quantity'    => 1,
                        'unit_price'  => $line['amount'],
                        'total'       => $line['amount'],
                    ]);
                }
            }

            $order->update(['invoice_id' => $invoice->id]);

            if ($order->order_type === 'deferred' && $customerProfile) {
                $customerProfile->deferred_balance = (float) $customerProfile->deferred_balance + (float) ($order->total_cost ?? 0);
                $customerProfile->credit_used = (float) $customerProfile->deferred_balance;
                $customerProfile->shipments_used = (int) $customerProfile->shipments_used + 1;
                $customerProfile->syncBillingTypeFromState();
                $customerProfile->save();
            }
        }

        // تسجيل استهلاك البوليصة من الحد الأقصى الشهري للناقل
        if ($carrierCompanyId) {
            try {
                $quotaService->recordConsumption(
                    $order,
                    $carrierCompanyId,
                    $carrierOrderType
                );
            } catch (\Exception $e) {
                Log::warning('Failed to record quota consumption', [
                    'order_id' => $order->id,
                    'carrier_id' => $carrierCompanyId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // تسجيل استهلاك السعة اليومية للفرع
        if ($originBranchId) {
            try {
                $branchCapacityService = app(\App\Services\BranchCapacityService::class);
                $branchCapacityService->recordConsumption($order);
            } catch (\Exception $e) {
                Log::warning('Failed to record branch capacity consumption', [
                    'order_id' => $order->id,
                    'branch_id' => $originBranchId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // إنشاء مهمة HR تلقائية لمتابعة الطلب على الفرع المسؤول
        if ($originBranchId) {
            try {
                $routingService = app(\App\Services\BranchRoutingService::class);
                $employee = $routingService->pickBranchEmployeeForOperations((int) $originBranchId);

                \App\Models\HrTask::create([
                    'title' => 'متابعة طلب جديد #' . $order->id,
                    'description' => 'تم توجيه الطلب آلياً للفرع حسب منطقة المستلم. رقم البوليصة: ' . $order->waybill_number,
                    'module' => 'orders',
                    'task_type' => 'order_follow_up',
                    'priority' => 'normal',
                    'status' => 'open',
                    'branch_id' => $originBranchId,
                    'assigned_employee_id' => $employee?->id,
                    'created_by' => auth()->id(),
                    'related_type' => \App\Models\Order::class,
                    'related_id' => $order->id,
                    'due_at' => now()->addHours(4),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create HR task for order', [
                    'order_id' => $order->id,
                    'branch_id' => $originBranchId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── إشعار تلقائي للمستخدم عند إنشاء الطلب ──────────────────
        if ($order->user_id) {
            try {
                NotificationService::orderNotification(
                    $order->user_id,
                    'تم إنشاء طلب جديد',
                    'رقم البوليصة: ' . $order->waybill_number . ' — بتكلفة ' . number_format($order->total_cost, 2),
                    $order->id,
                    'success'
                );
            } catch (\Exception $e) {
                Log::warning('Notification failed for order ' . $order->id);
            }
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'تم إنشاء الطلب بنجاح. رقم البوليصة: ' . $order->waybill_number);
    }

    public function edit(Order $order)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches         = Branch::pluck('title_ar', 'id')->prepend('— اختر الفرع —', '');
        $couriers         = BranchEmployee::pluck('name', 'id')->prepend('— اختر المندوب —', '');
        $senders          = Address::where('type', 1)->pluck('name', 'id')->prepend('— اختر المرسل —', '');
        $recipients       = Address::where('type', 0)->pluck('name', 'id')->prepend('— اختر المستلم —', '');
        $trackingStatuses = \App\Models\TrackingStatus::orderBy('category')->orderBy('sort_order')->get();

        $order->load('sender', 'recipient', 'originBranch', 'destinationBranch', 'assignedCourier', 'order_status');

        return view('admin.orders.edit', compact(
            'senders', 'recipients', 'branches', 'couriers', 'order', 'trackingStatuses'
        ));
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        $validated = $request->validated();

        // استخرج حقول التتبع قبل التحديث
        $trackingNote     = $validated['tracking_note'] ?? null;
        $trackingStatusId = $validated['tracking_status_id'] ?? null;
        unset($validated['tracking_note'], $validated['tracking_status_id']);

        // سجّل التغييرات قبل الحفظ لبناء رسالة تتبع تلقائية
        $changedFields = [];
        $fieldLabels = [
            'sender_id'             => 'المرسل',
            'recipient_id'          => 'المستلم',
            'origin_branch_id'      => 'فرع الانطلاق',
            'destination_branch_id' => 'فرع الوجهة',
            'assigned_courier_id'   => 'المندوب',
            'shipment_type'         => 'نوع الشحنة',
            'package_type'          => 'نوع الطرد',
            'packages_count'        => 'عدد الطرود',
            'actual_weight'         => 'الوزن الفعلي',
            'chargeable_weight'     => 'الوزن المحتسب',
            'stated_value'          => 'القيمة المصرحة',
            'shipping_cost'         => 'تكلفة الشحن',
            'cod_amount'            => 'مبلغ COD',
            'payment_status'        => 'حالة الدفع',
            'order_type'            => 'نوع الطلب',
        ];
        foreach ($fieldLabels as $field => $label) {
            if (isset($validated[$field]) && (string)$order->$field !== (string)$validated[$field]) {
                $changedFields[] = $label;
            }
        }

        $order->update($validated);

        // تسجيل في سجل التتبع إذا وُجدت ملاحظة أو حالة أو تغييرات
        if ($trackingNote || $trackingStatusId || !empty($changedFields)) {
            $autoNote = !empty($changedFields)
                ? 'تم تعديل: ' . implode('، ', $changedFields)
                : null;

            $note = trim(implode(' — ', array_filter([$trackingNote, $autoNote])));

            $statusId = $trackingStatusId
                ?? $order->latestTracking?->tracking_status_id
                ?? null;

            if ($statusId || $note) {
                \App\Models\ShipmentTracking::create([
                    'order_id'           => $order->id,
                    'tracking_status_id' => $statusId,
                    'notes'              => $note ?: 'تم تعديل بيانات الطلب',
                    'branch_id'          => $order->origin_branch_id,
                    'updated_by'         => auth()->id(),
                    'updated_by_role'    => 'admin',
                    'event_time'         => now(),
                ]);

                if ($trackingStatusId) {
                    $order->update(['order_status_id' => $trackingStatusId]);
                }
            }
        }

        return redirect()->route('admin.orders.edit', $order->id)
            ->with('success', 'تم تحديث الطلب بنجاح.' . (!empty($changedFields) ? ' (تم تسجيل التعديل في سجل التتبع)' : ''));
    }

    public function show(Order $order)
    {
        abort_if(Gate::denies('order_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $order->load([
            'sender.governorate', 'sender.city',
            'recipient.governorate', 'recipient.city',
            'order_status',
            'originBranch', 'destinationBranch', 'assignedCourier',
            'shipmentTracking.trackingStatus', 'shipmentTracking.branch', 'shipmentTracking.courier',
            'invoice.receipts',
        ]);

        return view('admin.orders.show', compact('order'));
    }

    public function destroy(Order $order)
    {
        abort_if(Gate::denies('order_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $order->delete();

        return back();
    }

    public function massDestroy(MassDestroyOrderRequest $request)
    {
        Order::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * استنساخ طلب — يفتح نموذج الإنشاء مع تعبئة بيانات الطلب الأصلي مسبقاً
     */
    public function clone(Order $order)
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $order->load([
            'sender.country', 'sender.governorate', 'sender.city',
            'recipient.country', 'recipient.governorate', 'recipient.city',
            'originBranch', 'destinationBranch', 'assignedCourier',
        ]);

        $countries     = Country::pluck('name', 'id');
        $branches      = Branch::pluck('title_ar', 'id')->prepend('— اختر الفرع —', '');
        $couriers      = BranchEmployee::pluck('name', 'id')->prepend('— اختر المندوب —', '');
        $users         = User::pluck('name', 'id')->prepend('— اختر العميل —', '');
        $subscriptions = UserSubscription::with('subscription')
            ->where('status', 0)
            ->where('end_date', '>=', now()->toDateString())
            ->get();

        // سيُمرَّر كـ $cloneFrom لتعبئة الحقول مسبقاً
        $cloneFrom = $order;

        return view('admin.orders.create', compact(
            'countries', 'branches', 'couriers', 'users', 'subscriptions', 'cloneFrom'
        ));
    }

    // ===== AJAX: جلب المحافظات حسب الدولة =====
    public function getGovernorates(Request $request)
    {
        $governorates = Governorate::where('region_id', function ($q) use ($request) {
            $q->select('id')->from('regions')->where('country_id', $request->country_id)->limit(1);
        })->orWhereHas('region', fn($q) => $q->where('country_id', $request->country_id))
          ->get(['id', 'title_ar as text']);

        return response()->json($governorates);
    }

    // ===== AJAX: جلب المدن حسب المحافظة =====
    public function getCities(Request $request)
    {
        $cities = City::where('governorate_id', $request->governorate_id)
            ->where('is_active', true)
            ->get(['id', 'title_ar as text']);

        return response()->json($cities);
    }

    // ===== AJAX: جلب الأحياء/المناطق حسب المدينة =====
    public function getNeighborhoods(Request $request)
    {
        $neighborhoods = Neighborhood::where('city_id', $request->city_id)
            ->where('is_active', true)
            ->get(['id', 'title_ar as text']);

        return response()->json($neighborhoods);
    }

    // ===== AJAX: جلب عناوين المستخدم =====
    public function getUserAddresses(Request $request)
    {
        $addresses = Address::where('user_id', $request->user_id)
            ->with('governorate', 'city')
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'text'           => $a->name . ' — ' . ($a->city?->title_ar ?? '') . ' — ' . $a->mobile,
                'country_id'     => $a->country_id     ?? null,
                'region_id'      => $a->governorate?->region_id ?? null,
                'governorate_id' => $a->governorate_id ?? null,
                'city_id'        => $a->city_id        ?? null,
            ]);

        return response()->json($addresses);
    }

    // ===== سجل الاستيرادات =====
    public function importLogs()
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $logs = ImportLog::with('importer')->latest()->paginate(20);

        return view('admin.orders.import-logs', compact('logs'));
    }

    // ===== طباعة بوالص دفعة واحدة =====
    public function batchPrintWaybills(ImportLog $importLog)
    {
        abort_if(Gate::denies('order_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $orders = Order::whereIn('id', $importLog->order_ids ?? [])
            ->with([
                'sender.governorate', 'sender.city',
                'recipient.governorate', 'recipient.city',
                'originBranch', 'destinationBranch',
            ])->get();

        $orderSetting = \App\Models\OrderSetting::first();
        $siteSetting  = \App\Models\SiteSetting::first();
        $siteName     = $siteSetting?->title_ar ?? config('app.name');
        $siteLogo     = $siteSetting?->logo?->url ?? null;
        $sitePhone    = $siteSetting?->phone ?? '';

        return view('admin.orders.batch-waybills', compact('orders', 'orderSetting', 'siteName', 'siteLogo', 'sitePhone', 'importLog'));
    }

    // ===== طباعة فواتير دفعة واحدة =====
    public function batchPrintInvoices(ImportLog $importLog)
    {
        abort_if(Gate::denies('invoice_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $orders = Order::whereIn('id', $importLog->order_ids ?? [])
            ->with([
                'sender.governorate', 'sender.city',
                'recipient.governorate', 'recipient.city',
                'invoice.receipts', 'originBranch',
            ])->get();

        $siteSetting = \App\Models\SiteSetting::first();
        $siteName    = $siteSetting?->title_ar ?? config('app.name');
        $siteLogo    = $siteSetting?->logo?->url ?? null;
        $sitePhone   = $siteSetting?->phone ?? '';

        return view('admin.orders.batch-invoices', compact('orders', 'siteName', 'siteLogo', 'sitePhone', 'importLog'));
    }

    // ===== طباعة مجمعة من تحديد الجدول =====
    public function bulkPrintWaybills(Request $request)
    {
        abort_if(Gate::denies('order_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $ids = array_filter(explode(',', $request->query('ids', '')));
        abort_if(empty($ids), 400, 'لم يتم تحديد أي طلبات.');

        $orders = Order::whereIn('id', $ids)
            ->with([
                'sender.governorate', 'sender.city',
                'recipient.governorate', 'recipient.city',
                'originBranch', 'destinationBranch',
            ])->get();

        $siteSetting = \App\Models\SiteSetting::first();
        $siteName    = $siteSetting?->title_ar ?? config('app.name');
        $siteLogo    = $siteSetting?->logo?->url ?? null;
        $sitePhone   = $siteSetting?->phone ?? '';

        return view('admin.orders.batch-waybills', compact('orders', 'siteName', 'siteLogo', 'sitePhone'));
    }

    public function bulkPrintInvoices(Request $request)
    {
        abort_if(Gate::denies('invoice_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $ids = array_filter(explode(',', $request->query('ids', '')));
        abort_if(empty($ids), 400, 'لم يتم تحديد أي طلبات.');

        $orders = Order::whereIn('id', $ids)
            ->with([
                'sender.governorate', 'sender.city',
                'recipient.governorate', 'recipient.city',
                'invoice.receipts', 'originBranch',
            ])->get();

        $siteSetting = \App\Models\SiteSetting::first();
        $siteName    = $siteSetting?->title_ar ?? config('app.name');
        $siteLogo    = $siteSetting?->logo?->url ?? null;
        $sitePhone   = $siteSetting?->phone ?? '';

        return view('admin.orders.batch-invoices', compact('orders', 'siteName', 'siteLogo', 'sitePhone'));
    }

    // ===== استيراد الطلبات من Excel =====
    public function downloadTemplate()
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return Excel::download(new OrderTemplateExport, 'orders_template.xlsx');
    }

    public function importOrders(Request $request)
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,xls,csv|max:4096',
        ], [
            'import_file.required' => 'يرجى اختيار ملف للاستيراد.',
            'import_file.mimes'    => 'صيغة الملف يجب أن تكون xlsx أو xls أو csv.',
            'import_file.max'      => 'حجم الملف يجب ألا يتجاوز 4 ميغابايت.',
        ]);

        $branchId = auth()->user()->branch_id ?? 0;

        $import = new OrdersImport(auth()->id(), (int)$branchId);

        Excel::import($import, $request->file('import_file'));

        // اجمع أخطاء التحقق (SkipsFailures) مع الأخطاء اليدوية
        $validationErrors = collect($import->failures())->map(fn($f) => [
            'row'     => $f->row(),
            'message' => implode(' | ', $f->errors()),
        ])->toArray();

        $allErrors = array_merge($validationErrors, $import->errors);

        // احفظ سجل الاستيراد
        $log = ImportLog::create([
            'filename'      => $request->file('import_file')->getClientOriginalName(),
            'imported_by'   => auth()->id(),
            'total_rows'    => $import->importedCount + count($allErrors),
            'success_count' => $import->importedCount,
            'error_count'   => count($allErrors),
            'order_ids'     => $import->importedIds,
            'errors'        => $allErrors ?: null,
            'status'        => count($allErrors) === 0 ? 'done' : ($import->importedCount > 0 ? 'partial' : 'failed'),
        ]);

        return response()->json([
            'success'    => true,
            'imported'   => $import->importedCount,
            'errors'     => $allErrors,
            'log_id'     => $log->id,
            'message'    => "تم استيراد {$import->importedCount} طلب بنجاح." . (count($allErrors) ? ' بعض الصفوف تحتوي على أخطاء.' : ''),
        ]);
    }
}

