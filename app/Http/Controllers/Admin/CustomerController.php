<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CustomerProfile;
use App\Models\CustomerDocument;
use App\Models\CustomerActivityLog;
use App\Models\CustomerNote;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Contract;
use App\Models\Quotation;
use App\Models\AccountActivationRequest;
use App\Models\CodSettlement;
use App\Models\UserSubscription;
use App\Models\WalletHistory;
use App\Models\City;
use App\Models\Role;
use App\Services\Validation\ContactValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    // ─── INDEX ──────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = User::with(['profile', 'roles'])
            ->where(function ($q) {
                $q->whereHas('roles', fn($roleQuery) => $roleQuery->whereIn(DB::raw('LOWER(title)'), ['customer', 'coustomer']))
                    ->orWhereIn(DB::raw('LOWER(user_type)'), ['customer', 'coustomer']);
            })
            ->withCount(['senderOrders as orders_count'])
            ->latest();

        if ($s = $request->search) {
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")
                ->orWhere('mobile', 'like', "%$s%")
                ->orWhereHas('profile', fn($p) => $p->where('customer_code', 'like', "%$s%")
                    ->orWhere('company_name', 'like', "%$s%")));
        }

        if ($status = $request->status) {
            $query->whereHas('profile', fn($p) => $p->where('account_status', $status));
        }
        if ($type = $request->customer_type) {
            $query->whereHas('profile', fn($p) => $p->where('customer_type', $type));
        }
        if ($billingType = $request->billing_type) {
            $query->whereHas('profile', fn($p) => $p->where('billing_type', $billingType));
        }
        if ($priority = $request->priority) {
            $query->whereHas('profile', fn($p) => $p->where('priority', $priority));
        }

        $customers = $query->paginate(25)->appends($request->query());

        return view('admin.customers.index', [
            'customers' => $customers,
            'statuses'  => CustomerProfile::ACCOUNT_STATUS,
            'types'     => CustomerProfile::CUSTOMER_TYPES,
            'billingTypes' => CustomerProfile::BILLING_TYPES,
            'priorities' => CustomerProfile::PRIORITY,
        ]);
    }

    public function create()
    {
        $salesUsers = User::whereHas('roles', fn($q) => $q->whereIn('title', ['sales', 'sales_manager']))
            ->orderBy('name')
            ->get(['id', 'name']);
        $cities = City::query()
            ->select(['id', 'title_ar', 'title_en'])
            ->orderBy('title_ar')
            ->orderBy('title_en')
            ->get()
            ->mapWithKeys(function (City $city) {
                $label = trim((string) ($city->title_ar ?: $city->title_en ?: ('مدينة #' . $city->id)));
                return [$city->id => $label];
            });

        return view('admin.customers.create', compact('salesUsers', 'cities'));
    }

    public function store(Request $request)
    {
        $this->normalizeContactInputs($request, ['mobile', 'contact_person_mobile'], ['email']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => ['nullable', ContactValidation::internationalMobileRegexRule(), Rule::unique('users', 'mobile')],
            'mobile_country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')],
            'company_name' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'customer_type' => 'nullable|in:individual,company,enterprise',
            'billing_type' => 'nullable|in:direct,subscription,deferred',
            'priority' => 'nullable|in:normal,vip,premium',
            'city_id' => 'nullable|exists:cities,id',
            'address_line1' => 'nullable|string|max:500',
            'sales_rep_id' => 'nullable|exists:users,id',
            'contact_person' => 'nullable|string|max:255',
            'contact_person_mobile' => ['nullable', ContactValidation::internationalMobileRegexRule()],
            'contact_person_mobile_country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'website' => ['nullable', 'max:255', 'regex:/^https?:\/\/.+/i'],
            'bank_name' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:50',
            'bank_account_holder' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'payment_cycle_days' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'password' => 'nullable|string|min:8|max:100',
        ]);

        DB::transaction(function () use ($validated) {
            $customer = User::create([
                'name' => $validated['name'],
                'mobile' => $validated['mobile'] ?? null,
                'email' => $validated['email'] ?? null,
                'password' => $validated['password'] ?? Str::random(16),
                'user_type' => 'customer',
                'status' => 0,
                'account_status' => 'pending',
                'company_name' => $validated['company_name'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'sales_rep_id' => $validated['sales_rep_id'] ?? null,
            ]);

            $customerRole = Role::whereIn(DB::raw('LOWER(title)'), ['customer', 'coustomer'])->first();
            if ($customerRole) {
                $customer->roles()->syncWithoutDetaching([$customerRole->id]);
            }

            $profile = $customer->profile()->create([
                'customer_code' => CustomerProfile::generateCode(),
                'company_name' => $validated['company_name'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'customer_type' => $validated['customer_type'] ?? 'company',
                'billing_type' => $validated['billing_type'] ?? 'direct',
                'priority' => $validated['priority'] ?? 'normal',
                'city_id' => $validated['city_id'] ?? null,
                'address_line1' => $validated['address_line1'] ?? null,
                'sales_rep_id' => $validated['sales_rep_id'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'contact_person_mobile' => $validated['contact_person_mobile'] ?? null,
                'website' => $validated['website'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'iban' => $validated['iban'] ?? null,
                'bank_account_holder' => $validated['bank_account_holder'] ?? null,
                'credit_limit' => $validated['credit_limit'] ?? 0,
                'discount_percent' => $validated['discount_percent'] ?? 0,
                'payment_cycle_days' => $validated['payment_cycle_days'] ?? 30,
                'account_status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            $profile->syncBillingTypeFromState();
            $profile->save();

            CustomerActivityLog::log($customer->id, 'customer_created_from_admin', [], 'تمت إضافة العميل من لوحة إدارة الزبائن');
        });

        return redirect()->route('admin.customers.index')->with('success', 'تم إضافة العميل بنجاح (الحساب غير مفعل افتراضياً).');
    }

    // ─── SHOW (full profile) ─────────────────────────────────────────────────

    public function show(User $customer)
    {
        $profile = $customer->profile()->firstOrCreate(
            ['user_id' => $customer->id],
            ['customer_code' => CustomerProfile::generateCode(), 'account_status' => 'pending']
        );

        $profile->syncStats();

        $recentOrders     = Order::where('user_id', $customer->id)->with(['order_status'])->latest()->limit(10)->get();
        $contracts        = Contract::where('user_id', $customer->id)->latest()->get();
        $quotations       = Quotation::where('user_id', $customer->id)->latest()->limit(5)->get();
        $activationReqs   = AccountActivationRequest::where('user_id', $customer->id)->latest()->get();
        $codSettlements   = CodSettlement::where('user_id', $customer->id)->latest()->limit(5)->get();
        $subscriptions    = UserSubscription::where('user_id', $customer->id)->with('subscription')->latest()->get();
        $walletHistory    = WalletHistory::where('user_id', $customer->id)->latest()->limit(20)->get();
        $documents        = CustomerDocument::where('user_id', $customer->id)->latest()->get();
        $notes            = CustomerNote::where('user_id', $customer->id)
                                ->with('author')->orderByDesc('pinned')->latest()->get();
        $activityLog      = CustomerActivityLog::where('user_id', $customer->id)
                                ->with('performedBy')->latest()->limit(30)->get();

        // Order stats by status
        $fallbackStatusLabels = [
            1 => 'تم التقاط الطلب',
            2 => 'في طريق العبور',
            3 => 'فشل التقاط الطلب',
            4 => 'تم التوصيل',
            5 => 'ملغي',
        ];

        $statusLabels = OrderStatus::query()->pluck('name_ar', 'id')->toArray();
        $orderStats = Order::where('user_id', $customer->id)
            ->selectRaw('order_status_id, count(*) as cnt')
            ->groupBy('order_status_id')
            ->pluck('cnt', 'order_status_id')
            ->mapWithKeys(function ($cnt, $statusId) use ($statusLabels, $fallbackStatusLabels) {
                $label = $statusLabels[$statusId] ?? $fallbackStatusLabels[$statusId] ?? ('حالة #' . $statusId);
                return [$label => $cnt];
            });

        return view('admin.customers.show', compact(
            'customer', 'profile', 'recentOrders', 'contracts', 'quotations',
            'activationReqs', 'codSettlements', 'subscriptions', 'walletHistory',
            'documents', 'notes', 'activityLog', 'orderStats'
        ));
    }

    // ─── EDIT / UPDATE PROFILE ───────────────────────────────────────────────

    public function edit(User $customer)
    {
        $profile = $customer->profile ?? new CustomerProfile(['user_id' => $customer->id]);
        $cities  = City::query()
            ->select(['id', 'title_ar', 'title_en'])
            ->orderBy('title_ar')
            ->orderBy('title_en')
            ->get()
            ->mapWithKeys(function (City $city) {
                $label = trim((string) ($city->title_ar ?: $city->title_en ?: ('مدينة #' . $city->id)));
                return [$city->id => $label];
            });

        $dialCodeList = collect(config('country_dial_codes.list', []))
            ->pluck('dial_code')
            ->unique()
            ->sortByDesc(fn ($code) => strlen((string) $code))
            ->values()
            ->all();

        $mobileParts = ContactValidation::splitDialCodeAndLocalNumber($customer->mobile, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));
        $phone2Parts = ContactValidation::splitDialCodeAndLocalNumber($profile->phone2, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));
        $whatsappParts = ContactValidation::splitDialCodeAndLocalNumber($profile->whatsapp, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));
        $contactMobileParts = ContactValidation::splitDialCodeAndLocalNumber($profile->contact_person_mobile, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));

        return view('admin.customers.edit', compact('customer', 'profile', 'cities', 'mobileParts', 'phone2Parts', 'whatsappParts', 'contactMobileParts'));
    }

    public function update(Request $request, User $customer)
    {
        $this->normalizeContactInputs($request, ['mobile', 'phone2', 'whatsapp', 'contact_person_mobile'], ['email']);

        $validated = $request->validate([
            'name'                    => 'required|string|max:255',
            'mobile'                  => ['nullable', ContactValidation::internationalMobileRegexRule(), Rule::unique('users', 'mobile')->ignore($customer->id)],
            'mobile_country_code'     => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'email'                   => ['nullable', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'company_name'            => 'nullable|string|max:255',
            'tax_number'              => 'nullable|string|max:100',
            'commercial_register'     => 'nullable|string|max:100',
            'customer_type'           => 'nullable|in:individual,company,enterprise',
            'billing_type'            => 'nullable|in:direct,subscription,deferred',
            'priority'                => 'nullable|in:normal,vip,premium',
            'phone2'                  => ['nullable', ContactValidation::internationalMobileRegexRule()],
            'phone2_country_code'     => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'whatsapp'                => ['nullable', ContactValidation::internationalMobileRegexRule()],
            'whatsapp_country_code'   => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'website'                 => ['nullable', 'max:255', 'regex:/^https?:\/\/.+/i'],
            'contact_person'          => 'nullable|string|max:255',
            'contact_person_mobile'   => ['nullable', ContactValidation::internationalMobileRegexRule()],
            'contact_person_mobile_country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'address_line1'           => 'nullable|string|max:500',
            'city_id'                 => 'nullable|exists:cities,id',
            'sales_rep_id'            => 'nullable|exists:users,id',
            'account_manager_id'      => 'nullable|exists:users,id',
            'bank_name'               => 'nullable|string|max:255',
            'iban'                    => 'nullable|string|max:50',
            'bank_account_holder'     => 'nullable|string|max:255',
            'credit_limit'            => 'nullable|numeric|min:0',
            'shipment_limit'          => 'nullable|integer|min:0',
            'discount_percent'        => 'nullable|numeric|min:0|max:100',
            'special_shipping_rate'   => 'nullable|numeric|min:0',
            'payment_cycle_days'      => 'nullable|integer|min:1',
            'email_notifications'     => 'nullable|boolean',
            'sms_notifications'       => 'nullable|boolean',
            'notes'                   => 'nullable|string',
        ]);

        $customer->update([
            'name'   => $validated['name'],
            'mobile' => $validated['mobile'] ?? $customer->mobile,
            'email'  => $validated['email'] ?? $customer->email,
            'user_type' => 'customer',
        ]);

        $customerRole = Role::whereIn(DB::raw('LOWER(title)'), ['customer', 'coustomer'])->first();
        if ($customerRole) {
            $customer->roles()->syncWithoutDetaching([$customerRole->id]);
        }

        $profileData = collect($validated)->except([
            'name',
            'mobile',
            'email',
            'mobile_country_code',
            'phone2_country_code',
            'whatsapp_country_code',
            'contact_person_mobile_country_code',
        ])->toArray();
        $profileData['email_notifications'] = $request->boolean('email_notifications');
        $profileData['sms_notifications']   = $request->boolean('sms_notifications');

        $profile = $customer->profile()->updateOrCreate(['user_id' => $customer->id], $profileData);

        if (($profile->billing_type ?? null) === 'deferred') {
            $profile->deferred_approved_by = auth()->id();
            $profile->deferred_approved_at = now();
        }

        $profile->syncBillingTypeFromState();
        $profile->save();

        CustomerActivityLog::log($customer->id, 'profile_updated', [], 'تحديث بيانات الملف الشخصي');

        return redirect()->route('admin.customers.show', $customer)->with('success', 'تم تحديث الملف الشخصي.');
    }

    // ─── ACCOUNT CONTROL ─────────────────────────────────────────────────────

    public function accountControl(User $customer)
    {
        $profile = $customer->profile;
        $activityLog = CustomerActivityLog::where('user_id', $customer->id)
                        ->with('performedBy')->latest()->limit(50)->get();
        return view('admin.customers.account-control', compact('customer', 'profile', 'activityLog'));
    }

    public function suspend(Request $request, User $customer)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $oldStatus = $profile->account_status;

        $profile->update([
            'account_status'    => 'suspended',
            'suspension_reason' => $request->reason,
            'suspended_at'      => now(),
            'suspended_by'      => auth()->id(),
            'suspension_lifted_at' => null,
        ]);

        // Deactivate user login
        $customer->update(['status' => 0]);

        CustomerActivityLog::log($customer->id, 'account_suspended', [
            'reason'     => $request->reason,
            'old_status' => $oldStatus,
        ], $request->reason);

        return back()->with('success', 'تم إيقاف الحساب مؤقتاً.');
    }

    public function freeze(Request $request, User $customer)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $profile->update([
            'account_status'    => 'frozen',
            'suspension_reason' => $request->reason,
            'suspended_at'      => now(),
            'suspended_by'      => auth()->id(),
        ]);

        $customer->update(['status' => 0]);
        CustomerActivityLog::log($customer->id, 'account_frozen', ['reason' => $request->reason]);

        return back()->with('success', 'تم تجميد الحساب.');
    }

    public function block(Request $request, User $customer)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $profile->update([
            'account_status'    => 'blocked',
            'suspension_reason' => $request->reason,
            'suspended_at'      => now(),
            'suspended_by'      => auth()->id(),
        ]);

        $customer->update(['status' => 0]);
        CustomerActivityLog::log($customer->id, 'account_blocked', ['reason' => $request->reason]);

        return back()->with('success', 'تم حظر الحساب.');
    }

    public function activate(Request $request, User $customer)
    {
        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $old = $profile->account_status;

        $profile->update([
            'account_status'       => 'active',
            'suspension_lifted_at' => now(),
            'suspension_reason'    => null,
        ]);

        $customer->update(['status' => 1]);
        CustomerActivityLog::log($customer->id, 'account_activated', ['old_status' => $old]);

        return back()->with('success', 'تم تفعيل الحساب.');
    }

    public function resetPassword(Request $request, User $customer)
    {
        $request->validate(['password' => 'required|min:8|confirmed']);

        $customer->update(['password' => $request->password]);
        CustomerActivityLog::log($customer->id, 'password_reset', []);

        return back()->with('success', 'تم إعادة تعيين كلمة المرور.');
    }

    // ─── DOCUMENTS ───────────────────────────────────────────────────────────

    public function documents(User $customer)
    {
        $documents = CustomerDocument::where('user_id', $customer->id)->latest()->get();
        $profile   = $customer->profile;
        return view('admin.customers.documents', compact('customer', 'documents', 'profile'));
    }

    public function uploadDocument(Request $request, User $customer)
    {
        $request->validate([
            'doc_type'    => 'required|string',
            'title'       => 'required|string|max:255',
            'file'        => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'expiry_date' => 'nullable|date',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $path     = $request->file('file')->store('customer-docs/' . $customer->id, 'public');
        $ext      = $request->file('file')->getClientOriginalExtension();

        CustomerDocument::create([
            'user_id'     => $customer->id,
            'doc_type'    => $request->doc_type,
            'title'       => $request->title,
            'file_path'   => $path,
            'file_type'   => $ext,
            'expiry_date' => $request->expiry_date,
            'notes'       => $request->notes,
            'status'      => 'pending',
        ]);

        CustomerActivityLog::log($customer->id, 'document_uploaded', ['type' => $request->doc_type, 'title' => $request->title]);

        return back()->with('success', 'تم رفع الوثيقة بنجاح.');
    }

    public function reviewDocument(Request $request, User $customer, CustomerDocument $document)
    {
        $request->validate(['status' => 'required|in:approved,rejected', 'notes' => 'nullable|string']);

        $document->update([
            'status'      => $request->status,
            'notes'       => $request->notes,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        CustomerActivityLog::log($customer->id, 'document_reviewed', [
            'doc_id' => $document->id, 'status' => $request->status,
        ]);

        return back()->with('success', 'تم تحديث حالة الوثيقة.');
    }

    public function deleteDocument(User $customer, CustomerDocument $document)
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
        CustomerActivityLog::log($customer->id, 'document_deleted', ['title' => $document->title]);

        return back()->with('success', 'تم حذف الوثيقة.');
    }

    // ─── NOTES ───────────────────────────────────────────────────────────────

    public function storeNote(Request $request, User $customer)
    {
        $request->validate([
            'type'   => 'required|in:general,financial,complaint,follow_up,important',
            'body'   => 'required|string|max:5000',
            'pinned' => 'nullable|boolean',
        ]);

        CustomerNote::create([
            'user_id'   => $customer->id,
            'author_id' => auth()->id(),
            'type'      => $request->type,
            'body'      => $request->body,
            'pinned'    => $request->boolean('pinned'),
        ]);

        return back()->with('success', 'تمت إضافة الملاحظة.');
    }

    public function deleteNote(User $customer, CustomerNote $note)
    {
        $note->delete();
        return back()->with('success', 'تم حذف الملاحظة.');
    }

    // ─── FINANCIALS ──────────────────────────────────────────────────────────

    public function financials(User $customer)
    {
        $profile      = $customer->profile;
        $codList      = CodSettlement::where('user_id', $customer->id)->with('orders')->latest()->paginate(20);
        $walletHistory = WalletHistory::where('user_id', $customer->id)->latest()->paginate(30);
        $subscriptions = UserSubscription::where('user_id', $customer->id)->with('subscription')->latest()->get();

        $orderPaymentStats = Order::where('user_id', $customer->id)
            ->selectRaw('payment_status, count(*) as cnt, sum(total_cost) as total')
            ->groupBy('payment_status')->get();

        return view('admin.customers.financials', compact(
            'customer', 'profile', 'codList', 'walletHistory',
            'subscriptions', 'orderPaymentStats'
        ));
    }

    public function adjustBalance(Request $request, User $customer)
    {
        $request->validate([
            'type'   => 'required|in:credit_balance,deferred_balance',
            'amount' => 'required|numeric',
            'note'   => 'required|string|max:500',
        ]);

        $profile = $customer->profile()->firstOrCreate(['user_id' => $customer->id]);
        $field   = $request->type;
        $old     = $profile->$field;
        $profile->increment($field, $request->amount);

        CustomerActivityLog::log($customer->id, 'balance_adjusted', [
            'field'  => $field,
            'amount' => $request->amount,
            'old'    => $old,
            'new'    => $old + $request->amount,
            'note'   => $request->note,
        ], $request->note);

        return back()->with('success', 'تم تعديل الرصيد.');
    }

    private function normalizeContactInputs(Request $request, array $mobileFields = [], array $emailFields = []): void
    {
        $normalized = [];
        $defaultDialCode = config('country_dial_codes.default', ContactValidation::COUNTRY_CODE);

        foreach ($mobileFields as $field) {
            $codeField = $field . '_country_code';
            $dialCode = ContactValidation::normalizeDialCode($request->input($codeField), $defaultDialCode);
            $normalized[$codeField] = $dialCode;
            $normalized[$field] = ContactValidation::combineDialCodeAndNumber($dialCode, $request->input($field), $defaultDialCode);
        }

        foreach ($emailFields as $field) {
            $normalized[$field] = ContactValidation::normalizeEmail($request->input($field));
        }

        $request->merge($normalized);
    }
}
