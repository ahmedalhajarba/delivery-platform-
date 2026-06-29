<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Contract;
use App\Models\CustomerActivityLog;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Models\User;
use App\Services\Validation\ContactValidation;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SalesLeadController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeLeadAccess();

        $query = SalesLead::query()
            ->with(['assignedTo', 'city', 'convertedUser'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('lead_code', 'like', '%' . $search . '%')
                    ->orWhere('company_name', 'like', '%' . $search . '%')
                    ->orWhere('store_name', 'like', '%' . $search . '%')
                    ->orWhere('contact_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('qualification_status')) {
            $query->where('qualification_status', $request->qualification_status);
        }

        if ($request->filled('lead_type')) {
            $query->where('lead_type', $request->lead_type);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $leads = $query->paginate(20)->appends($request->query());

        $salesUsers = $this->salesUsersForSelection();

        return view('admin.sales-leads.index', [
            'leads' => $leads,
            'salesUsers' => $salesUsers,
            'statuses' => SalesLead::STATUSES,
            'leadTypes' => SalesLead::LEAD_TYPES,
        ]);
    }

    public function create()
    {
        $this->authorizeLeadAccess();

        $defaultDialCode = config('country_dial_codes.default', ContactValidation::COUNTRY_CODE);

        return view('admin.sales-leads.create', [
            'lead' => new SalesLead([
                'lead_type' => 'company',
                'qualification_status' => 'cold',
            ]),
            'salesUsers' => $this->salesUsersForSelection(),
            'cities' => City::orderBy('title_ar')->get(['id', 'title_ar']),
            'statuses' => SalesLead::STATUSES,
            'leadTypes' => SalesLead::LEAD_TYPES,
            'formAction' => route('admin.sales-leads.store'),
            'formMethod' => 'POST',
            'leadMobileParts' => ['dial_code' => $defaultDialCode, 'local_number' => null],
            'leadWhatsappParts' => ['dial_code' => $defaultDialCode, 'local_number' => null],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeLeadAccess();

        $data = $this->validateLead($request);
        $data['created_by'] = auth()->id();

        $lead = SalesLead::query()->create($data);

        SalesLeadActivity::query()->create([
            'sales_lead_id' => $lead->id,
            'activity_type' => 'qualification',
            'summary' => 'تم إنشاء عميل محتمل جديد في النظام.',
            'activity_at' => now(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.sales-leads.show', $lead)->with('success', 'تم إنشاء العميل المحتمل بنجاح.');
    }

    public function show(SalesLead $salesLead)
    {
        $this->authorizeLeadAccess();

        $dialCodeList = collect(config('country_dial_codes.list', []))
            ->pluck('dial_code')
            ->unique()
            ->sortByDesc(fn ($code) => strlen((string) $code))
            ->values()
            ->all();

        $leadMobileParts = ContactValidation::splitDialCodeAndLocalNumber($salesLead->mobile, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));
        $leadWhatsappParts = ContactValidation::splitDialCodeAndLocalNumber($salesLead->whatsapp, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));

        $salesLead->load([
            'assignedTo',
            'city',
            'creator',
            'quotation',
            'contract',
            'order',
            'userSubscription',
            'convertedUser',
            'activities.creator',
        ]);

        return view('admin.sales-leads.show', [
            'lead' => $salesLead,
            'salesUsers' => $this->salesUsersForSelection(),
            'statuses' => SalesLead::STATUSES,
            'leadTypes' => SalesLead::LEAD_TYPES,
            'activityTypes' => SalesLeadActivity::ACTIVITY_TYPES,
            'quotations' => Quotation::query()->latest('id')->limit(200)->get(['id', 'quotation_number', 'user_id']),
            'contracts' => Contract::query()->latest('id')->limit(200)->get(['id', 'contract_number', 'user_id']),
            'orders' => Order::query()->latest('id')->limit(200)->get(['id', 'reference_number', 'waybill_number', 'user_id']),
            'subscriptions' => UserSubscription::query()->latest('id')->limit(200)->get(['id', 'user_id', 'subscription_id']),
            'leadMobileParts' => $leadMobileParts,
            'leadWhatsappParts' => $leadWhatsappParts,
        ]);
    }

    public function edit(SalesLead $salesLead)
    {
        $this->authorizeLeadAccess();

        $dialCodeList = collect(config('country_dial_codes.list', []))
            ->pluck('dial_code')
            ->unique()
            ->sortByDesc(fn ($code) => strlen((string) $code))
            ->values()
            ->all();

        $leadMobileParts = ContactValidation::splitDialCodeAndLocalNumber($salesLead->mobile, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));
        $leadWhatsappParts = ContactValidation::splitDialCodeAndLocalNumber($salesLead->whatsapp, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));

        return view('admin.sales-leads.create', [
            'lead' => $salesLead,
            'salesUsers' => $this->salesUsersForSelection(),
            'cities' => City::orderBy('title_ar')->get(['id', 'title_ar']),
            'statuses' => SalesLead::STATUSES,
            'leadTypes' => SalesLead::LEAD_TYPES,
            'formAction' => route('admin.sales-leads.update', $salesLead),
            'formMethod' => 'PUT',
            'leadMobileParts' => $leadMobileParts,
            'leadWhatsappParts' => $leadWhatsappParts,
        ]);
    }

    public function update(Request $request, SalesLead $salesLead)
    {
        $this->authorizeLeadAccess();

        $oldStatus = $salesLead->qualification_status;
        $data = $this->validateLead($request);

        $salesLead->update($data);

        if ($oldStatus !== $salesLead->qualification_status) {
            SalesLeadActivity::query()->create([
                'sales_lead_id' => $salesLead->id,
                'activity_type' => 'status_change',
                'summary' => 'تغيرت حالة التأهيل من ' . ($oldStatus ?: '-') . ' إلى ' . $salesLead->qualification_status,
                'activity_at' => now(),
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->route('admin.sales-leads.show', $salesLead)->with('success', 'تم تحديث العميل المحتمل.');
    }

    public function storeActivity(Request $request, SalesLead $salesLead)
    {
        $this->authorizeLeadAccess();

        $data = $request->validate([
            'activity_type' => 'required|in:' . implode(',', array_keys(SalesLeadActivity::ACTIVITY_TYPES)),
            'summary' => 'required|string|max:5000',
            'activity_at' => 'nullable|date',
            'next_action' => 'nullable|string|max:5000',
            'next_followup_at' => 'nullable|date',
        ]);

        SalesLeadActivity::query()->create([
            'sales_lead_id' => $salesLead->id,
            'activity_type' => $data['activity_type'],
            'summary' => $data['summary'],
            'activity_at' => $data['activity_at'] ?? now(),
            'next_action' => $data['next_action'] ?? null,
            'created_by' => auth()->id(),
        ]);

        if (!empty($data['next_followup_at'])) {
            $salesLead->update(['next_followup_at' => $data['next_followup_at']]);
        }

        return back()->with('success', 'تم حفظ متابعة جديدة لهذا العميل المحتمل.');
    }

    public function syncLinks(Request $request, SalesLead $salesLead)
    {
        $this->authorizeLeadAccess();

        $data = $request->validate([
            'quotation_id' => 'nullable|exists:quotations,id',
            'contract_id' => 'nullable|exists:contracts,id',
            'order_id' => 'nullable|exists:orders,id',
            'user_subscription_id' => 'nullable|exists:user_subscriptions,id',
        ]);

        $salesLead->update($data);

        SalesLeadActivity::query()->create([
            'sales_lead_id' => $salesLead->id,
            'activity_type' => 'linkage',
            'summary' => 'تم تحديث ربط العميل المحتمل مع أنظمة العروض/العقود/الطلبات/الاشتراكات.',
            'activity_at' => now(),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم تحديث الربط مع الأنظمة بنجاح.');
    }

    public function convertToCustomer(Request $request, SalesLead $salesLead)
    {
        $this->authorizeConversion();

        if ($salesLead->converted_user_id) {
            return back()->with('info', 'تم تحويل هذا العميل المحتمل مسبقًا إلى زبون فعلي.');
        }

        $this->normalizeContactInputs($request, ['mobile'], ['email']);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'mobile' => ['nullable', ContactValidation::internationalMobileRegexRule(), Rule::unique('users', 'mobile')],
            'mobile_country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'password' => 'nullable|string|min:8|max:100',
        ]);

        DB::transaction(function () use ($salesLead, $data) {
            $customerRole = Role::query()->where('title', 'Customer')->first();

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'password' => $data['password'] ?? Str::random(16),
                'status' => 0,
                'user_type' => 'customer',
                'account_status' => 'pending',
                'company_name' => $salesLead->company_name,
                'tax_number' => $salesLead->tax_number,
                'sales_rep_id' => $salesLead->assigned_to,
            ]);

            if ($customerRole) {
                $user->roles()->syncWithoutDetaching([$customerRole->id]);
            }

            $profile = CustomerProfile::query()->create([
                'user_id' => $user->id,
                'customer_code' => CustomerProfile::generateCode(),
                'company_name' => $salesLead->company_name ?: $salesLead->store_name,
                'tax_number' => $salesLead->tax_number,
                'customer_type' => $this->resolveCustomerType($salesLead->lead_type),
                'billing_type' => 'direct',
                'account_status' => 'pending',
                'phone2' => $salesLead->whatsapp,
                'website' => $salesLead->website,
                'contact_person' => $salesLead->contact_name,
                'city_id' => $salesLead->city_id,
                'address_line1' => $salesLead->address_line1,
                'sales_rep_id' => $salesLead->assigned_to,
                'notes' => trim(($salesLead->qualification_notes ?: '') . "\n\n[Lead #{$salesLead->lead_code}] تم التحويل من نظام العملاء المحتملين"),
            ]);

            $profile->syncBillingTypeFromState();
            $profile->save();

            if ($salesLead->quotation_id) {
                Quotation::query()->where('id', $salesLead->quotation_id)->update(['user_id' => $user->id]);
            }
            if ($salesLead->contract_id) {
                Contract::query()->where('id', $salesLead->contract_id)->update(['user_id' => $user->id]);
            }
            if ($salesLead->order_id) {
                Order::query()->where('id', $salesLead->order_id)->update(['user_id' => $user->id]);
            }
            if ($salesLead->user_subscription_id) {
                UserSubscription::query()->where('id', $salesLead->user_subscription_id)->update(['user_id' => $user->id]);
            }

            $salesLead->update([
                'converted_user_id' => $user->id,
                'converted_at' => now(),
                'qualification_status' => 'won',
            ]);

            SalesLeadActivity::query()->create([
                'sales_lead_id' => $salesLead->id,
                'activity_type' => 'conversion',
                'summary' => 'تم تحويل العميل المحتمل إلى زبون فعلي مع إنشاء حساب غير مفعل.',
                'activity_at' => now(),
                'created_by' => auth()->id(),
            ]);

            CustomerActivityLog::log($user->id, 'created_from_lead', [
                'lead_id' => $salesLead->id,
                'lead_code' => $salesLead->lead_code,
            ], 'تم إنشاء الزبون من نظام العملاء المحتملين');
        });

        return back()->with('success', 'تم تحويل العميل المحتمل إلى زبون فعلي (الحساب غير مفعل حتى استكمال إجراءات التفعيل).');
    }

    private function validateLead(Request $request): array
    {
        $this->normalizeContactInputs($request, ['mobile', 'whatsapp'], ['email']);
        $leadId = optional($request->route('salesLead'))->id;

        $validated = $request->validate([
            'lead_type' => 'required|in:' . implode(',', array_keys(SalesLead::LEAD_TYPES)),
            'qualification_status' => 'required|in:' . implode(',', array_keys(SalesLead::STATUSES)),
            'lead_source' => 'nullable|string|max:100',
            'company_name' => 'nullable|string|max:255',
            'store_name' => 'nullable|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_job_title' => 'nullable|string|max:255',
            'email' => ['nullable', 'email:rfc,dns', 'max:255', Rule::unique('sales_leads', 'email')->ignore($leadId)],
            'mobile' => ['nullable', ContactValidation::internationalMobileRegexRule(), Rule::unique('sales_leads', 'mobile')->ignore($leadId)],
            'mobile_country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'whatsapp' => ['nullable', ContactValidation::internationalMobileRegexRule()],
            'whatsapp_country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'website' => ['nullable', 'max:255', 'regex:/^https?:\/\/.+/i'],
            'tax_number' => 'nullable|string|max:100',
            'city_id' => 'nullable|exists:cities,id',
            'address_line1' => 'nullable|string|max:500',
            'industry' => 'nullable|string|max:150',
            'expected_monthly_shipments' => 'nullable|integer|min:0',
            'expected_monthly_revenue' => 'nullable|numeric|min:0',
            'expected_average_order_value' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|exists:users,id',
            'last_contact_at' => 'nullable|date',
            'next_followup_at' => 'nullable|date',
            'qualification_notes' => 'nullable|string|max:10000',
            'lost_reason' => 'nullable|string|max:5000',
        ]);

        unset($validated['mobile_country_code'], $validated['whatsapp_country_code']);

        $validated['expected_monthly_shipments'] = (int) ($validated['expected_monthly_shipments'] ?? 0);
        $validated['expected_monthly_revenue'] = (float) ($validated['expected_monthly_revenue'] ?? 0);
        $validated['expected_average_order_value'] = (float) ($validated['expected_average_order_value'] ?? 0);

        return $validated;
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

    private function salesUsersForSelection()
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('title', ['sales', 'sales_manager']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function authorizeLeadAccess(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        if ($user->is_admin) {
            return;
        }

        $hasRole = $user->roles()->whereIn('title', ['sales', 'sales_manager'])->exists();
        abort_unless($hasRole, 403, 'ليس لديك صلاحية الوصول لنظام العملاء المحتملين.');
    }

    private function authorizeConversion(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        if ($user->is_admin) {
            return;
        }

        $hasRole = $user->roles()->whereIn('title', ['sales_manager'])->exists();
        abort_unless($hasRole, 403, 'تحويل العميل المحتمل إلى زبون فعلي متاح لمدير المبيعات أو الإدارة فقط.');
    }

    private function resolveCustomerType(string $leadType): string
    {
        if (in_array($leadType, ['company', 'ecommerce_store', 'marketplace_seller'], true)) {
            return 'company';
        }

        return 'individual';
    }
}
